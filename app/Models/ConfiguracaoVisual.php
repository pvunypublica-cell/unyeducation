<?php

namespace App\Models;

use App\Models\Concerns\ComTimestampsPt;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Tema, home e menu do parceiro, em rascunho e publicado (UNY-DOC-005, 13.1).
 * O aluno só lê o publicado. A validação por esquema e o editor entram no M3.
 */
class ConfiguracaoVisual extends Model
{
    use BelongsToTenant, ComTimestampsPt;

    public const PUBLICADO = 'publicado';

    protected $table = 'configuracoes_visuais';

    protected $guarded = ['tenant_id'];

    protected $attributes = ['versao' => 1];

    protected function casts(): array
    {
        return [
            'conteudo_json' => 'array',
            'publicado_em' => 'datetime',
        ];
    }
}
