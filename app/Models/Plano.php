<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use Illuminate\Database\Eloquent\Model;

class Plano extends Model
{
    use ComTimestampsPt;

    protected $table = 'planos';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
