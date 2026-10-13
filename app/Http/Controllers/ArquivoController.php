<?php

namespace App\Http\Controllers;

use App\Aprendizado\Progresso;
use App\Models\Arquivo;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Material;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ArquivoController extends Controller
{
    /** Imagem pública do parceiro (capa, banner, logo). Arquivo privado nunca sai por aqui. */
    public function midia(string $uuid)
    {
        $arquivo = Arquivo::query()->where('uuid', $uuid)->where('publico', true)->firstOrFail();

        abort_unless(str_starts_with($arquivo->mime, 'image/'), 404);

        return $this->entregar($arquivo, 'inline');
    }

    /** Material de aula: parceiro certo (escopo) + aula publicada + matrícula ativa. */
    public function material(int $id, Progresso $progresso)
    {
        $aluno = Auth::guard('aluno')->user();
        $material = Material::query()->findOrFail($id);
        $aula = Aula::query()->publicadas()->findOrFail($material->aula_id);
        $curso = Curso::query()->publicados()->findOrFail($aula->curso_id);

        abort_if($progresso->matriculaAtiva($aluno, $curso) === null, 403);

        return $this->entregar(Arquivo::query()->findOrFail($material->arquivo_id), 'attachment');
    }

    private function entregar(Arquivo $arquivo, string $disposicao)
    {
        // O caminho vem do banco e é sempre relativo à pasta do parceiro; rejeita qualquer saída dela.
        abort_if(str_contains($arquivo->caminho, '..') || str_starts_with($arquivo->caminho, '/'), 404);
        abort_unless(Storage::disk('local')->exists($arquivo->caminho), 404);

        return Storage::disk('local')->response($arquivo->caminho, $arquivo->nome_original, [
            'Content-Type' => $arquivo->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $arquivo->publico ? 'public, max-age=3600' : 'private, no-store',
        ], $disposicao);
    }
}
