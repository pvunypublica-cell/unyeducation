<?php

namespace App\Catalogo;

use App\Models\Aula;
use App\Models\Curso;

/**
 * Verificação antes de publicar (UNY-DOC-003, 7.4). "Bloqueios" impedem; "avisos" só informam.
 */
class Publicacao
{
    /** @return array{bloqueios: list<string>, avisos: list<string>} */
    public static function pendencias(Curso $curso): array
    {
        $bloqueios = [];
        $avisos = [];

        if (trim($curso->titulo) === '') {
            $bloqueios[] = 'O curso precisa de um título.';
        }

        if (! Aula::query()->where('curso_id', $curso->id)->publicadas()->exists()) {
            $bloqueios[] = 'O curso precisa de ao menos uma aula publicada.';
        }

        if ($curso->capa_arquivo_id === null) {
            $avisos[] = 'O curso está sem capa; será mostrada uma capa gerada automaticamente.';
        }

        if (trim((string) $curso->descricao) === '') {
            $avisos[] = 'O curso está sem descrição.';
        }

        return ['bloqueios' => $bloqueios, 'avisos' => $avisos];
    }
}
