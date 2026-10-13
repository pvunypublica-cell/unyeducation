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

    public const UPDATED_AT = null;

    protected $table = 'tokens_acesso';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
            'usado_em' => 'datetime',
        ];
    }
}
