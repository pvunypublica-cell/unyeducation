<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Endereços de um parceiro. Fica fora do escopo de parceiro de propósito: é a tabela
 * consultada para descobrir QUAL é o parceiro, antes de haver um.
 */
class Dominio extends Model
{
    use ComTimestampsPt;

    public const ATIVO = 'ativo';

    protected $table = 'dominios';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'principal' => 'boolean',
            'verificado_em' => 'datetime',
        ];
    }

    public function parceiro(): BelongsTo
    {
        return $this->belongsTo(Parceiro::class, 'tenant_id');
    }
}
