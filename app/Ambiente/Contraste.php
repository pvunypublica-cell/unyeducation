<?php

namespace App\Ambiente;

/**
 * Contraste de cores pela fórmula do WCAG 2.x. Serve para escolher o texto sobre a cor
 * principal do parceiro e para recusar cores ilegíveis (UNY-DOC-007, 6.1 a 6.3).
 */
class Contraste
{
    public const ESCURO = '#0E0E14';

    public const CLARO = '#FFFFFF';

    public static function hexValido(mixed $valor): bool
    {
        return is_string($valor) && preg_match('/^#[0-9a-fA-F]{6}$/', $valor) === 1;
    }

    public static function luminancia(string $hex): float
    {
        $canais = array_map(function (string $par): float {
            $c = hexdec($par) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $canais[0] + 0.7152 * $canais[1] + 0.0722 * $canais[2];
    }

    public static function razao(string $a, string $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Texto (escuro ou claro) com mais contraste sobre a cor dada. */
    public static function textoSobre(string $fundo): string
    {
        return self::razao($fundo, self::ESCURO) >= self::razao($fundo, self::CLARO)
            ? self::ESCURO
            : self::CLARO;
    }
}
