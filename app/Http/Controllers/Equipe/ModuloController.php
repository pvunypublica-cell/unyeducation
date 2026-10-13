<?php

namespace App\Http\Controllers\Equipe;

use App\Auditoria\Auditor;
use App\Catalogo\Ordenacao;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Equipe\Concerns\Autoriza;
use App\Models\Aula;
use App\Models\Modulo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModuloController extends Controller
{
    use Autoriza;

    public function salvar(Request $request, int $cursoId): RedirectResponse
    {
        $curso = $this->cursoDoPainel($cursoId);
        $dados = $request->validate(['titulo' => ['required', 'string', 'max:160']], ['titulo.required' => 'Dê um nome ao módulo.']);

        $modulo = Modulo::create([
            'curso_id' => $curso->id,
            'titulo' => trim($dados['titulo']),
            'ordem' => Ordenacao::proxima(Modulo::query()->where('curso_id', $curso->id)),
        ]);
        Auditor::registrar('modulo.criado', $this->membro(), alvo: $modulo);

        return $this->volta($curso->id, 'Módulo criado.');
    }

    public function atualizar(Request $request, int $id): RedirectResponse
    {
        $modulo = Modulo::query()->findOrFail($id);
        $this->cursoDoPainel($modulo->curso_id);
        $dados = $request->validate(['titulo' => ['required', 'string', 'max:160']], ['titulo.required' => 'Dê um nome ao módulo.']);

        $modulo->update(['titulo' => trim($dados['titulo'])]);

        return $this->volta($modulo->curso_id, 'Módulo renomeado.');
    }

    public function mover(Request $request, int $id): RedirectResponse
    {
        $modulo = Modulo::query()->findOrFail($id);
        $this->cursoDoPainel($modulo->curso_id);
        $dados = $request->validate(['sentido' => ['required', 'in:sobe,desce']]);

        Ordenacao::mover(Modulo::query()->where('curso_id', $modulo->curso_id), $modulo, $dados['sentido']);

        return $this->volta($modulo->curso_id);
    }

    /** Só apaga módulo vazio: aulas com histórico de alunos não se perdem por engano. */
    public function apagar(int $id): RedirectResponse
    {
        $modulo = Modulo::query()->findOrFail($id);
        $this->cursoDoPainel($modulo->curso_id);

        if (Aula::query()->where('modulo_id', $modulo->id)->exists()) {
            return $this->volta($modulo->curso_id)->withErrors(['conteudo' => 'Este módulo ainda tem aulas. Apague ou mova as aulas antes.']);
        }

        $modulo->delete();
        Auditor::registrar('modulo.apagado', $this->membro(), alvo: $modulo);

        return $this->volta($modulo->curso_id, 'Módulo apagado.');
    }

    private function volta(int $cursoId, ?string $status = null): RedirectResponse
    {
        $resposta = redirect()->route('equipe.cursos.editar', [$cursoId, 'aba' => 'conteudo']);

        return $status ? $resposta->with('status', $status) : $resposta;
    }
}
