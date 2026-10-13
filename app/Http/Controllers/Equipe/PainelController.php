<?php

namespace App\Http\Controllers\Equipe;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Equipe\Concerns\Autoriza;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Matricula;

class PainelController extends Controller
{
    use Autoriza;

    public function index()
    {
        $porEstado = Curso::query()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return view('equipe.painel', [
            'membro' => $this->membro(),
            'cursos' => [
                'publicado' => (int) ($porEstado[Curso::PUBLICADO] ?? 0),
                'rascunho' => (int) ($porEstado[Curso::RASCUNHO] ?? 0),
                'arquivado' => (int) ($porEstado[Curso::ARQUIVADO] ?? 0),
            ],
            'alunos' => Aluno::query()->count(),
            'matriculasRecentes' => Matricula::query()->where('criado_em', '>=', now()->subDays(30))->count(),
            'concluidas' => Matricula::query()->whereNotNull('concluida_em')->count(),
            'rascunhosAntigos' => Curso::query()->where('estado', Curso::RASCUNHO)->where('atualizado_em', '<', now()->subDays(30))->count(),
        ]);
    }
}
