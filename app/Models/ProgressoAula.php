<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressoAula extends Model
{
    use BelongsToTenant, ComTimestampsPt;

    protected $table = 'progresso_aulas';

    protected $guarded = ['tenant_id'];

    protected $attributes = ['posicao_seg' => 0, 'percentual_assistido' => 0, 'concluida' => false];

    protected function casts(): array
    {
        return [
            'concluida' => 'boolean',
            'concluida_em' => 'datetime',
        ];
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }
}
