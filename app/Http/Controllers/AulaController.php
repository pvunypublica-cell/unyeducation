<?php

namespace App\Http\Controllers;

use App\Ambiente\Video;
use App\Aprendizado\Liberacao;
use App\Aprendizado\Progresso;
use App\Models\Aluno;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Modulo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AulaController extends Controller
{
    public function __construct(private readonly Progresso $progresso) {}

    public function mostrar(int $id)
    {
        [$aluno, $aula, $curso, $matricula] = $this->contexto($id);

        $mapa = $this->progresso->mapa($matricula);
        $feitas = [];
        foreach ($mapa as $aulaId => $registro) {
            if ($registro->concluida) {
                $feitas[(int) $aulaId] = true;
            }
        }

        if (Liberacao::estado($aula, $feitas) !== Liberacao::DISPONIVEL) {
            return redirect()->route('curso', $curso->slug)
                ->with('status', 'Esta aula ainda não está liberada.');
        }

        $modulos = Modulo::query()->where('curso_id', $curso->id)->orderBy('ordem')->orderBy('id')->get();
        $todas = Aula::query()->where('curso_id', $curso->id)->publicadas()
            ->orderBy('ordem')->orderBy('id')->get()->groupBy('modulo_id');

        $estruturas = [];
        foreach ($modulos as $modulo) {
            $doModulo = $todas->get($modulo->id, collect());
            if ($doModulo->isEmpty()) {
                continue;
            }
            $estruturas[] = [
                'modulo' => $modulo,
                'aulas' => $doModulo->map(fn (Aula $a) => [
                    'aula' => $a,
                    'feita' => isset($feitas[$a->id]),
                    'estado' => Liberacao::estado($a, $feitas),
                ])->all(),
            ];
        }

        $registro = $mapa[$aula->id] ?? null;

        return view('ambiente.aula', [
            'aula' => $aula,
            'curso' => $curso,
            'matricula' => $matricula,
            'estruturas' => $estruturas,
            'moduloAtual' => $modulos->firstWhere('id', $aula->modulo_id),
            'embed' => $aula->tipo === 'video' ? Video::urlIncorporada($aula->video_provedor, $aula->video_ref) : null,
            'registro' => $registro,
            'feita' => $registro?->concluida ?? false,
            'anterior' => $this->progresso->anterior($aula),
            'seguinte' => $this->progresso->seguinte($aula),
            'materiais' => $aula->materiais()->with('arquivo')->get(),
        ]);
    }

    /** Recebe a posição do vídeo enquanto o aluno assiste. */
    public function posicao(Request $request, int $id): JsonResponse
    {
        [, $aula, , $matricula] = $this->contexto($id);

        $dados = $request->validate([
            'posicao' => ['required', 'integer', 'min:0', 'max:86400'],
            'percentual' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $registro = $this->progresso->registrarPosicao($matricula, $aula, $dados['posicao'], $dados['percentual']);

        return response()->json([
            'concluida' => $registro->concluida,
            'percentual_curso' => $matricula->percentual_concluido,
        ]);
    }

    public function concluir(Request $request, int $id)
    {
        [, $aula, $curso, $matricula] = $this->contexto($id);

        $desfazer = $request->boolean('desfazer');
        $this->progresso->marcar($matricula, $aula, ! $desfazer);

        if ($desfazer) {
            return back();
        }

        $seguinte = $this->progresso->seguinte($aula);

        return $seguinte !== null
            ? redirect()->route('aula', $seguinte->id)
            : redirect()->route('curso', $curso->slug)->with('status', 'Você chegou ao fim do curso. Parabéns!');
    }

    /** @return array{0: Aluno, 1: Aula, 2: Curso, 3: Matricula} */
    private function contexto(int $id): array
    {
        $aluno = Auth::guard('aluno')->user();
        $aula = Aula::query()->publicadas()->findOrFail($id);
        $curso = Curso::query()->acessiveis()->findOrFail($aula->curso_id);
        $matricula = $this->progresso->matriculaAtiva($aluno, $curso);

        // Sem matrícula ativa não há acesso à aula (curso gratuito se inicia pela página do curso).
        abort_if($matricula === null, 403);

        return [$aluno, $aula, $curso, $matricula];
    }
}
