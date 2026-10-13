<?php

namespace Tests\Feature\Acesso;

use App\Models\Dominio;
use App\Models\TokenAcesso;
use App\Tenancy\RegrasDeSlug;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

/**
 * UNY-DOC-004 (5.2) e UNY-DOC-005 (5.3, 5.4).
 */
class ParceirosEDominiosTest extends TestCase
{
    use CriaParceiros, RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function slugsValidos(): array
    {
        return [
            'simples' => ['unyboss'],
            'uma letra' => ['a'],
            'com hífen' => ['meu-parceiro'],
            'com número' => ['curso2'],
            'só número' => ['2026'],
            '59 caracteres' => [str_repeat('a', 59)],
        ];
    }

    /** @return array<string, array{string}> */
    public static function slugsInvalidos(): array
    {
        return [
            'vazio' => [''],
            'maiúscula' => ['Unyboss'],
            'começa com hífen' => ['-abc'],
            'termina com hífen' => ['abc-'],
            'sublinhado' => ['a_b'],
            'espaço' => ['a b'],
            'ponto' => ['a.b'],
            'acento' => ['ação'],
            '60 caracteres' => [str_repeat('a', 60)],
            'reservado painel' => ['painel'],
            'reservado www' => ['www'],
            'reservado api' => ['api'],
            'reservado admin' => ['admin'],
            'reservado teste' => ['teste'],
            'reservado server' => ['server'],
            'reservado intranet' => ['intranet'],
        ];
    }

    #[DataProvider('slugsValidos')]
    public function test_slug_valido(string $slug): void
    {
        $this->assertNull(RegrasDeSlug::erro($slug));
        $this->assertTrue(RegrasDeSlug::valido($slug));
    }

    #[DataProvider('slugsInvalidos')]
    public function test_slug_invalido(string $slug): void
    {
        $this->assertNotNull(RegrasDeSlug::erro($slug));
        $this->assertFalse(RegrasDeSlug::valido($slug));
    }

    public function test_endereco_padrao_usa_prefixo_e_dominio_base(): void
    {
        $this->assertSame('ava-unyboss.faculdadeunypublica.edu.br', RegrasDeSlug::hostPadrao('unyboss'));
    }

    public function test_slug_e_unico_na_plataforma(): void
    {
        $this->criarParceiro('um');

        $this->expectException(QueryException::class);
        $this->criarParceiro('um', 'ava-outro.exemplo.com');
    }

    public function test_endereco_e_unico_em_toda_a_plataforma(): void
    {
        $this->criarParceiro('um', 'ava-compartilhado.exemplo.com');

        $this->expectException(QueryException::class);
        $this->criarParceiro('dois', 'ava-compartilhado.exemplo.com');
    }

    public function test_cada_parceiro_tem_no_maximo_um_dominio_principal(): void
    {
        $a = $this->criarParceiro('a');

        $this->expectException(QueryException::class);
        Dominio::create([
            'tenant_id' => $a->id, 'host' => 'segundo.exemplo.com',
            'tipo' => 'proprio', 'estado' => Dominio::ATIVO, 'principal' => true,
        ]);
    }

    public function test_parceiro_pode_ter_varios_dominios_nao_principais_e_parceiros_diferentes_tem_cada_um_o_seu_principal(): void
    {
        $a = $this->criarParceiro('a');
        $b = $this->criarParceiro('b');

        foreach (['x.exemplo.com', 'y.exemplo.com'] as $host) {
            Dominio::create([
                'tenant_id' => $a->id, 'host' => $host,
                'tipo' => 'proprio', 'estado' => Dominio::ATIVO, 'principal' => false,
            ]);
        }

        $this->assertSame(3, Dominio::query()->where('tenant_id', $a->id)->count());
        $this->assertSame(1, Dominio::query()->where('tenant_id', $b->id)->where('principal', true)->count());
    }

    public function test_banco_recusa_token_de_um_parceiro_apontando_para_aluno_de_outro(): void
    {
        $a = $this->criarParceiro('a');
        $b = $this->criarParceiro('b');
        $alunoDeA = $this->criarAluno($a);

        // Chave estrangeira composta (tenant_id, aluno_id): o banco impede o cruzamento,
        // mesmo que a aplicação erre (UNY-DOC-005, seção 15.1).
        $this->expectException(QueryException::class);

        TokenAcesso::query()->withoutGlobalScopes()->insert([
            'tenant_id' => $b->id,
            'aluno_id' => $alunoDeA->id,
            'token_hash' => str_repeat('a', 64),
            'expira_em' => now()->addHour(),
        ]);
    }

    public function test_email_do_aluno_e_unico_por_parceiro_e_pode_repetir_entre_parceiros(): void
    {
        $a = $this->criarParceiro('a');
        $b = $this->criarParceiro('b');

        $this->criarAluno($a, 'mesmo@exemplo.com');
        $this->criarAluno($b, 'mesmo@exemplo.com');

        $this->expectException(QueryException::class);
        $this->criarAluno($a, 'mesmo@exemplo.com');
    }
}
