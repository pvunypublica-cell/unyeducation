<?php

use App\Http\Controllers\AlunoAuthController;
use App\Http\Controllers\ArquivoController;
use App\Http\Controllers\AulaController;
use App\Http\Controllers\BuscaController;
use App\Http\Controllers\CursoController;
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

    // Ambiente do aluno (M2)
    Route::get('/busca', [BuscaController::class, 'index'])->name('busca');
    Route::get('/cursos/{slug}', [CursoController::class, 'mostrar'])->name('curso');
    Route::post('/cursos/{slug}/iniciar', [CursoController::class, 'iniciar'])->name('curso.iniciar');
    Route::post('/cursos/{slug}/favorito', [CursoController::class, 'favoritar'])->name('curso.favorito');
    Route::get('/aulas/{id}', [AulaController::class, 'mostrar'])->whereNumber('id')->name('aula');
    Route::post('/aulas/{id}/posicao', [AulaController::class, 'posicao'])->whereNumber('id')->name('aula.posicao');
    Route::post('/aulas/{id}/concluir', [AulaController::class, 'concluir'])->whereNumber('id')->name('aula.concluir');
    Route::get('/materiais/{id}', [ArquivoController::class, 'material'])->whereNumber('id')->name('material');
});

// Imagens públicas do parceiro (capas, banner, logo): sem login, mas só do parceiro do endereço.
Route::get('/midia/{uuid}', [ArquivoController::class, 'midia'])->name('midia');

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
