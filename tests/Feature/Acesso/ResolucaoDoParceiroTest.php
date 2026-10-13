<?php

namespace Tests\Feature\Acesso;

use App\Models\Dominio;
use App\Models\Parceiro;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

/**
 * Marco M1, critério de pronto: dois parceiros respondem em dois endereços, sem se misturar.
 */
class ResolucaoDoParceiroTest extends TestCase
{
    use CriaParceiros, RefreshDatabase;

    public function test_cada_endereco_mostra_o_seu_parceiro(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');

        $this->get($this->urlDe($a))->assertOk()->assertSee('Parceiro ALFA')->assertDontSee('Parceiro BETA');
        $this->get($this->urlDe($b))->assertOk()->assertSee('Parceiro BETA')->assertDontSee('Parceiro ALFA');
    }

    public function test_endereco_em_maiusculas_resolve_o_mesmo_parceiro(): void
    {
        $this->criarParceiro('alfa');

        $this->get('http://AVA-ALFA.faculdadeunypublica.edu.br/')->assertOk()->assertSee('Parceiro ALFA');
    }

    public function test_endereco_desconhecido_responde_404(): void
    {
        $this->criarParceiro('alfa');

        $this->get('http://ava-nao-existe.faculdadeunypublica.edu.br/')->assertNotFound();
        $this->get('http://exemplo.com/')->assertNotFound();
    }

    public function test_dominio_que_ainda_nao_esta_ativo_responde_404(): void
    {
        $this->criarParceiro('alfa', estadoDominio: 'aguardando_dns');

        $this->get('http://ava-alfa.faculdadeunypublica.edu.br/')->assertNotFound();
    }

    public function test_parceiro_suspenso_ou_encerrado_responde_503(): void
    {
        $this->criarParceiro('alfa', estado: Parceiro::SUSPENSO);
        $this->criarParceiro('beta', estado: Parceiro::ENCERRADO);

        $this->get('http://ava-alfa.faculdadeunypublica.edu.br/')->assertStatus(503);
        $this->get('http://ava-beta.faculdadeunypublica.edu.br/')->assertStatus(503);
    }

    public function test_dominio_proprio_do_parceiro_tambem_resolve(): void
    {
        $a = $this->criarParceiro('alfa');
        Dominio::create([
            'tenant_id' => $a->id, 'host' => 'cursos.alfa.com.br',
            'tipo' => 'proprio', 'estado' => Dominio::ATIVO, 'principal' => false,
        ]);

        $this->get('http://cursos.alfa.com.br/')->assertOk()->assertSee('Parceiro ALFA');
    }

    public function test_o_parceiro_nao_fica_definido_depois_da_requisicao(): void
    {
        $a = $this->criarParceiro('alfa');

        $this->get($this->urlDe($a))->assertOk();

        $this->assertFalse(app(TenantContext::class)->has());
    }

    public function test_pagina_de_login_mostra_a_marca_do_parceiro(): void
    {
        $a = $this->criarParceiro('alfa');

        $this->get($this->urlDe($a, '/entrar'))->assertOk()->assertSee('Parceiro ALFA');
    }
}
