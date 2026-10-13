<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Curso extends Model
{
    use BelongsToTenant, ComTimestampsPt;

    public const RASCUNHO = 'rascunho';

    public const PUBLICADO = 'publicado';

    public const ARQUIVADO = 'arquivado';

    protected $table = 'cursos';

    protected $guarded = ['tenant_id'];

    protected $attributes = [
        'estado' => self::RASCUNHO,
        'gratuito' => false,
        'certificado_ativo' => false,
        'certificado_percentual_minimo' => 100,
        'avaliacoes_ativas' => false,
        'certificado_exige_avaliacoes' => false,
    ];

    protected function casts(): array
    {
        return [
            'gratuito' => 'boolean',
            'certificado_ativo' => 'boolean',
            'avaliacoes_ativas' => 'boolean',
            'certificado_exige_avaliacoes' => 'boolean',
            'publicado_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Curso $curso): void {
            $curso->uuid ??= (string) Str::uuid();
        });
    }

    public function scopePublicados(Builder $consulta): Builder
    {
        return $consulta->where('estado', self::PUBLICADO);
    }

    public function capa(): BelongsTo
    {
        return $this->belongsTo(Arquivo::class, 'capa_arquivo_id');
    }

    public function modulos(): HasMany
    {
        return $this->hasMany(Modulo::class)->orderBy('ordem')->orderBy('id');
    }

    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class)->orderBy('ordem')->orderBy('id');
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(Categoria::class, 'curso_categoria', 'curso_id', 'categoria_id');
    }
}
