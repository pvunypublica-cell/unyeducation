<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tabelas da plataforma (UNY-DOC-005, seção 5): operadores, planos, parceiros e domínios.
 * Compatível com MySQL 5.7: sem CHECK, sem recursos do 8.0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operadores', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email', 190)->unique();
            $table->string('senha_hash');
            $table->string('papel', 30)->default('suporte'); // administrador | suporte
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->unsignedInteger('limite_alunos')->nullable();
            $table->unsignedInteger('limite_cursos')->nullable();
            $table->unsignedInteger('limite_armazenamento_mb')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        Schema::create('parceiros', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('slug', 59)->unique();
            $table->string('nome');
            $table->foreignId('plano_id')->constrained('planos');
            $table->string('estado', 20)->default('ativo'); // ativo | suspenso | encerrado
            $table->foreignId('criado_por')->nullable()->constrained('operadores')->nullOnDelete();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });

        Schema::create('dominios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('parceiros');
            $table->string('host', 190)->unique(); // único em toda a plataforma
            $table->string('tipo', 30)->default('subdominio_padrao'); // subdominio_padrao | proprio
            $table->string('estado', 30)->default('aguardando_dns');
            $table->boolean('principal')->default(false);
            // MySQL não tem índice parcial: esta coluna calculada vale tenant_id só quando
            // principal = 1, e o índice único garante no máximo um domínio principal por parceiro.
            $table->unsignedBigInteger('principal_chave')->nullable()
                ->virtualAs('IF(principal = 1, tenant_id, NULL)')->unique();
            $table->text('ultimo_erro')->nullable();
            $table->timestamp('verificado_em')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dominios');
        Schema::dropIfExists('parceiros');
        Schema::dropIfExists('planos');
        Schema::dropIfExists('operadores');
    }
};
