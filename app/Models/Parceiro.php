<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * O parceiro é o tenant. A tabela é global: não leva tenant_id nem o escopo de parceiro.
 */
class Parceiro extends Model
{
    use ComTimestampsPt;

    public const ATIVO = 'ativo';

    public const SUSPENSO = 'suspenso';

    public const ENCERRADO = 'encerrado';

    protected $table = 'parceiros';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Parceiro $parceiro): void {
            $parceiro->uuid ??= (string) Str::uuid();
        });
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    public function dominios(): HasMany
    {
        return $this->hasMany(Dominio::class, 'tenant_id');
    }
}
