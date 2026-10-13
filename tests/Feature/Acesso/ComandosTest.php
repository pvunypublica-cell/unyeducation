<?php

namespace Tests\Feature\Acesso;

use App\Models\Dominio;
use App\Models\MembroEquipe;
use App\Models\Operador;
use App\Models\Parceiro;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ComandosTest extends TestCase
{
    use RefreshDatabase;

    public function test_parceiro_criar_cria_parceiro_com_endereco_padrao_ativo(): void
    {
        $this->artisan('parceiro:criar', ['slug' => 'unyboss', 'nome' => 'Unyboss'])->assertSuccessful();

        $parceiro = Parceiro::query()->where('slug', 'unyboss')->firstOrFail();
        $dominio = Dominio::query()->where('tenant_id', $parceiro->id)->firstOrFail();

        $this->assertSame('ava-unyboss.faculdadeunypublica.edu.br', $dominio->host);
        $this->assertSame('ativo', $dominio->estado);
        $this->assertTrue($dominio->principal);
        $this->assertSame('ativo', $parceiro->estado);

        $this->get('http://ava-unyboss.faculdadeunypublica.edu.br/')->assertOk()->assertSee('Unyboss');
    }

    public function test_parceiro_criar_com_endereco_proprio_e_administrador(): void
    {
        $this->artisan('parceiro:criar', [
            'slug' => 'demo', 'nome' => 'Demo',
            '--host' => 'ava-demo.test', '--admin-email' => 'Admin@Demo.test',
        ])->expectsOutputToContain('Senha inicial')->assertSuccessful();

        $parceiro = Parceiro::query()->where('slug', 'demo')->firstOrFail();
        $this->assertSame('proprio', Dominio::query()->where('tenant_id', $parceiro->id)->value('tipo'));

        $admin = app(TenantContext::class)->runAs($parceiro->id, fn () => MembroEquipe::query()->firstOrFail());
        $this->assertSame('admin@demo.test', $admin->email);
        $this->assertSame('administrador', $admin->papel);
        $this->assertSame('ativo', $admin->estado);
    }

    public function test_parceiro_criar_recusa_slug_reservado_invalido_e_repetido(): void
    {
        $this->artisan('parceiro:criar', ['slug' => 'painel', 'nome' => 'X'])->assertFailed();
        $this->artisan('parceiro:criar', ['slug' => 'Inválido', 'nome' => 'X'])->assertFailed();

        $this->artisan('parceiro:criar', ['slug' => 'ok', 'nome' => 'X'])->assertSuccessful();
        $this->artisan('parceiro:criar', ['slug' => 'ok', 'nome' => 'Y'])->assertFailed();

        $this->assertSame(1, Parceiro::query()->count());
    }

    public function test_parceiro_criar_recusa_endereco_ja_usado(): void
    {
        $this->artisan('parceiro:criar', ['slug' => 'um', 'nome' => 'Um', '--host' => 'mesmo.test'])->assertSuccessful();
        $this->artisan('parceiro:criar', ['slug' => 'dois', 'nome' => 'Dois', '--host' => 'mesmo.test'])->assertFailed();

        $this->assertSame(1, Parceiro::query()->count());
    }

    public function test_dois_parceiros_criados_pelo_comando_respondem_em_dois_enderecos(): void
    {
        $this->artisan('parceiro:criar', ['slug' => 'um', 'nome' => 'Primeiro Parceiro'])->assertSuccessful();
        $this->artisan('parceiro:criar', ['slug' => 'dois', 'nome' => 'Segundo Parceiro'])->assertSuccessful();

        $this->get('http://ava-um.faculdadeunypublica.edu.br/')->assertSee('Primeiro Parceiro')->assertDontSee('Segundo Parceiro');
        $this->get('http://ava-dois.faculdadeunypublica.edu.br/')->assertSee('Segundo Parceiro')->assertDontSee('Primeiro Parceiro');
    }

    public function test_operador_criar_guarda_hash_da_senha(): void
    {
        $this->artisan('operador:criar', ['email' => 'Op@Exemplo.com', 'nome' => 'Operador'])
            ->expectsQuestion('Senha (mínimo 12 caracteres)', 'uma-senha-bem-longa-1')
            ->assertSuccessful();

        $operador = Operador::query()->firstOrFail();
        $this->assertSame('op@exemplo.com', $operador->email);
        $this->assertTrue(Hash::check('uma-senha-bem-longa-1', $operador->senha_hash));
    }

    public function test_operador_criar_recusa_senha_curta(): void
    {
        $this->artisan('operador:criar', ['email' => 'op@exemplo.com', 'nome' => 'Operador'])
            ->expectsQuestion('Senha (mínimo 12 caracteres)', 'curta')
            ->assertFailed();

        $this->assertSame(0, Operador::query()->count());
    }
}
