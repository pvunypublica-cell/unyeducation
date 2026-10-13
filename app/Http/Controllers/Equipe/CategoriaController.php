<?php

namespace App\Http\Controllers\Equipe;

use App\Auditoria\Auditor;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Equipe\Concerns\Autoriza;
use App\Models\Categoria;
use App\Permissoes\Matriz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Categorias valem para o parceiro todo: só quem enxerga todos os cursos (administrador) gerencia. */
class CategoriaController extends Controller
{
    use Autoriza;

    private function conferir(): void
    {
        $this->autorizar('cursos.editar');
        abort_unless(Matriz::escopo(Matriz::papelDe($this->membro()), 'cursos.editar') === 'todos', 403);
    }

    public function index()
    {
        $this->conferir();

        return view('equipe.categorias', ['categorias' => Categoria::query()->withCount('cursos')->orderBy('ordem')->orderBy('nome')->get()]);
    }

    public function salvar(Request $request): RedirectResponse
    {
        $this->conferir();
        $dados = $request->validate(['nome' => ['required', 'string', 'max:100']], ['nome.required' => 'Informe o nome da categoria.']);

        $base = Str::limit(Str::slug($dados['nome']) ?: 'categoria', 90, '');
        $slug = $base;
        for ($n = 2; Categoria::query()->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        $categoria = Categoria::create([
            'nome' => trim($dados['nome']), 'slug' => $slug,
            'ordem' => (int) Categoria::query()->max('ordem') + 1,
        ]);
        Auditor::registrar('categoria.criada', $this->membro(), alvo: $categoria);

        return back()->with('status', 'Categoria criada.');
    }

    public function alternar(int $id): RedirectResponse
    {
        $this->conferir();
        $categoria = Categoria::query()->findOrFail($id);
        $categoria->update(['ativa' => ! $categoria->ativa]);
        Auditor::registrar('categoria.alternada', $this->membro(), alvo: $categoria, detalhes: ['ativa' => $categoria->ativa]);

        return back();
    }
}
