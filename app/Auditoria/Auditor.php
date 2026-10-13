<?php

namespace App\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Grava eventos no banco de auditoria (ava_audit) pela conta que só pode INSERT e SELECT.
 * Não há método para alterar ou apagar. Não gravar senha, token nem dado pessoal além do
 * identificador do ator.
 */
class Auditor
{
    public static function registrar(
        string $acao,
        ?Model $ator = null,
        ?int $tenantId = null,
        ?Model $alvo = null,
        array $detalhes = [],
        ?string $ip = null,
    ): void {
        try {
            DB::connection('audit')->table('auditoria')->insert([
                'ocorrido_em' => now('UTC')->format('Y-m-d H:i:s.u'),
                'acao' => $acao,
                'ator_tipo' => $ator ? class_basename($ator) : null,
                'ator_id' => $ator?->getKey(),
                'tenant_id' => $tenantId ?? ($ator->tenant_id ?? null),
                'alvo_tipo' => $alvo ? class_basename($alvo) : null,
                'alvo_id' => $alvo?->getKey(),
                'ip' => $ip ?? request()?->ip(),
                'detalhes' => $detalhes === [] ? null : json_encode($detalhes, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            // Falha de auditoria não derruba o login, mas precisa aparecer no registro de erros.
            report($e);
        }
    }
}
