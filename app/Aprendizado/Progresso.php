<?php

namespace App\Aprendizado;

use App\Models\Aluno;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\ProgressoAula;
use DomainException;

/**
 * Regras de progresso (UNY-DOC-003, FS-STU-040 a 042; UNY-DOC-005, 9.3).
 * O progresso pertence à matrícula. Só aulas publicadas contam.
 */
class Progresso
{
    /** Percentual assistido a partir do qual a aula é dada como concluída sozinha. */
    public const LIMIAR_AUTOMATICO = 90;

    /** Inicia (ou devolve) a matrícula do aluno num curso GRATUITO e publicado. */
    public function iniciarGratuito(Aluno $aluno, Curso $curso): Matricula
    {
        if ($curso->estado !== Curso::PUBLICADO || ! $curso->gratuito) {
            throw new DomainException('Este curso não pode ser iniciado sem compra ou matrícula.');
        }

        return Matricula::query()->firstOrCreate(
            ['aluno_id' => $aluno->id, 'curso_id' => $curso->id],
            ['estado' => Matricula::ATIVA, 'origem' => 'nativo'],
        );
    }

    public function matriculaAtiva(Aluno $aluno, Curso $curso): ?Matricula
    {
        $matricula = Matricula::query()
            ->where('aluno_id', $aluno->id)->where('curso_id', $curso->id)->first();

        return $matricula?->estaAtiva() ? $matricula : null;
    }

    /** Guarda o ponto em que o aluno parou. Conclui sozinho ao passar do limiar. */
    public function registrarPosicao(Matricula $matricula, Aula $aula, int $posicaoSeg, int $percentual): ProgressoAula
    {
        $this->conferir($matricula, $aula);

        $registro = ProgressoAula::query()->firstOrNew(
            ['matricula_id' => $matricula->id, 'aula_id' => $aula->id],
        );

        $registro->posicao_seg = max(0, $posicaoSeg);
        $registro->percentual_assistido = max((int) $registro->percentual_assistido, max(0, min(100, $percentual)));
        $registro->atualizado_em = now();

        if (! $registro->concluida && $registro->percentual_assistido >= self::LIMIAR_AUTOMATICO) {
            $registro->concluida = true;
            $registro->concluida_em = now();
        }

        $registro->save();
        $this->recalcular($matricula);

        return $registro;
    }

    public function marcar(Matricula $matricula, Aula $aula, bool $concluida): ProgressoAula
    {
        $this->conferir($matricula, $aula);

        $registro = ProgressoAula::query()->firstOrNew(
            ['matricula_id' => $matricula->id, 'aula_id' => $aula->id],
        );

        $registro->concluida = $concluida;
        $registro->concluida_em = $concluida ? ($registro->concluida_em ?? now()) : null;
        $registro->atualizado_em = now();

        if ($concluida) {
            $registro->percentual_assistido = 100;
        }

        $registro->save();
        $this->recalcular($matricula);

        return $registro;
    }

    /** Atualiza o percentual guardado na matrícula (cache) e a data de conclusão. */
    public function recalcular(Matricula $matricula): void
    {
        $curso = Curso::query()->findOrFail($matricula->curso_id);

        $total = Aula::query()->where('curso_id', $curso->id)->publicadas()->count();

        $feitas = $total === 0 ? 0 : ProgressoAula::query()
            ->where('matricula_id', $matricula->id)->where('concluida', true)
            ->whereIn('aula_id', Aula::query()->where('curso_id', $curso->id)->publicadas()->select('id'))
            ->count();

        $percentual = $total === 0 ? 0 : (int) floor($feitas * 100 / $total);

        $matricula->percentual_concluido = $percentual;

        if ($percentual >= $curso->certificado_percentual_minimo && $total > 0) {
            $matricula->concluida_em ??= now();
        } else {
            $matricula->concluida_em = null;
        }

        $matricula->save();
    }

    /**
     * Aula para "continuar": a última mexida; se não houve, a primeira publicada.
     */
    public function aulaParaContinuar(Matricula $matricula): ?Aula
    {
        $ultima = ProgressoAula::query()
            ->where('matricula_id', $matricula->id)
            ->orderByDesc('atualizado_em')->orderByDesc('id')->first();

        if ($ultima !== null) {
            $aula = Aula::query()->publicadas()->find($ultima->aula_id);

            // Se a última já foi concluída, a retomada vai para a seguinte.
            if ($aula !== null && $ultima->concluida) {
                return $this->seguinte($aula) ?? $aula;
            }

            if ($aula !== null) {
                return $aula;
            }
        }

        return $this->primeira($matricula->curso_id);
    }

    public function primeira(int $cursoId): ?Aula
    {
        return Aula::query()->where('aulas.curso_id', $cursoId)->where('aulas.publicada', true)
            ->join('modulos', 'modulos.id', '=', 'aulas.modulo_id')
            ->orderBy('modulos.ordem')->orderBy('modulos.id')->orderBy('aulas.ordem')->orderBy('aulas.id')
            ->select('aulas.*')->first();
    }

    public function seguinte(Aula $aula): ?Aula
    {
        return $this->vizinha($aula, +1);
    }

    public function anterior(Aula $aula): ?Aula
    {
        return $this->vizinha($aula, -1);
    }

    private function vizinha(Aula $aula, int $sentido): ?Aula
    {
        $lista = Aula::query()->where('aulas.curso_id', $aula->curso_id)->publicadas()
            ->join('modulos', 'modulos.id', '=', 'aulas.modulo_id')
            ->orderBy('modulos.ordem')->orderBy('modulos.id')->orderBy('aulas.ordem')->orderBy('aulas.id')
            ->select('aulas.*')->get();

        $indice = $lista->search(fn (Aula $a) => $a->id === $aula->id);

        return $indice === false ? null : $lista->get($indice + $sentido);
    }

    private function conferir(Matricula $matricula, Aula $aula): void
    {
        if (! $matricula->estaAtiva()) {
            throw new DomainException('Matrícula sem acesso.');
        }

        if ($aula->curso_id !== $matricula->curso_id || ! $aula->publicada) {
            throw new DomainException('Esta aula não pertence ao curso da matrícula.');
        }
    }

    /** @return array<int, ProgressoAula> progresso por id de aula */
    public function mapa(Matricula $matricula): array
    {
        return ProgressoAula::query()->where('matricula_id', $matricula->id)->get()->keyBy('aula_id')->all();
    }
}
