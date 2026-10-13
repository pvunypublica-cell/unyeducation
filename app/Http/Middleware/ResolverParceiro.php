<?php

namespace App\Http\Middleware;

use App\Models\Dominio;
use App\Models\Parceiro;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descobre o parceiro pelo endereço (host) da requisição e o define para o resto do ciclo.
 * Endereço desconhecido ou domínio ainda não ativo: 404. Parceiro suspenso ou encerrado: 503.
 */
class ResolverParceiro
{
    public function __construct(private readonly TenantContext $contexto) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = mb_strtolower($request->getHost());

        $dominio = Dominio::query()
            ->where('host', $host)
            ->where('estado', Dominio::ATIVO)
            ->first();

        abort_if($dominio === null, 404);

        $parceiro = Parceiro::query()->find($dominio->tenant_id);

        abort_if($parceiro === null, 404);
        abort_unless($parceiro->estado === Parceiro::ATIVO, 503, 'Este ambiente está indisponível no momento.');

        $this->contexto->set($parceiro->id);
        $request->attributes->set('parceiro', $parceiro);
        view()->share('parceiro', $parceiro);

        try {
            return $next($request);
        } finally {
            $this->contexto->forget();
        }
    }
}
