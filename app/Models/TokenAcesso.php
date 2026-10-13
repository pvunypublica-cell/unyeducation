<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Link mágico de uso único. Só o hash do token é guardado.
 */
class TokenAcesso extends Model
{
    use BelongsToTenant, ComTimestampsPt;

    protected $table = 'tokens_acesso';

    protected $guarded = ['tenant_id'];

    // Só existe criado_em: o token nunca é atualizado, apenas marcado como usado.
    public function getUpdatedAtColumn(): ?string
    {
        return null;
    }

    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
            'usado_em' => 'datetime',
        ];
    }
}
