<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aula extends Model
{
    use BelongsToTenant, ComTimestampsPt;

    protected $table = 'aulas';

    protected $guarded = ['tenant_id'];

    protected $attributes = [
        'ordem' => 0,
        'tipo' => 'video',
        'publicada' => false,
        'liberacao_tipo' => 'imediata',
    ];

    protected function casts(): array
    {
        return [
            'publicada' => 'boolean',
            'liberacao_data' => 'datetime',
        ];
    }

    /** Só aulas publicadas contam no progresso e aparecem para o aluno (FS-REG-021). */
    public function scopePublicadas(Builder $consulta): Builder
    {
        return $consulta->where($consulta->qualifyColumn('publicada'), true);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    public function materiais(): HasMany
    {
        return $this->hasMany(Material::class)->orderBy('ordem')->orderBy('id');
    }
}
