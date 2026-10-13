<?php

namespace App\Tenancy;

/**
 * Regras do slug do parceiro (UNY-DOC-004, 5.2).
 */
class RegrasDeSlug
{
    public const MAXIMO = 59;

    /** Devolve a mensagem de erro, ou null se o slug é válido. */
    public static function erro(string $slug): ?string
    {
        if ($slug === '') {
            return 'O slug não pode ser vazio.';
        }

        if (strlen($slug) > self::MAXIMO) {
            return 'O slug pode ter no máximo '.self::MAXIMO.' caracteres.';
        }

        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $slug)) {
            return 'Use apenas letras minúsculas, números e hífen, sem começar nem terminar com hífen.';
        }

        if (in_array($slug, config('uny.slugs_reservados'), true)) {
            return 'Este nome é reservado.';
        }

        return null;
    }

    public static function valido(string $slug): bool
    {
        return self::erro($slug) === null;
    }

    /** Endereço padrão: ava-{slug}.{domínio base}. */
    public static function hostPadrao(string $slug): string
    {
        return config('uny.prefixo_host').$slug.'.'.config('uny.dominio_base');
    }
}
