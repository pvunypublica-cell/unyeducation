<?php

namespace Tests\Feature\Acesso;

use App\Mail\LinkMagicoMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

class LinkMagicoTest extends TestCase
{
    use CriaParceiros, RefreshDatabase;

    /** Pede o link e devolve a URL que foi enviada por e-mail. */
    private function pedirLink(string $urlLogin, string $email): ?string
    {
        Mail::fake();
        $this->post($urlLogin.'/link-magico', ['email' => $email])->assertSessionHas('status');

        $url = null;
        Mail::assertSent(LinkMagicoMail::class, function (LinkMagicoMail $mail) use (&$url) {
            $url = $mail->url;

            return true;
        });

        return $url;
    }

    public function test_pedir_link_envia_um_email_com_endereco_do_proprio_parceiro(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com');

        $url = $this->pedirLink($this->urlDe($a, ''), 'maria@exemplo.com');

        $this->assertStringStartsWith('http://ava-alfa.faculdadeunypublica.edu.br/link-magico/', $url);
    }

    public function test_resposta_e_igual_para_email_cadastrado_ou_nao_e_nao_envia_para_desconhecido(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com');

        Mail::fake();
        $cadastrado = $this->post($this->urlDe($a, '/link-magico'), ['email' => 'maria@exemplo.com']);
        $desconhecido = $this->post($this->urlDe($a, '/link-magico'), ['email' => 'ninguem@exemplo.com']);

        $mensagem = 'Se o e-mail estiver cadastrado, enviamos um link de acesso. Ele vale por poucos minutos.';
        $cadastrado->assertSessionHas('status', $mensagem);
        $desconhecido->assertSessionHas('status', $mensagem);
        Mail::assertSent(LinkMagicoMail::class, 1);
    }

    public function test_so_o_hash_do_token_e_guardado(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com');

        $url = $this->pedirLink($this->urlDe($a, ''), 'maria@exemplo.com');
        $token = basename($url);

        $guardado = DB::table('tokens_acesso')->value('token_hash');
        $this->assertSame(hash('sha256', $token), $guardado);
        $this->assertNotSame($token, $guardado);
    }

    public function test_abrir_o_link_nao_gasta_o_token_e_confirmar_faz_o_login(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com');
        $url = $this->pedirLink($this->urlDe($a, ''), 'maria@exemplo.com');

        // Um verificador de e-mail que só abre o link (GET) não consome nada.
        $this->get($url)->assertOk()->assertSee('Entrar');
        $this->get($url)->assertOk()->assertSee('Entrar');
        $this->assertNull(DB::table('tokens_acesso')->value('usado_em'));
        $this->assertGuest('aluno');

        $this->post($url)->assertRedirect($this->urlDe($a, '/minha-conta'));
        $this->assertAuthenticated('aluno');
        $this->assertNotNull(DB::table('tokens_acesso')->value('usado_em'));
    }

    public function test_link_so_funciona_uma_vez(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com');
        $url = $this->pedirLink($this->urlDe($a, ''), 'maria@exemplo.com');

        $this->post($url)->assertRedirect($this->urlDe($a, '/minha-conta'));

        // Segundo uso, de um navegador sem sessão.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post($url)->assertRedirect($this->urlDe($a, '/entrar'))->assertSessionHasErrors('email');
        $this->assertGuest('aluno');
    }

    public function test_link_expira(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com');
        $url = $this->pedirLink($this->urlDe($a, ''), 'maria@exemplo.com');

        $this->travel(config('uny.link_magico_minutos') + 1)->minutes();

        $this->post($url)->assertRedirect($this->urlDe($a, '/entrar'))->assertSessionHasErrors('email');
        $this->assertGuest('aluno');
    }

    public function test_link_de_um_parceiro_nao_funciona_em_outro(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->criarAluno($a, 'maria@exemplo.com');
        $url = $this->pedirLink($this->urlDe($a, ''), 'maria@exemplo.com');
        $token = basename($url);

        $this->app['auth']->forgetGuards();
        $this->post($this->urlDe($b, '/link-magico/'.$token))->assertRedirect()->assertSessionHasErrors('email');
        $this->assertGuest('aluno');
    }

    public function test_aluno_inativo_nao_recebe_link(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com', extra: ['estado' => 'inativo']);

        Mail::fake();
        $this->post($this->urlDe($a, '/link-magico'), ['email' => 'maria@exemplo.com']);
        Mail::assertNothingSent();
    }
}
