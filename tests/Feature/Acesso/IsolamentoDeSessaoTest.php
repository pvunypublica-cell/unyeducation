<?php

namespace Tests\Feature\Acesso;

use App\Http\Middleware\IsolarSessao;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

/**
 * Marco M1: a sessão de um parceiro não vale em outro (UNY-DOC-004, 5.7).
 */
class IsolamentoDeSessaoTest extends TestCase
{
    use CriaParceiros, RefreshDatabase;

    public function test_cookie_de_sessao_de_um_parceiro_nao_autentica_em_outro(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->criarAluno($a, 'maria@exemplo.com', 'senha-forte-123', ['nome' => 'Maria Souza']);

        $login = $this->post($this->urlDe($a, '/entrar'), [
            'email' => 'maria@exemplo.com',
            'password' => 'senha-forte-123',
        ]);
        $login->assertRedirect();

        $nomeCookie = config('session.cookie');
        $cookie = $login->getCookie($nomeCookie, false);
        $this->assertNotNull($cookie, 'O login deveria ter criado o cookie de sessão.');

        // Controle: o cookie vale no parceiro de origem.
        $this->app['auth']->forgetGuards();
        $this->call('GET', $this->urlDe($a, '/minha-conta'), [], [$nomeCookie => $cookie->getValue()])
            ->assertOk()->assertSee('Maria');

        // O mesmo cookie, entregue ao outro parceiro, não autentica.
        $this->app['auth']->forgetGuards();
        $resposta = $this->call('GET', $this->urlDe($b, '/minha-conta'), [], [$nomeCookie => $cookie->getValue()]);
        $resposta->assertRedirect($this->urlDe($b, '/entrar'));
    }

    public function test_cookie_de_sessao_fica_preso_ao_host_exato(): void
    {
        $this->assertNull(config('session.domain'), 'SESSION_DOMAIN deve ser nulo (cookie só do host exato).');

        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com', 'senha-forte-123');

        $login = $this->post($this->urlDe($a, '/entrar'), [
            'email' => 'maria@exemplo.com',
            'password' => 'senha-forte-123',
        ]);

        $this->assertNull($login->getCookie(config('session.cookie'), false)->getDomain());
    }

    public function test_sessao_marcada_com_outro_parceiro_e_descartada(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');

        $sessao = $this->app['session.store'];
        $sessao->put('_tenant_id', $a->id);
        $sessao->put('qualquer_dado', 'veio do parceiro A');

        $requisicao = Request::create('/');
        $requisicao->setLaravelSession($sessao);

        app(TenantContext::class)->set($b->id);
        (new IsolarSessao(app(TenantContext::class)))->handle($requisicao, fn () => new Response);

        $this->assertNull($sessao->get('qualquer_dado'));
        $this->assertSame($b->id, $sessao->get('_tenant_id'));
    }

    public function test_sessao_do_mesmo_parceiro_e_mantida(): void
    {
        $a = $this->criarParceiro('alfa');

        $sessao = $this->app['session.store'];
        $sessao->put('_tenant_id', $a->id);
        $sessao->put('qualquer_dado', 'continua');

        $requisicao = Request::create('/');
        $requisicao->setLaravelSession($sessao);

        app(TenantContext::class)->set($a->id);
        (new IsolarSessao(app(TenantContext::class)))->handle($requisicao, fn () => new Response);

        $this->assertSame('continua', $sessao->get('qualquer_dado'));
    }

    public function test_mesmo_email_em_dois_parceiros_sao_contas_separadas(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->criarAluno($a, 'maria@exemplo.com', 'senha-do-alfa-123');
        $this->criarAluno($b, 'maria@exemplo.com', 'senha-do-beta-123');

        // A senha do parceiro A não entra no parceiro B.
        $this->post($this->urlDe($b, '/entrar'), [
            'email' => 'maria@exemplo.com', 'password' => 'senha-do-alfa-123',
        ])->assertSessionHasErrors('email');

        $this->app['auth']->forgetGuards();
        $this->post($this->urlDe($b, '/entrar'), [
            'email' => 'maria@exemplo.com', 'password' => 'senha-do-beta-123',
        ])->assertRedirect();
    }

    public function test_aluno_de_um_parceiro_nao_entra_em_outro(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->criarAluno($a, 'so-no-alfa@exemplo.com', 'senha-forte-123');

        $this->post($this->urlDe($b, '/entrar'), [
            'email' => 'so-no-alfa@exemplo.com', 'password' => 'senha-forte-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('aluno');
    }
}
