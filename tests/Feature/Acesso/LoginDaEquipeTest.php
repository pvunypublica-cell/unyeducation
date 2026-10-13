<?php

namespace Tests\Feature\Acesso;

use App\Models\MembroEquipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

class LoginDaEquipeTest extends TestCase
{
    use CriaParceiros, RefreshDatabase;

    public function test_membro_ativo_entra_no_painel(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarMembro($a, email: 'chefe@exemplo.com', senha: 'senha-forte-123');

        $this->post($this->urlDe($a, '/equipe/entrar'), [
            'email' => 'chefe@exemplo.com', 'password' => 'senha-forte-123',
        ])->assertRedirect($this->urlDe($a, '/equipe'));

        $this->assertAuthenticated('equipe');
    }

    public function test_membro_convidado_ou_inativo_nao_entra(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarMembro($a, email: 'convidado@exemplo.com', estado: 'convidado');
        $this->criarMembro($a, MembroEquipe::SUPORTE, 'inativo@exemplo.com', estado: 'inativo');

        foreach (['convidado@exemplo.com', 'inativo@exemplo.com'] as $email) {
            $this->post($this->urlDe($a, '/equipe/entrar'), ['email' => $email, 'password' => 'senha-forte-123'])
                ->assertSessionHasErrors('email');
            $this->assertGuest('equipe');
        }
    }

    public function test_membro_de_um_parceiro_nao_entra_em_outro(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->criarMembro($a, email: 'chefe@exemplo.com', senha: 'senha-forte-123');

        $this->post($this->urlDe($b, '/equipe/entrar'), [
            'email' => 'chefe@exemplo.com', 'password' => 'senha-forte-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('equipe');
    }

    public function test_conta_de_aluno_nao_abre_a_area_da_equipe(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarAluno($a, 'maria@exemplo.com', 'senha-forte-123');

        $this->post($this->urlDe($a, '/equipe/entrar'), [
            'email' => 'maria@exemplo.com', 'password' => 'senha-forte-123',
        ])->assertSessionHasErrors('email');

        $this->actingAs($this->criarAluno($a, 'outra@exemplo.com'), 'aluno')
            ->get($this->urlDe($a, '/equipe'))
            ->assertRedirect($this->urlDe($a, '/equipe/entrar'));
    }

    public function test_login_do_aluno_nao_vale_como_login_da_equipe(): void
    {
        $a = $this->criarParceiro('alfa');
        $aluno = $this->criarAluno($a, 'maria@exemplo.com');

        $this->actingAs($aluno, 'aluno');

        $this->assertAuthenticated('aluno');
        $this->assertGuest('equipe');
    }

    public function test_painel_da_equipe_exige_login(): void
    {
        $a = $this->criarParceiro('alfa');

        $this->get($this->urlDe($a, '/equipe'))->assertRedirect($this->urlDe($a, '/equipe/entrar'));
    }
}
