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

    /**
     * Aceita o que o parceiro cola (endereço completo ou só o código) e devolve o código limpo,
     * ou null se não reconhecer. Nunca devolve texto que não passe no formato do provedor.
     */
    public static function extrairRef(?string $provedor, string $entrada): ?string
    {
        $entrada = trim($entrada);

        $candidato = match ($provedor) {
            'youtube' => self::deUrl($entrada, [
                '~^(?:https?://)?(?:www\.|m\.)?youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/)([A-Za-z0-9_-]{6,20})~i',
                '~^(?:https?://)?youtu\.be/([A-Za-z0-9_-]{6,20})~i',
            ]),
            'vimeo' => self::deUrl($entrada, [
                '~^(?:https?://)?(?:www\.)?vimeo\.com/(?:video/)?([0-9]{5,15})~i',
                '~^(?:https?://)?player\.vimeo\.com/video/([0-9]{5,15})~i',
            ]),
            default => null,
        };

        $candidato ??= $entrada;

        return self::urlIncorporada($provedor, $candidato) !== null ? $candidato : null;
    }

    /** @param  list<string>  $padroes */
    private static function deUrl(string $entrada, array $padroes): ?string
    {
        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $entrada, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }
}
