<?php

namespace Tests\Feature\Foundation;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UNY-DOC-005, decisões 3 e 6 · UNY-DOC-008, seção 9.5.
 * A conexão da aplicação declara charset, collation, modo estrito e fuso por conta própria,
 * porque o padrão do servidor MySQL 5.7 costuma ser latin1.
 */
class ConexaoTest extends TestCase
{
    public function test_a_conexao_usa_utf8mb4_e_a_collation_unica(): void
    {
        $linha = DB::selectOne('SELECT @@character_set_connection AS charset, @@collation_connection AS collation');

        $this->assertSame('utf8mb4', $linha->charset);
        $this->assertSame('utf8mb4_unicode_ci', $linha->collation);
    }

    public function test_a_conexao_usa_utc(): void
    {
        $linha = DB::selectOne('SELECT @@session.time_zone AS fuso');

        $this->assertSame('+00:00', $linha->fuso);
    }

    public function test_a_conexao_usa_modo_estrito(): void
    {
        $linha = DB::selectOne('SELECT @@session.sql_mode AS modo');

        $this->assertStringContainsString('STRICT_TRANS_TABLES', $linha->modo);
    }

    public function test_registra_a_versao_do_mysql_do_ci(): void
    {
        $versao = (string) DB::selectOne('SELECT VERSION() AS v')->v;

        // O CI deve rodar no MySQL 5.7 (a versão do servidor). Se rodar em outra, o aviso aparece aqui.
        fwrite(STDERR, "\n[info] MySQL do teste: {$versao}\n");

        $this->assertNotSame('', $versao);
    }
}
