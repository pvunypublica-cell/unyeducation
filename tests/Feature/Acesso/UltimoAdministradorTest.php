<?php

namespace Tests\Feature\Acesso;

use App\Exceptions\UltimoAdministrador;
use App\Models\MembroEquipe;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

/**
 * FS-ADM-060: o último administrador ativo de um parceiro não pode sair.
 */
class UltimoAdministradorTest extends TestCase
{
    use CriaParceiros, RefreshDatabase;

    private function como($parceiro, callable $acao): mixed
    {
        return app(TenantContext::class)->runAs($parceiro->id, $acao);
    }

    public function test_nao_desativa_o_unico_administrador(): void
    {
        $a = $this->criarParceiro('alfa');
        $admin = $this->criarMembro($a);

        $this->expectException(UltimoAdministrador::class);
        $this->como($a, fn () => $admin->update(['estado' => 'inativo']));
    }

    public function test_nao_rebaixa_o_unico_administrador(): void
    {
        $a = $this->criarParceiro('alfa');
        $admin = $this->criarMembro($a);

        $this->expectException(UltimoAdministrador::class);
        $this->como($a, fn () => $admin->update(['papel' => 'suporte']));
    }

    public function test_nao_remove_o_unico_administrador(): void
    {
        $a = $this->criarParceiro('alfa');
        $admin = $this->criarMembro($a);

        $this->expectException(UltimoAdministrador::class);
        $this->como($a, fn () => $admin->delete());
    }

    public function test_com_dois_administradores_um_pode_sair(): void
    {
        $a = $this->criarParceiro('alfa');
        $primeiro = $this->criarMembro($a, email: 'um@exemplo.com');
        $this->criarMembro($a, email: 'dois@exemplo.com');

        $this->como($a, fn () => $primeiro->update(['estado' => 'inativo']));

        $this->assertSame('inativo', $primeiro->fresh()->estado);
    }

    public function test_administrador_de_outro_parceiro_nao_conta(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $adminDeA = $this->criarMembro($a, email: 'a@exemplo.com');
        $this->criarMembro($b, email: 'b@exemplo.com');

        $this->expectException(UltimoAdministrador::class);
        $this->como($a, fn () => $adminDeA->update(['estado' => 'inativo']));
    }

    public function test_alterar_outros_campos_do_unico_administrador_e_permitido(): void
    {
        $a = $this->criarParceiro('alfa');
        $admin = $this->criarMembro($a);

        $this->como($a, fn () => $admin->update(['nome' => 'Novo Nome']));

        $this->assertSame('Novo Nome', $admin->fresh()->nome);
    }

    public function test_instrutor_pode_ser_removido(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarMembro($a, email: 'admin@exemplo.com');
        $instrutor = $this->criarMembro($a, MembroEquipe::INSTRUTOR, 'inst@exemplo.com');

        $this->como($a, fn () => $instrutor->delete());

        $this->assertDatabaseMissing('membros_equipe', ['email' => 'inst@exemplo.com']);
    }
}
