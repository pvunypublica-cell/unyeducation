<?php

namespace App\Ambiente;

/**
 * O AVA não guarda vídeo: guarda o identificador no provedor externo. Aqui o identificador vira
 * um endereço de incorporação SOMENTE para provedores conhecidos e com o formato esperado,
 * para que um dado do parceiro nunca vire endereço arbitrário dentro de um iframe.
 */
class Video
{
    public static function urlIncorporada(?string $provedor, ?string $ref): ?string
    {
        if ($provedor === null || $ref === null) {
            return null;
        }

        return match ($provedor) {
            'youtube' => preg_match('/^[A-Za-z0-9_-]{6,20}$/', $ref) === 1
                ? "https://www.youtube-nocookie.com/embed/{$ref}" : null,
            'vimeo' => preg_match('/^[0-9]{5,15}$/', $ref) === 1
                ? "https://player.vimeo.com/video/{$ref}" : null,
            default => null,
        };
    }
}
