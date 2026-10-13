<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Models\Concerns\SenhaHash;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Equipe da plataforma (global, sem tenant_id). UNY-DOC-005, 5.1.
 */
class Operador extends Authenticatable
{
    use ComTimestampsPt, SenhaHash;

    protected $table = 'operadores';

    protected $guarded = [];

    protected $hidden = ['senha_hash', 'remember_token'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
