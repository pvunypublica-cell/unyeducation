<?php

namespace Tests\Feature\Ambiente;

use App\Models\Aluno;
use App\Models\Curso;
use App\Models\MembroEquipe;
use App\Models\Parceiro;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_cria_parceiro_aluno_equipe_e_cursos(): void
    {
        Storage::fake('local');

        $this->artisan('demo:popular', ['--host' => 'demo.test'])->assertSuccessful();

        $parceiro = Parceiro::query()->where('slug', 'demo')->firstOrFail();
        app(TenantContext::class)->runAs($parceiro->id, function (): void {
            $this->assertSame(1, Aluno::query()->count());
            $this->assertSame(1, MembroEquipe::query()->where('papel', MembroEquipe::ADMINISTRADOR)->count());
            $this->assertSame(7, Curso::query()->publicados()->count());
        });
    }

    public function test_demo_nao_roda_em_producao(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('demo:popular')->assertFailed();
        $this->assertSame(0, Parceiro::query()->count());
    }
}
