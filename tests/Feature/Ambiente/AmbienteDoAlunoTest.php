<?php

namespace Tests\Feature\Ambiente;

use App\Models\Curso;
use App\Models\Favorito;
use App\Models\Matricula;
use App\Models\ProgressoAula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CriaCatalogo;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

class AmbienteDoAlunoTest extends TestCase
{
    use CriaCatalogo, CriaParceiros, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_home_so_mostra_cursos_publicados_do_proprio_parceiro(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->criarCurso($a, 'publicado-a');
        $this->criarCurso($a, 'rascunho-a', estado: Curso::RASCUNHO);
        $this->criarCurso($a, 'arquivado-a', estado: Curso::ARQUIVADO);
        $this->criarCurso($b, 'curso-do-beta');
        $aluno = $this->criarAluno($a);

        $resposta = $this->actingAs($aluno, 'aluno')->get($this->urlDe($a));

        $resposta->assertOk()->assertSee('Curso PUBLICADO-A');
        $resposta->assertDontSee('Curso RASCUNHO-A')->assertDontSee('Curso ARQUIVADO-A')->assertDontSee('CURSO-DO-BETA');
    }

    public function test_pagina_de_curso_de_outro_parceiro_nao_existe(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->criarCurso($b, 'so-do-beta');
        $aluno = $this->criarAluno($a);

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, '/cursos/so-do-beta'))->assertNotFound();
    }

    public function test_curso_em_rascunho_nao_abre_para_o_aluno(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarCurso($a, 'rascunho', estado: Curso::RASCUNHO);
        $aluno = $this->criarAluno($a);

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, '/cursos/rascunho'))->assertNotFound();
    }

    public function test_visitante_sem_login_vai_para_a_entrada(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarCurso($a);

        $this->get($this->urlDe($a, '/cursos/curso-a'))->assertRedirect();
    }

    public function test_aula_exige_matricula_ativa(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a);
        $aluno = $this->criarAluno($a);
        $aula = $this->aulasDe($a, $curso)->first();

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, "/aulas/{$aula->id}"))->assertForbidden();

        $this->matricular($a, $aluno, $curso, 'suspensa');
        $this->get($this->urlDe($a, "/aulas/{$aula->id}"))->assertForbidden();
    }

    public function test_aula_de_outro_parceiro_da_404(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $cursoB = $this->criarCurso($b, 'curso-b');
        $aulaB = $this->aulasDe($b, $cursoB)->first();
        $aluno = $this->criarAluno($a);

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, "/aulas/{$aulaB->id}"))->assertNotFound();
        $this->post($this->urlDe($a, "/aulas/{$aulaB->id}/concluir"))->assertNotFound();
    }

    public function test_curso_gratuito_inicia_sozinho_e_pago_nao(): void
    {
        $a = $this->criarParceiro('alfa');
        $gratis = $this->criarCurso($a, 'gratis');
        $pago = $this->criarCurso($a, 'pago', gratuito: false);
        $aluno = $this->criarAluno($a);

        $this->actingAs($aluno, 'aluno')->post($this->urlDe($a, '/cursos/gratis/iniciar'))->assertRedirect();
        $this->assertSame(1, $this->contexto($a, fn () => Matricula::query()->where('curso_id', $gratis->id)->count()));

        $this->post($this->urlDe($a, '/cursos/pago/iniciar'))->assertForbidden();
        $this->assertSame(0, $this->contexto($a, fn () => Matricula::query()->where('curso_id', $pago->id)->count()));
    }

    public function test_iniciar_duas_vezes_nao_duplica_matricula(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarCurso($a, 'gratis');
        $aluno = $this->criarAluno($a);

        $this->actingAs($aluno, 'aluno');
        $this->post($this->urlDe($a, '/cursos/gratis/iniciar'));
        $this->post($this->urlDe($a, '/cursos/gratis/iniciar'));

        $this->assertSame(1, $this->contexto($a, fn () => Matricula::query()->count()));
    }

    public function test_concluir_avanca_calcula_percentual_e_retoma_na_proxima(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'gratis', aulas: 4);
        $aluno = $this->criarAluno($a);
        $aulas = $this->aulasDe($a, $curso);
        $matricula = $this->matricular($a, $aluno, $curso);

        $this->actingAs($aluno, 'aluno')
            ->post($this->urlDe($a, "/aulas/{$aulas[0]->id}/concluir"))
            ->assertRedirectContains("/aulas/{$aulas[1]->id}");

        $this->assertSame(25, $this->contexto($a, fn () => Matricula::find($matricula->id)->percentual_concluido));

        $home = $this->get($this->urlDe($a));
        $home->assertSee('Continuar assistindo')->assertSee($aulas[1]->titulo);
    }

    public function test_concluir_tudo_marca_conclusao_e_desfazer_reverte(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'gratis', aulas: 2);
        $aluno = $this->criarAluno($a);
        $aulas = $this->aulasDe($a, $curso);
        $matricula = $this->matricular($a, $aluno, $curso);

        $this->actingAs($aluno, 'aluno');
        foreach ($aulas as $aula) {
            $this->post($this->urlDe($a, "/aulas/{$aula->id}/concluir"));
        }

        $m = $this->contexto($a, fn () => Matricula::find($matricula->id));
        $this->assertSame(100, $m->percentual_concluido);
        $this->assertNotNull($m->concluida_em);

        $this->post($this->urlDe($a, "/aulas/{$aulas[0]->id}/concluir"), ['desfazer' => 1]);
        $m = $this->contexto($a, fn () => Matricula::find($matricula->id));
        $this->assertSame(50, $m->percentual_concluido);
        $this->assertNull($m->concluida_em);
    }

    public function test_posicao_guarda_onde_parou_e_conclui_ao_passar_de_90_por_cento(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'gratis', aulas: 2);
        $aluno = $this->criarAluno($a);
        $aula = $this->aulasDe($a, $curso)->first();
        $matricula = $this->matricular($a, $aluno, $curso);

        $this->actingAs($aluno, 'aluno')
            ->postJson($this->urlDe($a, "/aulas/{$aula->id}/posicao"), ['posicao' => 120, 'percentual' => 40])
            ->assertOk()->assertJson(['concluida' => false]);

        $this->postJson($this->urlDe($a, "/aulas/{$aula->id}/posicao"), ['posicao' => 580, 'percentual' => 95])
            ->assertOk()->assertJson(['concluida' => true]);

        // o percentual assistido nunca diminui
        $this->postJson($this->urlDe($a, "/aulas/{$aula->id}/posicao"), ['posicao' => 10, 'percentual' => 1]);
        $registro = $this->contexto($a, fn () => ProgressoAula::query()->where('matricula_id', $matricula->id)->first());
        $this->assertSame(95, $registro->percentual_assistido);
        $this->assertTrue($registro->concluida);
    }

    public function test_posicao_recusa_valores_invalidos(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'gratis');
        $aluno = $this->criarAluno($a);
        $aula = $this->aulasDe($a, $curso)->first();
        $this->matricular($a, $aluno, $curso);

        $this->actingAs($aluno, 'aluno')
            ->postJson($this->urlDe($a, "/aulas/{$aula->id}/posicao"), ['posicao' => -5, 'percentual' => 500])
            ->assertStatus(422);
    }

    public function test_aula_bloqueada_por_data_nao_abre(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'gratis', aulas: 2);
        $aluno = $this->criarAluno($a);
        $aulas = $this->aulasDe($a, $curso);
        $this->matricular($a, $aluno, $curso);
        $this->contexto($a, fn () => $aulas[1]->update(['liberacao_tipo' => 'data', 'liberacao_data' => now()->addDays(3)]));

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, "/aulas/{$aulas[1]->id}"))
            ->assertRedirectContains('/cursos/gratis');
    }

    public function test_aula_liberada_apos_outra_so_abre_depois_de_concluir_a_outra(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'gratis', aulas: 2);
        $aluno = $this->criarAluno($a);
        $aulas = $this->aulasDe($a, $curso);
        $this->matricular($a, $aluno, $curso);
        $this->contexto($a, fn () => $aulas[1]->update(['liberacao_tipo' => 'apos_aula', 'liberacao_aula_id' => $aulas[0]->id]));

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, "/aulas/{$aulas[1]->id}"))->assertRedirect();

        $this->post($this->urlDe($a, "/aulas/{$aulas[0]->id}/concluir"));
        $this->get($this->urlDe($a, "/aulas/{$aulas[1]->id}"))->assertOk();
    }

    public function test_favoritar_liga_e_desliga_e_nao_vaza_entre_alunos(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarCurso($a, 'gratis');
        $ana = $this->criarAluno($a, 'ana@exemplo.com');
        $bia = $this->criarAluno($a, 'bia@exemplo.com');

        $this->actingAs($ana, 'aluno')->post($this->urlDe($a, '/cursos/gratis/favorito'));
        $this->assertSame(1, $this->contexto($a, fn () => Favorito::query()->where('aluno_id', $ana->id)->count()));
        $this->assertSame(0, $this->contexto($a, fn () => Favorito::query()->where('aluno_id', $bia->id)->count()));

        $this->post($this->urlDe($a, '/cursos/gratis/favorito'));
        $this->assertSame(0, $this->contexto($a, fn () => Favorito::query()->count()));
    }

    public function test_material_baixa_com_matricula_e_nega_sem_ela(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'gratis');
        $aluno = $this->criarAluno($a);
        $aula = $this->aulasDe($a, $curso)->first();
        $material = $this->criarMaterial($a, $aula);

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, "/materiais/{$material->id}"))->assertForbidden();

        $this->matricular($a, $aluno, $curso);
        $resposta = $this->get($this->urlDe($a, "/materiais/{$material->id}"));
        $resposta->assertOk();
        $this->assertSame('conteudo secreto', $resposta->streamedContent());
        $this->assertStringContainsString('attachment', (string) $resposta->headers->get('Content-Disposition'));
    }

    public function test_material_de_outro_parceiro_da_404(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $cursoB = $this->criarCurso($b, 'curso-b');
        $material = $this->criarMaterial($b, $this->aulasDe($b, $cursoB)->first());
        $aluno = $this->criarAluno($a);

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, "/materiais/{$material->id}"))->assertNotFound();
    }

    public function test_arquivo_privado_nao_sai_pela_rota_publica_de_midia(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a);
        $material = $this->criarMaterial($a, $this->aulasDe($a, $curso)->first());
        $uuid = $this->contexto($a, fn () => $material->arquivo->uuid);

        $this->get($this->urlDe($a, "/midia/{$uuid}"))->assertNotFound();
    }

    public function test_pagina_do_curso_com_155_aulas_nao_dispara_uma_consulta_por_aula(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'grande', aulas: 155);
        $aluno = $this->criarAluno($a);
        $this->matricular($a, $aluno, $curso);

        $this->actingAs($aluno, 'aluno');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($this->urlDe($a, '/cursos/grande'))->assertOk()->assertSee('Aula 155 de grande');
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(30, $consultas, "Consultas demais na página do curso: {$consultas}");
    }
}
