<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    use BelongsToTenant, ComTimestampsPt;

    protected $table = 'materiais';

    protected $guarded = ['tenant_id'];

    protected $attributes = ['ordem' => 0];

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }

    public function arquivo(): BelongsTo
    {
        return $this->belongsTo(Arquivo::class);
    }
}
