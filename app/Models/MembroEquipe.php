<?php

namespace App\Models;

use App\Exceptions\UltimoAdministrador;
use App\Models\Concerns\ComTimestampsPt;
use App\Models\Concerns\SenhaHash;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Pessoa da equipe do parceiro. UNY-DOC-005, 5.6.
 */
class MembroEquipe extends Authenticatable
{
    use BelongsToTenant, ComTimestampsPt, SenhaHash;

    public const ADMINISTRADOR = 'administrador';

    public const INSTRUTOR = 'instrutor';

    public const SUPORTE = 'suporte';

    public const ATIVO = 'ativo';

    protected $table = 'membros_equipe';

    protected $guarded = ['tenant_id'];

    protected $hidden = ['senha_hash', 'remember_token'];

    protected function casts(): array
    {
        return ['pode_publicar' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (MembroEquipe $membro): void {
            $membro->email = mb_strtolower(trim($membro->email));
        });

        static::updating(function (MembroEquipe $membro): void {
            $eraAdminAtivo = $membro->getOriginal('papel') === self::ADMINISTRADOR
                && $membro->getOriginal('estado') === self::ATIVO;
            $continua = $membro->papel === self::ADMINISTRADOR && $membro->estado === self::ATIVO;

            if ($eraAdminAtivo && ! $continua && ! $membro->existeOutroAdministradorAtivo()) {
                throw new UltimoAdministrador;
            }
        });

        static::deleting(function (MembroEquipe $membro): void {
            $eraAdminAtivo = $membro->papel === self::ADMINISTRADOR && $membro->estado === self::ATIVO;

            if ($eraAdminAtivo && ! $membro->existeOutroAdministradorAtivo()) {
                throw new UltimoAdministrador;
            }
        });
    }

    private function existeOutroAdministradorAtivo(): bool
    {
        return static::query()
            ->where('papel', self::ADMINISTRADOR)
            ->where('estado', self::ATIVO)
            ->whereKeyNot($this->getKey())
            ->exists();
    }
}
