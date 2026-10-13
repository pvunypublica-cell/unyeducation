<?php

namespace App\Http\Controllers;

use App\Aprendizado\Liberacao;
use App\Aprendizado\Progresso;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Favorito;
use App\Models\Modulo;
use Illuminate\Support\Facades\Auth;

class CursoController extends Controller
{
    public function __construct(private readonly Progresso $progresso) {}

    public function mostrar(string $slug)
    {
        $aluno = Auth::guard('aluno')->user();
        $curso = Curso::query()->with('capa')->publicados()->where('slug', $slug)->firstOrFail();
        $matricula = $this->progresso->matriculaAtiva($aluno, $curso);

        $modulos = Modulo::query()->where('curso_id', $curso->id)->orderBy('ordem')->orderBy('id')->get();
        $aulas = Aula::query()->where('curso_id', $curso->id)->publicadas()
            ->orderBy('ordem')->orderBy('id')->get()->groupBy('modulo_id');

        $feitas = [];
        $mapa = [];
        if ($matricula !== null) {
            $mapa = $this->progresso->mapa($matricula);
            foreach ($mapa as $aulaId => $registro) {
                if ($registro->concluida) {
                    $feitas[(int) $aulaId] = true;
                }
            }
        }

        $estruturas = [];
        $totalAulas = 0;
        $totalSeg = 0;
        foreach ($modulos as $modulo) {
            $doModulo = $aulas->get($modulo->id, collect());
            if ($doModulo->isEmpty()) {
                continue; // módulo sem aula publicada não aparece
            }
            $totalAulas += $doModulo->count();
            $totalSeg += (int) $doModulo->sum('duracao_seg');
            $estruturas[] = [
                'modulo' => $modulo,
                'aulas' => $doModulo->map(fn (Aula $a) => [
                    'aula' => $a,
                    'feita' => isset($feitas[$a->id]),
                    'estado' => Liberacao::estado($a, $feitas),
                ])->all(),
            ];
        }

        $continuar = $matricula !== null ? $this->progresso->aulaParaContinuar($matricula) : null;
        $primeira = $this->progresso->primeira($curso->id);

        return view('ambiente.curso', [
            'curso' => $curso,
            'matricula' => $matricula,
            'estruturas' => $estruturas,
            'totalAulas' => $totalAulas,
            'totalSeg' => $totalSeg,
            'continuar' => $continuar,
            'primeira' => $primeira,
            'favorito' => Favorito::query()->where('aluno_id', $aluno->id)->where('curso_id', $curso->id)->exists(),
            'categorias' => $curso->categorias()->where('ativa', true)->orderBy('ordem')->get(),
        ]);
    }

    public function iniciar(string $slug)
    {
        $aluno = Auth::guard('aluno')->user();
        $curso = Curso::query()->publicados()->where('slug', $slug)->firstOrFail();

        // Só curso gratuito se inicia sozinho; os demais dependem de matrícula (marco M4).
        abort_unless($curso->gratuito, 403);

        $matricula = $this->progresso->iniciarGratuito($aluno, $curso);
        abort_unless($matricula->estaAtiva(), 403);

        $aula = $this->progresso->aulaParaContinuar($matricula);

        return $aula !== null
            ? redirect()->route('aula', $aula->id)
            : redirect()->route('curso', $curso->slug)->with('status', 'Este curso ainda não tem aulas publicadas.');
    }

    public function favoritar(string $slug)
    {
        $aluno = Auth::guard('aluno')->user();
        $curso = Curso::query()->publicados()->where('slug', $slug)->firstOrFail();

        $existente = Favorito::query()->where('aluno_id', $aluno->id)->where('curso_id', $curso->id)->first();

        if ($existente !== null) {
            $existente->delete();
        } else {
            Favorito::create(['aluno_id' => $aluno->id, 'curso_id' => $curso->id]);
        }

        return back();
    }
}
