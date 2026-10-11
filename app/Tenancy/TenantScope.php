<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtro global: toda consulta de um modelo de parceiro leva "tenant_id = parceiro atual".
 * Sem parceiro definido, a consulta falha (TenantNotResolved), nunca devolve tudo.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $contexto = app(TenantContext::class);

        if ($contexto->isBypassed()) {
            return;
        }

        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $contexto->requireId($model::class),
        );
    }
}
