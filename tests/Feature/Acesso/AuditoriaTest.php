<?php

namespace Tests\Feature\Acesso;

use App\Auditoria\Auditor;
use App\Models\Parceiro;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use CriaParceiros, RefreshDatabase;

    public function test_registrar_grava_um_evento_no_banco_de_auditoria(): void
    {
        $inicio = now('UTC')->subSecond()->format('Y-m-d H:i:s');
        $a = $this->criarParceiro('alfa');
        $aluno = $this->criarAluno($a);

        Auditor::registrar('teste.evento', $aluno, detalhes: ['chave' => 'valor'], ip: '203.0.113.9');

        $linha = DB::connection('audit')->table('auditoria')
            ->where('acao', 'teste.evento')->where('ocorrido_em', '>=', $inicio)->first();

        $this->assertNotNull($linha);
        $this->assertSame('Aluno', $linha->ator_tipo);
        $this->assertSame((string) $aluno->id, $linha->ator_id);
        $this->assertSame($a->id, (int) $linha->tenant_id);
        $this->assertSame('203.0.113.9', $linha->ip);
        $this->assertSame(['chave' => 'valor'], json_decode($linha->detalhes, true));
    }

    public function test_banco_de_auditoria_e_separado_do_banco_principal(): void
    {
        $this->assertNotSame(
            DB::connection()->getDatabaseName(),
            DB::connection('audit')->getDatabaseName(),
        );
        $this->assertTrue(DB::connection('audit')->getSchemaBuilder()->hasTable('auditoria'));
    }

    public function test_conta_da_aplicacao_nao_consegue_alterar_nem_apagar_a_auditoria(): void
    {
        if (! env('AUDIT_DB_USERNAME') || env('AUDIT_DB_USERNAME') === env('AUDIT_MIGRATOR_USERNAME', env('DB_USERNAME'))) {
            $this->markTestSkipped('Sem conta de auditoria restrita configurada (AUDIT_DB_USERNAME). No CI ela existe.');
        }

        Auditor::registrar('teste.imutavel', ip: '203.0.113.9');

        $audit = DB::connection('audit');

        foreach ([
            'UPDATE auditoria SET acao = "alterada"',
            'DELETE FROM auditoria',
            'DROP TABLE auditoria',
            'TRUNCATE TABLE auditoria',
        ] as $comando) {
            try {
                $audit->statement($comando);
                $this->fail("A conta da aplicação não deveria conseguir executar: {$comando}");
            } catch (QueryException $e) {
                $this->assertStringContainsString('denied', strtolower($e->getMessage()), $comando);
            }
        }

        $this->assertTrue($audit->table('auditoria')->where('acao', 'teste.imutavel')->exists());
    }

    public function test_criar_parceiro_pelo_comando_fica_na_auditoria(): void
    {
        $inicio = now('UTC')->subSecond()->format('Y-m-d H:i:s');

        $this->artisan('parceiro:criar', ['slug' => 'auditado', 'nome' => 'Auditado'])->assertSuccessful();

        $parceiro = Parceiro::query()->where('slug', 'auditado')->firstOrFail();
        $this->assertTrue(
            DB::connection('audit')->table('auditoria')
                ->where('acao', 'parceiro.criado')->where('tenant_id', $parceiro->id)
                ->where('ocorrido_em', '>=', $inicio)->exists(),
        );
    }
}
