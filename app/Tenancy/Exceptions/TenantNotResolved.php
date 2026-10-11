<?php

namespace App\Tenancy\Exceptions;

use RuntimeException;

/**
 * Lançada quando algo tenta ler ou gravar dado de parceiro sem parceiro definido.
 * É de propósito um erro "barulhento": melhor falhar do que vazar dado entre parceiros.
 */
class TenantNotResolved extends RuntimeException
{
    public static function forQuery(string $model): self
    {
        return new self("Consulta em {$model} sem parceiro (tenant) definido. Defina o parceiro antes de consultar.");
    }

    public static function forWrite(string $model): self
    {
        return new self("Gravação em {$model} sem parceiro (tenant) definido.");
    }

    public static function mismatch(string $model, int $esperado, int $recebido): self
    {
        return new self("Gravação em {$model} com tenant_id {$recebido}, mas o parceiro atual é {$esperado}.");
    }
}
