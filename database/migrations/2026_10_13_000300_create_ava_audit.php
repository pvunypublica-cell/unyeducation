<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Auditoria em banco separado (ava_audit). A aplicação grava e lê por uma conta que só tem
 * INSERT e SELECT (UNY-DOC-008, 9.3); esta migração roda pela conta de migração.
 * O hasTable evita erro quando o banco principal é recriado e o de auditoria permanece.
 */
return new class extends Migration
{
    public function up(): void
    {
        $banco = Schema::connection('audit_migrator');

        if ($banco->hasTable('auditoria')) {
            return;
        }

        $banco->create('auditoria', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('ocorrido_em', 6);
            $table->string('acao', 80)->index();
            $table->string('ator_tipo', 30)->nullable();
            $table->string('ator_id', 40)->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('alvo_tipo', 60)->nullable();
            $table->string('alvo_id', 40)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('detalhes')->nullable(); // JSON, sem dados pessoais sensíveis
        });
    }

    public function down(): void
    {
        // A auditoria nunca é apagada por reversão de migração.
    }
};
