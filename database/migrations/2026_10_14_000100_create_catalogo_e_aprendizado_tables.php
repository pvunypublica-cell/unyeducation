<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Catálogo, aprendizado e tema (UNY-DOC-005, seções 8, 9 e 13).
 *
 * Toda tabela de negócio leva tenant_id e um índice único (tenant_id, id). As ligações entre
 * tabelas usam chave estrangeira COMPOSTA (tenant_id, x_id): o banco recusa uma referência
 * entre parceiros, mesmo que a aplicação erre (seção 15.1).
 *
 * Datas: DATETIME em UTC (no MySQL 5.7 um TIMESTAMP não nulo se atualiza sozinho).
 */
return new class extends Migration
{
    private function datas(Blueprint $t, bool $atualizado = true): void
    {
        $t->dateTime('criado_em')->nullable();

        if ($atualizado) {
            $t->dateTime('atualizado_em')->nullable();
        }
    }

    public function up(): void
    {
        Schema::create('arquivos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('parceiros');
            $t->char('uuid', 36)->unique();
            $t->string('caminho', 500);
            $t->string('nome_original');
            $t->string('mime', 120);
            $t->unsignedBigInteger('tamanho_bytes');
            $t->char('hash_sha256', 64);
            $t->boolean('publico')->default(false); // true só para logo, banner e capa
            $t->string('enviado_por_tipo', 30)->nullable();
            $t->unsignedBigInteger('enviado_por_id')->nullable();
            $this->datas($t);
            $t->unique(['tenant_id', 'id']);
        });

        Schema::create('categorias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('parceiros');
            $t->string('nome');
            $t->string('slug', 100);
            $t->unsignedInteger('ordem')->default(0);
            $t->boolean('ativa')->default(true);
            $this->datas($t);
            $t->unique(['tenant_id', 'slug']);
            $t->unique(['tenant_id', 'id']);
        });

        Schema::create('cursos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('parceiros');
            $t->char('uuid', 36)->unique();
            $t->string('titulo');
            $t->string('slug', 120);
            $t->text('descricao')->nullable();
            $t->unsignedBigInteger('capa_arquivo_id')->nullable();
            $t->unsignedBigInteger('capa_horizontal_arquivo_id')->nullable();
            $t->string('trailer_provedor', 30)->nullable();
            $t->string('trailer_ref')->nullable();
            $t->unsignedBigInteger('instrutor_id')->nullable();
            $t->string('estado', 20)->default('rascunho'); // rascunho | publicado | arquivado
            $t->boolean('gratuito')->default(false);
            $t->unsignedInteger('carga_horaria_min')->nullable();
            $t->boolean('certificado_ativo')->default(false);
            $t->unsignedTinyInteger('certificado_percentual_minimo')->default(100);
            $t->boolean('avaliacoes_ativas')->default(false);
            $t->boolean('certificado_exige_avaliacoes')->default(false);
            $t->unsignedBigInteger('modelo_certificado_id')->nullable();
            $t->dateTime('publicado_em')->nullable();
            $this->datas($t);

            $t->unique(['tenant_id', 'slug']);
            $t->unique(['tenant_id', 'id']);
            $t->index(['tenant_id', 'estado', 'publicado_em']);
            $t->foreign(['tenant_id', 'capa_arquivo_id'])->references(['tenant_id', 'id'])->on('arquivos');
            $t->foreign(['tenant_id', 'capa_horizontal_arquivo_id'])->references(['tenant_id', 'id'])->on('arquivos');
            $t->foreign(['tenant_id', 'instrutor_id'])->references(['tenant_id', 'id'])->on('membros_equipe');
        });

        Schema::create('curso_categoria', function (Blueprint $t) {
            $t->foreignId('tenant_id')->constrained('parceiros');
            $t->unsignedBigInteger('curso_id');
            $t->unsignedBigInteger('categoria_id');
            $t->primary(['curso_id', 'categoria_id']);
            $t->foreign(['tenant_id', 'curso_id'])->references(['tenant_id', 'id'])->on('cursos')->cascadeOnDelete();
            $t->foreign(['tenant_id', 'categoria_id'])->references(['tenant_id', 'id'])->on('categorias')->cascadeOnDelete();
        });

        Schema::create('modulos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('parceiros');
            $t->unsignedBigInteger('curso_id');
            $t->string('titulo');
            $t->unsignedInteger('ordem')->default(0);
            $t->unsignedBigInteger('capa_arquivo_id')->nullable();
            $this->datas($t);
            $t->unique(['tenant_id', 'id']);
            $t->index(['curso_id', 'ordem']);
            $t->foreign(['tenant_id', 'curso_id'])->references(['tenant_id', 'id'])->on('cursos');
            $t->foreign(['tenant_id', 'capa_arquivo_id'])->references(['tenant_id', 'id'])->on('arquivos');
        });

        Schema::create('aulas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('parceiros');
            $t->unsignedBigInteger('modulo_id');
            $t->unsignedBigInteger('curso_id'); // repetido para acelerar consultas
            $t->string('titulo');
            $t->unsignedInteger('ordem')->default(0);
            $t->string('tipo', 20)->default('video'); // video | audio | texto | tarefa | prova
            $t->longText('conteudo_texto')->nullable();
            $t->string('video_provedor', 30)->nullable();
            $t->string('video_ref')->nullable();
            $t->unsignedInteger('duracao_seg')->nullable();
            $t->boolean('publicada')->default(false);
            $t->string('liberacao_tipo', 20)->default('imediata'); // imediata | data | apos_aula | apos_aprovacao
            $t->dateTime('liberacao_data')->nullable();
            $t->unsignedBigInteger('liberacao_aula_id')->nullable();
            $this->datas($t);

            $t->unique(['tenant_id', 'id']);
            $t->index(['modulo_id', 'ordem']);
            $t->index(['curso_id', 'ordem']);
            $t->foreign(['tenant_id', 'modulo_id'])->references(['tenant_id', 'id'])->on('modulos');
            $t->foreign(['tenant_id', 'curso_id'])->references(['tenant_id', 'id'])->on('cursos');
        });

        Schema::table('aulas', function (Blueprint $t) {
            // Auto-referência: criada depois da tabela, quando o índice (tenant_id, id) já existe.
            $t->foreign(['tenant_id', 'liberacao_aula_id'])->references(['tenant_id', 'id'])->on('aulas');
        });

        Schema::create('materiais', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('parceiros');
            $t->unsignedBigInteger('aula_id');
            $t->unsignedBigInteger('arquivo_id');
            $t->string('titulo');
            $t->unsignedInteger('ordem')->default(0);
            $this->datas($t);
            $t->foreign(['tenant_id', 'aula_id'])->references(['tenant_id', 'id'])->on('aulas')->cascadeOnDelete();
            $t->foreign(['tenant_id', 'arquivo_id'])->references(['tenant_id', 'id'])->on('arquivos');
        });

        Schema::create('matriculas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('parceiros');
            $t->char('uuid', 36)->unique();
            $t->unsignedBigInteger('aluno_id');
            $t->unsignedBigInteger('curso_id');
            $t->string('estado', 20)->default('pendente'); // pendente | ativa | suspensa | expirada | cancelada
            $t->string('origem', 20); // nativo | generico | manual | codigo | importacao | painel
            $t->unsignedBigInteger('integracao_id')->nullable();
            $t->string('compra_ref')->nullable();
            $t->text('motivo')->nullable();
            $t->unsignedBigInteger('responsavel_id')->nullable();
            $t->dateTime('expira_em')->nullable();
            $t->dateTime('concluida_em')->nullable();
            $t->unsignedTinyInteger('percentual_concluido')->default(0);
            $t->string('painel_matricula_id')->nullable();
            $t->string('espelhamento_estado', 20)->default('pendente');
            $t->dateTime('espelhamento_atualizado_em')->nullable();
            $this->datas($t);

            // Uma matrícula por aluno e curso; a matrícula nunca é excluída.
            $t->unique(['tenant_id', 'aluno_id', 'curso_id']);
            $t->unique(['tenant_id', 'id']);
            $t->index(['tenant_id', 'curso_id', 'estado']);
            $t->foreign(['tenant_id', 'aluno_id'])->references(['tenant_id', 'id'])->on('alunos');
            $t->foreign(['tenant_id', 'curso_id'])->references(['tenant_id', 'id'])->on('cursos');
            $t->foreign(['tenant_id', 'responsavel_id'])->references(['tenant_id', 'id'])->on('membros_equipe');
        });

        Schema::create('progresso_aulas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('parceiros');
            $t->unsignedBigInteger('matricula_id');
            $t->unsignedBigInteger('aula_id');
            $t->unsignedInteger('posicao_seg')->default(0);
            $t->unsignedTinyInteger('percentual_assistido')->default(0);
            $t->boolean('concluida')->default(false);
            $t->dateTime('concluida_em')->nullable();
            $this->datas($t);

            $t->unique(['matricula_id', 'aula_id']);
            $t->index(['tenant_id', 'matricula_id', 'atualizado_em']);
            $t->foreign(['tenant_id', 'matricula_id'])->references(['tenant_id', 'id'])->on('matriculas');
            $t->foreign(['tenant_id', 'aula_id'])->references(['tenant_id', 'id'])->on('aulas');
        });

        Schema::create('favoritos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('parceiros');
            $t->unsignedBigInteger('aluno_id');
            $t->unsignedBigInteger('curso_id');
            $this->datas($t, atualizado: false);
            $t->unique(['tenant_id', 'aluno_id', 'curso_id']);
            $t->foreign(['tenant_id', 'aluno_id'])->references(['tenant_id', 'id'])->on('alunos')->cascadeOnDelete();
            $t->foreign(['tenant_id', 'curso_id'])->references(['tenant_id', 'id'])->on('cursos')->cascadeOnDelete();
        });

        Schema::create('configuracoes_visuais', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('parceiros');
            $t->string('tipo', 20); // tema | home | menu
            $t->string('estado', 20); // rascunho | publicado
            $t->unsignedInteger('versao')->default(1);
            $t->json('conteudo_json');
            $t->unsignedBigInteger('atualizado_por')->nullable();
            $t->dateTime('publicado_em')->nullable();
            $this->datas($t);
            $t->unique(['tenant_id', 'tipo', 'estado']);
        });
    }

    public function down(): void
    {
        foreach ([
            'configuracoes_visuais', 'favoritos', 'progresso_aulas', 'matriculas', 'materiais',
            'aulas', 'modulos', 'curso_categoria', 'cursos', 'categorias', 'arquivos',
        ] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
