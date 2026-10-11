<?php

namespace App\Tenancy\Concerns;

use App\Tenancy\Exceptions\TenantNotResolved;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Usar em todo modelo cuja tabela tenha a coluna tenant_id.
 *
 * - Leitura: filtro global por parceiro (TenantScope).
 * - Gravação: preenche tenant_id com o parceiro atual e recusa um valor diferente.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $contexto = app(TenantContext::class);
            $atual = $model->getAttribute('tenant_id');

            if ($contexto->isBypassed()) {
                if ($atual === null) {
                    throw TenantNotResolved::forWrite($model::class);
                }

                return;
            }

            $parceiro = $contexto->requireId($model::class, write: true);

            if ($atual !== null && (int) $atual !== $parceiro) {
                throw TenantNotResolved::mismatch($model::class, $parceiro, (int) $atual);
            }

            $model->setAttribute('tenant_id', $parceiro);
        });
    }
}
