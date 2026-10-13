<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Segunda trava do isolamento de sessão. A primeira é o cookie preso ao host exato
 * (SESSION_DOMAIN=null). Aqui, se uma sessão de outro parceiro chegar por qualquer
 * motivo, ela é descartada antes de qualquer autenticação.
 * Precisa rodar depois de iniciar a sessão e depois de ResolverParceiro.
 */
class IsolarSessao
{
    public function __construct(private readonly TenantContext $contexto) {}

    public function handle(Request $request, Closure $next): Response
    {
        $atual = $this->contexto->requireId('sessão');
        $sessao = $request->session();
        $guardado = $sessao->get('_tenant_id');

        if ($guardado !== null && (int) $guardado !== $atual) {
            $sessao->invalidate();
        }

        $sessao->put('_tenant_id', $atual);

        return $next($request);
    }
}
