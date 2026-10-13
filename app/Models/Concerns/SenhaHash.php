<?php

namespace App\Models\Concerns;

/**
 * A coluna de senha se chama senha_hash. O Laravel precisa saber o nome ao conferir e ao
 * regravar o hash (por exemplo, quando o custo do hash muda).
 */
trait SenhaHash
{
    public function getAuthPassword(): ?string
    {
        return $this->senha_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'senha_hash';
    }
}
