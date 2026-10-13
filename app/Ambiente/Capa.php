<?php

namespace App\Ambiente;

use App\Models\Curso;

/**
 * Capa do curso: a imagem enviada, quando existe e é pública; senão um degradê gerado a partir
 * do curso (sempre o mesmo para o mesmo curso). O degradê só usa números calculados aqui.
 */
class Capa
{
    public static function url(Curso $curso): ?string
    {
        $arquivo = $curso->capa;

        return $arquivo !== null && $arquivo->publico ? route('midia', $arquivo->uuid) : null;
    }

    public static function degrade(Curso $curso): string
    {
        $base = crc32($curso->slug ?: (string) $curso->id) % 360;
        $outra = ($base + 48) % 360;

        return "linear-gradient(145deg, hsl({$base} 55% 32%) 0%, hsl({$outra} 60% 16%) 100%)";
    }

    /** Iniciais para o degradê (até 2 letras). */
    public static function iniciais(Curso $curso): string
    {
        $palavras = preg_split('/\s+/u', trim($curso->titulo)) ?: [];
        $letras = '';
        foreach (array_slice(array_filter($palavras, fn ($p) => mb_strlen($p) > 2), 0, 2) as $palavra) {
            $letras .= mb_strtoupper(mb_substr($palavra, 0, 1));
        }

        return $letras !== '' ? $letras : mb_strtoupper(mb_substr($curso->titulo, 0, 1));
    }
}
