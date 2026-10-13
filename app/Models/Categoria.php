<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Categoria extends Model
{
    use BelongsToTenant, ComTimestampsPt;

    protected $table = 'categorias';

    protected $guarded = ['tenant_id'];

    protected $attributes = ['ordem' => 0, 'ativa' => true];

    protected function casts(): array
    {
        return ['ativa' => 'boolean'];
    }

    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_categoria', 'categoria_id', 'curso_id');
    }
}
