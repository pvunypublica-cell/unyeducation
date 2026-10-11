<?php

namespace Tests\Feature\Foundation;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SEC-034 (UNY-DOC-008): nomes difíceis devem ser gravados e lidos exatamente como digitados,
 * sem erro e sem execução. No painel atual, "Walter" (contém "alter") e nomes com apóstrofo falhavam.
 *
 * Este teste cobre a camada de banco (consultas parametrizadas). Quando o cadastro de alunos
 * existir (marco M1), o mesmo conjunto de nomes passa também pela requisição HTTP completa.
 */
class NomesDificeisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('teste_nomes');
        Schema::create('teste_nomes', function (Blueprint $tabela): void {
            $tabela->id();
            $tabela->string('nome', 150);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('teste_nomes');

        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nomes(): array
    {
        return [
            'palavra alter dentro do nome' => ['Walter'],
            'alter no começo' => ['Alterio'],
            'so alter' => ['Alter'],
            'apostrofo comum' => ["D'Ávila"],
            'apostrofo irlandes' => ["O'Connor"],
            'apostrofo no meio' => ["Santa'Ana"],
            'dois apostrofos' => ["Mc'Donald's"],
            'aspas duplas' => ['Maria "Duda" Souza'],
            'acentos' => ['José Conceição Müller'],
            'emoji de 4 bytes' => ['José 😀'],
            'ideogramas' => ['李小龙'],
            'hifen' => ['Ana-Maria'],
            'palavras de SQL' => ['Select Union Drop Delete Insert Update'],
            'injecao classica' => ["Robert'); DROP TABLE teste_nomes;--"],
            'injecao com delete' => ["'; DELETE FROM teste_nomes; --"],
            'injecao com ou' => ["' OR '1'='1"],
            'comentario sql' => ['Nome /* comentario */ Teste'],
            'barra invertida' => ['Ana\\Maria'],
        ];
    }

    #[DataProvider('nomes')]
    public function test_o_nome_e_gravado_e_lido_exatamente_como_digitado(string $nome): void
    {
        $id = DB::table('teste_nomes')->insertGetId(['nome' => $nome]);

        $this->assertSame($nome, DB::table('teste_nomes')->where('id', $id)->value('nome'));
        $this->assertTrue(Schema::hasTable('teste_nomes'), 'A tabela não pode ter sido afetada pelo texto.');
    }

    #[DataProvider('nomes')]
    public function test_o_nome_pode_ser_usado_como_filtro_de_busca(string $nome): void
    {
        DB::table('teste_nomes')->insert(['nome' => $nome]);

        $achados = DB::table('teste_nomes')->where('nome', $nome)->count();

        $this->assertGreaterThanOrEqual(1, $achados);
    }

    public function test_todos_os_nomes_juntos_nao_apagam_nada(): void
    {
        $nomes = array_map(fn (array $linha) => $linha[0], self::nomes());

        foreach ($nomes as $nome) {
            DB::table('teste_nomes')->insert(['nome' => $nome]);
        }

        $this->assertSame(count($nomes), DB::table('teste_nomes')->count());
    }
}
