<?php

namespace App\Http\Controllers\Equipe\Concerns;

use App\Models\Curso;
use App\Models\MembroEquipe;
use App\Permissoes\Matriz;
use Illuminate\Support\Facades\Auth;

trait Autoriza
{
    protected function membro(): MembroEquipe
    {
        return Auth::guard('equipe')->user();
    }

    /** Recusa (403) se o membro não puder fazer a ação; com curso, confere também o dono. */
    protected function autorizar(string $acao, ?Curso $curso = null): void
    {
        $permitido = $curso === null
            ? Matriz::permite($this->membro(), $acao)
            : Matriz::permiteSobre($this->membro(), $acao, $curso->instrutor_id);

        abort_unless($permitido, 403);
    }

    /** Curso do parceiro atual; instrutor só alcança os seus (403 para os outros). */
    protected function cursoDoPainel(int $id, string $acao = 'cursos.editar'): Curso
    {
        $curso = Curso::query()->findOrFail($id);
        $this->autorizar($acao, $curso);

        return $curso;
    }
}
