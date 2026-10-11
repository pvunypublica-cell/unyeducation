<?php

namespace Tests\Feature\Foundation;

use App\Tenancy\Concerns\BelongsToTenant;
use App\Tenancy\Exceptions\TenantNotResolved;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * UNY-DOC-004, seção 6.2: nenhuma consulta sem parceiro; um parceiro nunca enxerga o outro.
 * Usa uma tabela de teste própria, criada e apagada em cada teste.
 */
class IsolamentoPorParceiroTest extends TestCase
{
    private TenantContext $parceiro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parceiro = app(TenantContext::class);
        $this->parceiro->forget();

        Schema::dropIfExists('teste_itens');
        Schema::create('teste_itens', function (Blueprint $tabela): void {
            $tabela->id();
            $tabela->unsignedBigInteger('tenant_id');
            $tabela->string('nome', 100);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('teste_itens');

        parent::tearDown();
    }

    public function test_consulta_sem_parceiro_falha(): void
    {
        $this->expectException(TenantNotResolved::class);

        ItemDeTeste::query()->get();
    }

    public function test_gravacao_sem_parceiro_falha(): void
    {
        $this->expectException(TenantNotResolved::class);

        ItemDeTeste::create(['nome' => 'sem parceiro']);
    }

    public function test_cada_parceiro_enxerga_somente_os_proprios_dados(): void
    {
        $this->parceiro->runAs(1, fn () => ItemDeTeste::create(['nome' => 'A1']));
        $this->parceiro->runAs(2, fn () => ItemDeTeste::create(['nome' => 'B1']));
        $this->parceiro->runAs(2, fn () => ItemDeTeste::create(['nome' => 'B2']));

        $do1 = $this->parceiro->runAs(1, fn () => ItemDeTeste::orderBy('nome')->pluck('nome')->all());
        $do2 = $this->parceiro->runAs(2, fn () => ItemDeTeste::orderBy('nome')->pluck('nome')->all());

        $this->assertSame(['A1'], $do1);
        $this->assertSame(['B1', 'B2'], $do2);
    }

    public function test_gravacao_preenche_o_parceiro_atual(): void
    {
        $item = $this->parceiro->runAs(7, fn () => ItemDeTeste::create(['nome' => 'X']));

        $this->assertSame(7, (int) $item->tenant_id);
    }

    public function test_nao_se_acessa_registro_de_outro_parceiro_pelo_id(): void
    {
        $idDoB = $this->parceiro->runAs(2, fn () => ItemDeTeste::create(['nome' => 'B1'])->id);

        $achado = $this->parceiro->runAs(1, fn () => ItemDeTeste::find($idDoB));

        $this->assertNull($achado);
    }

    public function test_nao_se_apaga_nem_se_altera_registro_de_outro_parceiro(): void
    {
        $idDoB = $this->parceiro->runAs(2, fn () => ItemDeTeste::create(['nome' => 'B1'])->id);

        $apagados = $this->parceiro->runAs(1, fn () => ItemDeTeste::whereKey($idDoB)->delete());
        $alterados = $this->parceiro->runAs(1, fn () => ItemDeTeste::whereKey($idDoB)->update(['nome' => 'hackeado']));

        $this->assertSame(0, $apagados);
        $this->assertSame(0, $alterados);
        $this->assertSame('B1', $this->parceiro->runAs(2, fn () => ItemDeTeste::find($idDoB)->nome));
    }

    public function test_gravar_com_tenant_id_de_outro_parceiro_e_recusado(): void
    {
        $this->expectException(TenantNotResolved::class);

        $this->parceiro->runAs(1, fn () => ItemDeTeste::create(['nome' => 'X', 'tenant_id' => 2]));
    }

    public function test_a_saida_de_emergencia_enxerga_todos_os_parceiros(): void
    {
        $this->parceiro->runAs(1, fn () => ItemDeTeste::create(['nome' => 'A1']));
        $this->parceiro->runAs(2, fn () => ItemDeTeste::create(['nome' => 'B1']));

        $total = $this->parceiro->bypass(fn () => ItemDeTeste::count());

        $this->assertSame(2, $total);
    }

    public function test_a_saida_de_emergencia_nao_vaza_para_fora_do_bloco(): void
    {
        $this->parceiro->bypass(fn () => null);

        $this->expectException(TenantNotResolved::class);

        ItemDeTeste::query()->get();
    }

    public function test_runas_restaura_o_parceiro_anterior(): void
    {
        $this->parceiro->set(1);

        $this->parceiro->runAs(2, fn () => null);

        $this->assertSame(1, $this->parceiro->id());
    }
}

/**
 * Modelo só de teste: representa qualquer modelo de parceiro do sistema.
 */
class ItemDeTeste extends Model
{
    use BelongsToTenant;

    protected $table = 'teste_itens';

    public $timestamps = false;

    protected $guarded = [];
}
