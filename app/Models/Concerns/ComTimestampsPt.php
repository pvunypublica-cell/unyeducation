<?php

namespace App\Models\Concerns;

/**
 * As tabelas usam criado_em / atualizado_em (UNY-DOC-005, seção 3), não created_at / updated_at.
 */
trait ComTimestampsPt
{
    public function getCreatedAtColumn(): string
    {
        return 'criado_em';
    }

    public function getUpdatedAtColumn(): string
    {
        return 'atualizado_em';
    }
}
