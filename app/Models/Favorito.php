<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorito extends Model
{
    use BelongsToTenant, ComTimestampsPt;

    protected $table = 'favoritos';

    protected $guarded = ['tenant_id'];

    public function getUpdatedAtColumn(): ?string
    {
        return null;
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
