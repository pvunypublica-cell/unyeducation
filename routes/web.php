<?php

use App\Http\Controllers\AlunoAuthController;
use App\Http\Controllers\ArquivoController;
use App\Http\Controllers\AulaController;
use App\Http\Controllers\BuscaController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\Equipe;
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
        Route::get('/', [Equipe\PainelController::class, 'index'])->name('equipe.painel');
        Route::post('/sair', [EquipeAuthController::class, 'sair'])->name('equipe.logout');

        // Cursos (M3a)
        Route::get('/cursos', [Equipe\CursoController::class, 'index'])->name('equipe.cursos');
        Route::get('/cursos/novo', [Equipe\CursoController::class, 'criar'])->name('equipe.cursos.criar');
        Route::post('/cursos', [Equipe\CursoController::class, 'salvar'])->name('equipe.cursos.salvar');
        Route::get('/cursos/{id}', [Equipe\CursoController::class, 'editar'])->whereNumber('id')->name('equipe.cursos.editar');
        Route::post('/cursos/{id}', [Equipe\CursoController::class, 'atualizar'])->whereNumber('id')->name('equipe.cursos.atualizar');
        Route::post('/cursos/{id}/capa', [Equipe\CursoController::class, 'capa'])->whereNumber('id')->name('equipe.cursos.capa');
        Route::post('/cursos/{id}/publicar', [Equipe\CursoController::class, 'publicar'])->whereNumber('id')->name('equipe.cursos.publicar');
        Route::post('/cursos/{id}/arquivar', [Equipe\CursoController::class, 'arquivar'])->whereNumber('id')->name('equipe.cursos.arquivar');
        Route::post('/cursos/{id}/rascunho', [Equipe\CursoController::class, 'rascunho'])->whereNumber('id')->name('equipe.cursos.rascunho');
        Route::get('/cursos/{id}/previa', [Equipe\CursoController::class, 'previa'])->whereNumber('id')->name('equipe.cursos.previa');

        Route::post('/cursos/{id}/modulos', [Equipe\ModuloController::class, 'salvar'])->whereNumber('id')->name('equipe.modulos.salvar');
        Route::post('/modulos/{id}', [Equipe\ModuloController::class, 'atualizar'])->whereNumber('id')->name('equipe.modulos.atualizar');
        Route::post('/modulos/{id}/mover', [Equipe\ModuloController::class, 'mover'])->whereNumber('id')->name('equipe.modulos.mover');
        Route::post('/modulos/{id}/apagar', [Equipe\ModuloController::class, 'apagar'])->whereNumber('id')->name('equipe.modulos.apagar');

        Route::post('/modulos/{id}/aulas', [Equipe\AulaController::class, 'salvar'])->whereNumber('id')->name('equipe.aulas.salvar');
        Route::get('/aulas/{id}', [Equipe\AulaController::class, 'editar'])->whereNumber('id')->name('equipe.aulas.editar');
        Route::post('/aulas/{id}', [Equipe\AulaController::class, 'atualizar'])->whereNumber('id')->name('equipe.aulas.atualizar');
        Route::post('/aulas/{id}/publicar', [Equipe\AulaController::class, 'publicar'])->whereNumber('id')->name('equipe.aulas.publicar');
        Route::post('/aulas/{id}/mover', [Equipe\AulaController::class, 'mover'])->whereNumber('id')->name('equipe.aulas.mover');
        Route::post('/aulas/{id}/apagar', [Equipe\AulaController::class, 'apagar'])->whereNumber('id')->name('equipe.aulas.apagar');
        Route::post('/aulas/{id}/materiais', [Equipe\AulaController::class, 'enviarMaterial'])->whereNumber('id')->name('equipe.materiais.enviar');
        Route::post('/materiais/{id}/apagar', [Equipe\AulaController::class, 'apagarMaterial'])->whereNumber('id')->name('equipe.materiais.apagar');

        Route::get('/categorias', [Equipe\CategoriaController::class, 'index'])->name('equipe.categorias');
        Route::post('/categorias', [Equipe\CategoriaController::class, 'salvar'])->name('equipe.categorias.salvar');
        Route::post('/categorias/{id}/alternar', [Equipe\CategoriaController::class, 'alternar'])->whereNumber('id')->name('equipe.categorias.alternar');
    });
});
