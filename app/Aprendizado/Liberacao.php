<?php

namespace App\Aprendizado;

use App\Models\Aula;

/**
 * Regra de liberação de uma aula (UNY-DOC-005, 8.5).
 * A prova (apos_aprovacao) entra com as avaliações; até lá, fica bloqueada.
 */
class Liberacao
{
    public const DISPONIVEL = 'disponivel';

    public const BLOQUEADA_DATA = 'bloqueada_data';

    public const BLOQUEADA_AULA = 'bloqueada_aula';

    public const BLOQUEADA_AVALIACAO = 'bloqueada_avaliacao';

    /** @param  array<int, true>  $concluidas  ids das aulas concluídas (chave = id) */
    public static function estado(Aula $aula, array $concluidas): string
    {
        return match ($aula->liberacao_tipo) {
            'data' => $aula->liberacao_data !== null && $aula->liberacao_data->isFuture()
                ? self::BLOQUEADA_DATA : self::DISPONIVEL,
            'apos_aula' => $aula->liberacao_aula_id !== null && ! isset($concluidas[$aula->liberacao_aula_id])
                ? self::BLOQUEADA_AULA : self::DISPONIVEL,
            'apos_aprovacao' => self::BLOQUEADA_AVALIACAO,
            default => self::DISPONIVEL,
        };
    }
}
