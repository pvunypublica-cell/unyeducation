<?php

namespace App\Tenancy;

use App\Tenancy\Exceptions\TenantNotResolved;

/**
 * Guarda o parceiro (tenant) da requisição atual.
 *
 * Registrada como singleton: uma instância por requisição, ou por tarefa de fila
 * (a tarefa deve definir o parceiro explicitamente antes de tocar em dados).
 */
class TenantContext
{
    private ?int $tenantId = null;

    private int $bypassDepth = 0;

    public function set(int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function forget(): void
    {
        $this->tenantId = null;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    public function requireId(string $model, bool $write = false): int
    {
        if ($this->tenantId === null) {
            throw $write ? TenantNotResolved::forWrite($model) : TenantNotResolved::forQuery($model);
        }

        return $this->tenantId;
    }

    /**
     * Executa $callback como o parceiro informado e restaura o anterior ao terminar.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(int $tenantId, callable $callback): mixed
    {
        $anterior = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->tenantId = $anterior;
        }
    }

    /**
     * Saída de emergência: executa sem o filtro de parceiro (painel do operador, rotinas globais).
     * Todo uso deve ser auditado (UNY-DOC-003, FS-OPR-003); a auditoria entra no marco M1.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function bypass(callable $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }
}
