<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Coração do sistema (UNY-DOC-005, 9.1). Uma por aluno e curso; nunca é excluída.
 * No M2 só existe a origem "nativo" (curso gratuito); as demais entram no M4.
 */
class Matricula extends Model
{
    use BelongsToTenant, ComTimestampsPt;

    public const ATIVA = 'ativa';

    protected $table = 'matriculas';

    protected $guarded = ['tenant_id'];

    protected $attributes = ['percentual_concluido' => 0, 'espelhamento_estado' => 'pendente'];

    protected function casts(): array
    {
        return [
            'concluida_em' => 'datetime',
            'expira_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Matricula $matricula): void {
            $matricula->uuid ??= (string) Str::uuid();
        });

        static::deleting(function (): never {
            throw new \LogicException('Matrícula nunca é excluída; mude o estado.');
        });
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function progressos(): HasMany
    {
        return $this->hasMany(ProgressoAula::class);
    }

    public function estaAtiva(): bool
    {
        return $this->estado === self::ATIVA && ($this->expira_em === null || $this->expira_em->isFuture());
    }
}
