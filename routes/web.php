<?php

use App\Http\Controllers\AlunoAuthController;
use App\Http\Controllers\EquipeAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LinkMagicoController;
use Illuminate\Support\Facades\Route;

// Todas as rotas do grupo web passam por ResolverParceiro e IsolarSessao (bootstrap/app.php).

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('guest:aluno')->group(function () {
    Route::get('/entrar', [AlunoAuthController::class, 'formLogin'])->name('login');
    Route::post('/entrar', [AlunoAuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/cadastro', [AlunoAuthController::class, 'formCadastro'])->name('cadastro');
    Route::post('/cadastro', [AlunoAuthController::class, 'cadastrar'])->middleware('throttle:login');

    Route::post('/link-magico', [LinkMagicoController::class, 'solicitar'])
        ->middleware('throttle:login')->name('link-magico.solicitar');
    Route::get('/link-magico/{token}', [LinkMagicoController::class, 'confirmar'])->name('link-magico.confirmar');
    Route::post('/link-magico/{token}', [LinkMagicoController::class, 'entrar'])->name('link-magico.entrar');
});

Route::middleware('auth:aluno')->group(function () {
    Route::get('/minha-conta', [AlunoAuthController::class, 'conta'])->name('conta');
    Route::post('/sair', [AlunoAuthController::class, 'sair'])->name('logout');
});

Route::prefix('equipe')->group(function () {
    Route::middleware('guest:equipe')->group(function () {
        Route::get('/entrar', [EquipeAuthController::class, 'formLogin'])->name('equipe.login');
        Route::post('/entrar', [EquipeAuthController::class, 'login'])->middleware('throttle:login');
    });

    Route::middleware('auth:equipe')->group(function () {
        Route::get('/', [EquipeAuthController::class, 'painel'])->name('equipe.painel');
        Route::post('/sair', [EquipeAuthController::class, 'sair'])->name('equipe.logout');
    });
});
