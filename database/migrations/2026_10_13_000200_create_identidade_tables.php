<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Identidade (UNY-DOC-005, seção 6). O CPF entra no marco M6, não aqui.
 * Cada tabela de negócio tem tenant_id e um índice único (tenant_id, id), para que as
 * tabelas filhas usem chave estrangeira composta e o banco recuse referência entre parceiros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membros_equipe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('parceiros');
            $table->string('nome');
            $table->string('email', 190);
            $table->string('senha_hash');
            $table->string('papel', 20); // administrador | instrutor | suporte
            $table->boolean('pode_publicar')->default(false);
            $table->string('estado', 20)->default('convidado'); // convidado | ativo | inativo
            $table->rememberToken();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('alunos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('parceiros');
            $table->char('uuid', 36)->unique();
            $table->string('nome');
            $table->string('email', 190);
            $table->string('senha_hash')->nullable(); // nulo enquanto só usa link mágico
            $table->string('telefone', 30)->nullable();
            $table->string('estado', 20)->default('ativo'); // ativo | inativo | anonimizado
            $table->timestamp('ultimo_acesso_em')->nullable();
            $table->timestamp('email_verificado_em')->nullable();
            $table->rememberToken();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('tokens_acesso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('parceiros');
            $table->unsignedBigInteger('aluno_id');
            $table->char('token_hash', 64)->unique();
            $table->dateTime('expira_em'); // dateTime: o MySQL 5.7 daria a um TIMESTAMP NOT NULL o padrão de se atualizar sozinho
            $table->timestamp('usado_em')->nullable();
            $table->timestamp('criado_em')->nullable();

            // Chave composta: o token e o aluno precisam ser do mesmo parceiro.
            $table->foreign(['tenant_id', 'aluno_id'])
                ->references(['tenant_id', 'id'])->on('alunos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens_acesso');
        Schema::dropIfExists('alunos');
        Schema::dropIfExists('membros_equipe');
    }
};
