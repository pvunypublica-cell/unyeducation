<?php

namespace App\Providers;

use App\Permissoes\Matriz;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Um parceiro (tenant) por requisição; uma instância por ciclo de vida da aplicação.
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Uma regra por ação da matriz (UNY-DOC-003, 3.2); a checagem real fica no servidor.
        foreach (Matriz::acoes() as $acao) {
            Gate::define($acao, fn (object $usuario) => Matriz::permite($usuario, $acao));
        }

        // Limite de tentativas por e-mail, endereço de rede e parceiro.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            mb_strtolower((string) $request->input('email')).'|'.$request->ip().'|'.$this->app->make(TenantContext::class)->id(),
        ));
    }
}
