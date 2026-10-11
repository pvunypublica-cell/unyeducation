<?php

namespace Tests\Feature\Foundation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UNY-DOC-005, decisão 3: InnoDB, utf8mb4 e uma única collation em TODAS as tabelas e colunas de texto.
 * O painel atual sofreu com collations diferentes entre tabelas; aqui isso é proibido desde o início.
 */
class CharsetTest extends TestCase
{
    use RefreshDatabase;

    private const COLLATION = 'utf8mb4_unicode_ci';

    public function test_todas_as_tabelas_usam_innodb_e_a_collation_unica(): void
    {
        $esquema = DB::getDatabaseName();

        $fora = DB::select(
            "SELECT TABLE_NAME AS tabela, ENGINE AS motor, TABLE_COLLATION AS collation
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
                AND (ENGINE <> 'InnoDB' OR TABLE_COLLATION <> ?)",
            [$esquema, self::COLLATION],
        );

        $this->assertSame([], $fora, 'Tabelas fora do padrão: '.json_encode($fora));
    }

    public function test_todas_as_colunas_de_texto_usam_a_collation_unica(): void
    {
        $esquema = DB::getDatabaseName();

        $fora = DB::select(
            'SELECT TABLE_NAME AS tabela, COLUMN_NAME AS coluna, COLLATION_NAME AS collation
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ? AND COLLATION_NAME IS NOT NULL AND COLLATION_NAME <> ?',
            [$esquema, self::COLLATION],
        );

        $this->assertSame([], $fora, 'Colunas fora do padrão: '.json_encode($fora));
    }
}
