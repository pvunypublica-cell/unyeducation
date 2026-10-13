<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Models\Concerns\SenhaHash;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

/**
 * Conta do aluno, pertencente a um único parceiro. UNY-DOC-005, 6.1 (sem CPF até o M6).
 */
class Aluno extends Authenticatable
{
    use BelongsToTenant, ComTimestampsPt, SenhaHash;

    protected $table = 'alunos';

    protected $guarded = ['tenant_id'];

    protected $hidden = ['senha_hash', 'remember_token'];

    // Mesmos padrões da tabela, para o objeto recém-criado já refletir o banco.
    protected $attributes = ['estado' => 'ativo'];

    protected function casts(): array
    {
        return [
            'ultimo_acesso_em' => 'datetime',
            'email_verificado_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Aluno $aluno): void {
            $aluno->uuid ??= (string) Str::uuid();
            $aluno->email = mb_strtolower(trim($aluno->email));
        });
    }
}
