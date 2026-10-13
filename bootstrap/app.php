<?php

use App\Http\Middleware\IsolarSessao;
use App\Http\Middleware\ResolverParceiro;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Depois da sessão (que já faz parte do grupo web): primeiro descobre o parceiro
        // pelo endereço, depois confere se a sessão pertence a ele.
        $middleware->appendToGroup('web', [
            ResolverParceiro::class,
            IsolarSessao::class,
        ]);

        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('equipe', 'equipe/*') ? route('equipe.login') : route('login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
