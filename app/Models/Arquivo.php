<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Registro de arquivo enviado. O arquivo fica fora da pasta pública, sob o parceiro,
 * e só é entregue por uma rota que confere parceiro e matrícula.
 */
class Arquivo extends Model
{
    use BelongsToTenant, ComTimestampsPt;

    protected $table = 'arquivos';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['publico' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Arquivo $arquivo): void {
            $arquivo->uuid ??= (string) Str::uuid();
        });
    }
}
