<?php

namespace Tests\Feature\Acesso;

use App\Models\Aluno;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

class LoginDoAlunoTest extends TestCase
{
    use CriaParceiros, RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function nomesDificeis(): array
    {
        return [
            'Walter' => ['Walter'],
            'Alterio' => ['Alterio Select Drop'],
            'apóstrofo' => ["D'Ávila"],
            'apóstrofo duplo' => ["O'Connor-D'Ávila"],
            'acentos' => ['José da Conceição Müller'],
            'emoji' => ['Maria 😀'],
            'injeção SQL' => ["Robert'); DROP TABLE alunos;--"],
            'html' => ['<script>alert(1)</script>'],
            'aspas' => ['Ana "Nana" Souza'],
        ];
    }

    #[DataProvider('nomesDificeis')]
    public function test_cadastro_aceita_nomes_dificeis_e_guarda_exatamente_como_veio(string $nome): void
    {
        $a = $this->criarParceiro('alfa');

        $this->post($this->urlDe($a, '/cadastro'), [
            'nome' => $nome,
            'email' => 'pessoa@exemplo.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertRedirect($this->urlDe($a, '/minha-conta'));

        $aluno = app(TenantContext::class)->runAs($a->id, fn () => Aluno::query()->firstOrFail());
        $this->assertSame($nome, $aluno->nome);
        $this->assertSame(1, DB::table('alunos')->count(), 'A tabela de alunos deve continuar existindo e com 1 linha.');
    }

    public function test_cadastro_cria_conta_entra_e_vai_para_a_conta(): void
    {
        $a = $this->criarParceiro('alfa');

        $this->post($this->urlDe($a, '/cadastro'), [
            'nome' => 'Maria Souza',
            'email' => '  MARIA@Exemplo.COM ',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertRedirect($this->urlDe($a, '/minha-conta'));

        $this->assertAuthenticated('aluno');
        $this->assertSame('maria@exemplo.com', Aluno::query()->withoutGlobalScopes()->firstOrFail()->email);
        $this->assertNotSame('senha-forte-123', Aluno::query()->withoutGlobalScopes()->firstOrFail()->senha_hash);
    }

    public function test_cadastro_recusa_senha_curta_e_confirmacao_diferente(): void
    {
        $a = $this->criarParceiro('alfa');

        $this->post($this->urlDe($a, '/cadastro'), [
            'nome' => 'Maria', 'email' => 'maria@exemplo.com',
            'password' => 'curta', 'password_confirmation' => 'curta',
        ])->assertSessionHasErrors('password');

        $this->post($this->urlDe($a, '/cadastro'), [
            'nome' => 'Maria', 'email' => 'maria@exemplo.com',
            'password' => 'senha-forte-123', 'password_confirmation' => 'outra-senha-123',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, DB::table('alunos')->count());
    }

    public function test_cadastro_com_email_ja_existente_nao_revela_e_nao_duplica(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com', 'senha-original-123');

        $this->post($this->urlDe($a, '/cadastro'), [
            'nome' => 'Intrusa', 'email' => 'maria@exemplo.com',
            'password' => 'outra-senha-123', 'password_confirmation' => 'outra-senha-123',
        ])->assertRedirect($this->urlDe($a, '/entrar'))->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('alunos')->count());
        $this->assertGuest('aluno');
    }

    public function test_login_com_senha_correta(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com', 'senha-forte-123');

        $this->post($this->urlDe($a, '/entrar'), [
            'email' => 'Maria@Exemplo.com', 'password' => 'senha-forte-123',
        ])->assertRedirect($this->urlDe($a, '/minha-conta'));

        $this->assertAuthenticated('aluno');
    }

    public function test_login_com_senha_errada_ou_conta_inexistente_da_a_mesma_mensagem(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com', 'senha-forte-123');

        $errada = $this->post($this->urlDe($a, '/entrar'), ['email' => 'maria@exemplo.com', 'password' => 'errada-errada']);
        $inexistente = $this->post($this->urlDe($a, '/entrar'), ['email' => 'ninguem@exemplo.com', 'password' => 'errada-errada']);

        $errada->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
        $inexistente->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
        $this->assertGuest('aluno');
    }

    public function test_aluno_inativo_nao_entra(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com', 'senha-forte-123', ['estado' => 'inativo']);

        $this->post($this->urlDe($a, '/entrar'), [
            'email' => 'maria@exemplo.com', 'password' => 'senha-forte-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('aluno');
    }

    public function test_area_do_aluno_exige_login(): void
    {
        $a = $this->criarParceiro('alfa');

        $this->get($this->urlDe($a, '/minha-conta'))->assertRedirect($this->urlDe($a, '/entrar'));
    }

    public function test_sair_encerra_a_sessao(): void
    {
        $a = $this->criarParceiro('alfa');
        $aluno = $this->criarAluno($a);

        $this->actingAs($aluno, 'aluno')
            ->post($this->urlDe($a, '/sair'))
            ->assertRedirect($this->urlDe($a, '/'));

        $this->assertGuest('aluno');
    }

    public function test_depois_de_cinco_tentativas_o_login_e_limitado(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com', 'senha-forte-123');

        for ($i = 0; $i < 5; $i++) {
            $this->post($this->urlDe($a, '/entrar'), ['email' => 'maria@exemplo.com', 'password' => 'errada-errada'])
                ->assertSessionHasErrors('email');
        }

        $this->post($this->urlDe($a, '/entrar'), ['email' => 'maria@exemplo.com', 'password' => 'senha-forte-123'])
            ->assertStatus(429);
    }

    public function test_login_e_tentativa_falha_ficam_na_auditoria(): void
    {
        $inicio = now('UTC')->subSecond()->format('Y-m-d H:i:s');
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com', 'senha-forte-123');

        $this->post($this->urlDe($a, '/entrar'), ['email' => 'maria@exemplo.com', 'password' => 'errada-errada']);
        $this->post($this->urlDe($a, '/entrar'), ['email' => 'maria@exemplo.com', 'password' => 'senha-forte-123']);

        $registros = DB::connection('audit')->table('auditoria')
            ->where('tenant_id', $a->id)->where('ocorrido_em', '>=', $inicio)->pluck('acao');

        $this->assertContains('aluno.login_falhou', $registros);
        $this->assertContains('aluno.login', $registros);

        // A senha nunca vai para a auditoria.
        $texto = DB::connection('audit')->table('auditoria')->where('ocorrido_em', '>=', $inicio)->get()->toJson();
        $this->assertStringNotContainsString('senha-forte-123', $texto);
        $this->assertStringNotContainsString('errada-errada', $texto);
    }
}
