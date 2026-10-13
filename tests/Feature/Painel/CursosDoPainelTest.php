<?php

namespace Tests\Feature\Painel;

use App\Ambiente\Video;
use App\Models\Arquivo;
use App\Models\Aula;
use App\Models\Categoria;
use App\Models\Curso;
use App\Models\Material;
use App\Models\MembroEquipe;
use App\Models\Modulo;
use App\Models\ProgressoAula;
use App\Permissoes\Matriz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CriaCatalogo;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

class CursosDoPainelTest extends TestCase
{
    use CriaCatalogo, CriaParceiros, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admin($parceiro): MembroEquipe
    {
        return $this->criarMembro($parceiro, MembroEquipe::ADMINISTRADOR, 'admin@exemplo.com');
    }

    private function url($parceiro, string $caminho): string
    {
        return $this->urlDe($parceiro, '/equipe'.$caminho);
    }

    // ---------- acesso e permissões ----------

    public function test_visitante_nao_entra_no_painel_de_cursos(): void
    {
        $a = $this->criarParceiro('alfa');

        $this->get($this->url($a, '/cursos'))->assertRedirect();
    }

    public function test_suporte_nao_edita_cursos(): void
    {
        $a = $this->criarParceiro('alfa');
        $suporte = $this->criarMembro($a, MembroEquipe::SUPORTE, 'suporte@exemplo.com');

        $this->actingAs($suporte, 'equipe')->get($this->url($a, '/cursos'))->assertForbidden();
        $this->post($this->url($a, '/cursos'), ['titulo' => 'Meu curso'])->assertForbidden();
    }

    public function test_administrador_cria_curso_como_rascunho_com_slug_unico(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, '/cursos'), ['titulo' => 'Excel Básico'])->assertRedirect();
        $this->post($this->url($a, '/cursos'), ['titulo' => 'Excel Básico'])->assertRedirect();

        $cursos = $this->contexto($a, fn () => Curso::query()->orderBy('id')->get());
        $this->assertCount(2, $cursos);
        $this->assertSame(['excel-basico', 'excel-basico-2'], $cursos->pluck('slug')->all());
        $this->assertSame(Curso::RASCUNHO, $cursos[0]->estado);
    }

    public function test_titulo_curto_ou_vazio_e_recusado(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, '/cursos'), ['titulo' => ''])->assertSessionHasErrors('titulo');
        $this->post($this->url($a, '/cursos'), ['titulo' => 'ab'])->assertSessionHasErrors('titulo');
    }

    public function test_curso_de_outro_parceiro_da_404_no_painel(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $cursoB = $this->criarCurso($b, 'do-beta');
        $this->actingAs($this->admin($a), 'equipe');

        $this->get($this->url($a, "/cursos/{$cursoB->id}"))->assertNotFound();
        $this->post($this->url($a, "/cursos/{$cursoB->id}/publicar"))->assertNotFound();
    }

    public function test_instrutor_so_ve_e_edita_os_proprios_cursos(): void
    {
        $a = $this->criarParceiro('alfa');
        $ana = $this->criarMembro($a, MembroEquipe::INSTRUTOR, 'ana@exemplo.com');
        $bia = $this->criarMembro($a, MembroEquipe::INSTRUTOR, 'bia@exemplo.com');
        $minha = $this->criarCurso($a, 'da-ana');
        $dela = $this->criarCurso($a, 'da-bia');
        $this->contexto($a, fn () => Curso::query()->whereKey($minha->id)->update(['instrutor_id' => $ana->id]));
        $this->contexto($a, fn () => Curso::query()->whereKey($dela->id)->update(['instrutor_id' => $bia->id]));

        $this->actingAs($ana, 'equipe')->get($this->url($a, '/cursos'))
            ->assertOk()->assertSee('Curso DA-ANA')->assertDontSee('Curso DA-BIA');
        $this->get($this->url($a, "/cursos/{$minha->id}"))->assertOk();
        $this->get($this->url($a, "/cursos/{$dela->id}"))->assertForbidden();
        $this->post($this->url($a, "/cursos/{$dela->id}"), ['titulo' => 'Invadido'])->assertForbidden();
    }

    public function test_instrutor_sem_permissao_de_publicar_nao_publica(): void
    {
        $a = $this->criarParceiro('alfa');
        $ana = $this->criarMembro($a, MembroEquipe::INSTRUTOR, 'ana@exemplo.com', podePublicar: false);
        $curso = $this->criarCurso($a, 'da-ana', estado: Curso::RASCUNHO);
        $this->contexto($a, fn () => Curso::query()->whereKey($curso->id)->update(['instrutor_id' => $ana->id]));

        $this->actingAs($ana, 'equipe')->post($this->url($a, "/cursos/{$curso->id}/publicar"))->assertForbidden();

        $outra = $this->criarMembro($a, MembroEquipe::INSTRUTOR, 'outra@exemplo.com', podePublicar: true);
        $this->contexto($a, fn () => Curso::query()->whereKey($curso->id)->update(['instrutor_id' => $outra->id]));
        $this->actingAs($outra, 'equipe')->post($this->url($a, "/cursos/{$curso->id}/publicar"))->assertRedirect();
        $this->assertSame(Curso::PUBLICADO, $this->contexto($a, fn () => Curso::find($curso->id)->estado));
    }

    public function test_matriz_confere_o_dono_do_recurso(): void
    {
        $a = $this->criarParceiro('alfa');
        $ana = $this->criarMembro($a, MembroEquipe::INSTRUTOR, 'ana@exemplo.com', podePublicar: true);
        $admin = $this->admin($a);

        $this->assertTrue(Matriz::permiteSobre($ana, 'cursos.editar', $ana->id));
        $this->assertFalse(Matriz::permiteSobre($ana, 'cursos.editar', $ana->id + 99));
        $this->assertFalse(Matriz::permiteSobre($ana, 'cursos.editar', null));
        $this->assertTrue(Matriz::permiteSobre($admin, 'cursos.editar', null));
        $this->assertFalse(Matriz::permiteSobre($ana, 'equipe.gerenciar', $ana->id));
    }

    // ---------- publicação ----------

    public function test_nao_publica_curso_sem_aula_publicada(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'vazio', aulas: 0, estado: Curso::RASCUNHO);
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/cursos/{$curso->id}/publicar"))->assertSessionHasErrors('publicacao');
        $this->assertSame(Curso::RASCUNHO, $this->contexto($a, fn () => Curso::find($curso->id)->estado));
    }

    public function test_publica_com_aula_publicada_e_registra_a_data(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'ok', aulas: 1, estado: Curso::RASCUNHO);
        $this->contexto($a, fn () => Curso::query()->whereKey($curso->id)->update(['publicado_em' => null]));
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/cursos/{$curso->id}/publicar"))->assertSessionHasNoErrors();
        $depois = $this->contexto($a, fn () => Curso::find($curso->id));
        $this->assertSame(Curso::PUBLICADO, $depois->estado);
        $this->assertNotNull($depois->publicado_em);
    }

    public function test_arquivado_some_do_catalogo_mas_matriculado_continua_com_acesso(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'velho', aulas: 2);
        $matriculado = $this->criarAluno($a, 'com@exemplo.com');
        $outro = $this->criarAluno($a, 'sem@exemplo.com');
        $this->matricular($a, $matriculado, $curso);
        $aula = $this->aulasDe($a, $curso)->first();

        $this->actingAs($this->admin($a), 'equipe')->post($this->url($a, "/cursos/{$curso->id}/arquivar"));
        $this->assertSame(Curso::ARQUIVADO, $this->contexto($a, fn () => Curso::find($curso->id)->estado));

        $this->actingAs($matriculado, 'aluno')->get($this->urlDe($a, '/cursos/velho'))->assertOk();
        $this->get($this->urlDe($a, "/aulas/{$aula->id}"))->assertOk();
        $this->actingAs($outro, 'aluno')->get($this->urlDe($a, '/cursos/velho'))->assertNotFound();
        $this->get($this->urlDe($a))->assertDontSee('Curso VELHO');
    }

    public function test_curso_com_matricula_nao_volta_a_rascunho(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'em-uso');
        $this->matricular($a, $this->criarAluno($a), $curso);
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/cursos/{$curso->id}/rascunho"))->assertSessionHasErrors('publicacao');
        $this->assertSame(Curso::PUBLICADO, $this->contexto($a, fn () => Curso::find($curso->id)->estado));
    }

    public function test_previa_mostra_curso_em_rascunho_para_a_equipe(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'rascunho', estado: Curso::RASCUNHO);
        $this->actingAs($this->admin($a), 'equipe');

        $this->get($this->url($a, "/cursos/{$curso->id}/previa"))->assertOk()->assertSee('Prévia')->assertSee('Curso RASCUNHO');
    }

    // ---------- módulos e aulas ----------

    public function test_modulos_sao_criados_renomeados_ordenados_e_so_apagam_vazios(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c', aulas: 1);
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/cursos/{$curso->id}/modulos"), ['titulo' => 'Segundo']);
        $modulos = $this->contexto($a, fn () => Modulo::query()->orderBy('ordem')->get());
        $this->assertSame([1, 2], $modulos->pluck('ordem')->all());

        $this->post($this->url($a, "/modulos/{$modulos[1]->id}/mover"), ['sentido' => 'sobe']);
        $depois = $this->contexto($a, fn () => Modulo::query()->orderBy('ordem')->pluck('titulo')->all());
        $this->assertSame(['Segundo', 'Módulo 1'], $depois);

        $this->post($this->url($a, "/modulos/{$modulos[1]->id}"), ['titulo' => 'Renomeado']);
        $this->assertSame('Renomeado', $this->contexto($a, fn () => Modulo::find($modulos[1]->id)->titulo));

        $this->post($this->url($a, "/modulos/{$modulos[0]->id}/apagar"))->assertSessionHasErrors('conteudo');
        $this->post($this->url($a, "/modulos/{$modulos[1]->id}/apagar"));
        $this->assertNull($this->contexto($a, fn () => Modulo::find($modulos[1]->id)));
    }

    public function test_mover_no_limite_nao_quebra_a_ordem(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c', aulas: 3);
        $aulas = $this->aulasDe($a, $curso);
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/aulas/{$aulas[0]->id}/mover"), ['sentido' => 'sobe']);
        $this->post($this->url($a, "/aulas/{$aulas[2]->id}/mover"), ['sentido' => 'desce']);
        $this->post($this->url($a, "/aulas/{$aulas[2]->id}/mover"), ['sentido' => 'diagonal'])->assertSessionHasErrors('sentido');

        $this->assertSame([1, 2, 3], $this->contexto($a, fn () => Aula::query()->orderBy('ordem')->pluck('ordem')->all()));
        $this->assertSame($aulas->pluck('id')->all(), $this->contexto($a, fn () => Aula::query()->orderBy('ordem')->pluck('id')->all()));
    }

    public function test_aula_nova_nasce_em_rascunho_e_aceita_endereco_completo_do_youtube(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c', aulas: 0);
        $modulo = $this->contexto($a, fn () => Modulo::create(['curso_id' => $curso->id, 'titulo' => 'M', 'ordem' => 1]));
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/modulos/{$modulo->id}/aulas"), ['titulo' => 'Aula <b>um</b>']);
        $aula = $this->contexto($a, fn () => Aula::query()->first());
        $this->assertFalse($aula->publicada);

        $this->post($this->url($a, "/aulas/{$aula->id}"), [
            'titulo' => 'Aula um', 'tipo' => 'video', 'video_provedor' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s', 'duracao_min' => 12, 'liberacao_tipo' => 'imediata',
        ])->assertSessionHasNoErrors();

        $aula = $this->contexto($a, fn () => Aula::find($aula->id));
        $this->assertSame('dQw4w9WgXcQ', $aula->video_ref);
        $this->assertSame(720, $aula->duracao_seg);
    }

    public function test_endereco_de_video_invalido_e_recusado(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c', aulas: 1);
        $aula = $this->aulasDe($a, $curso)->first();
        $this->actingAs($this->admin($a), 'equipe');

        foreach (['javascript:alert(1)', 'https://evil.example/watch?v=abc', '"><script>x</script>'] as $url) {
            $this->post($this->url($a, "/aulas/{$aula->id}"), [
                'titulo' => 'A', 'tipo' => 'video', 'video_provedor' => 'youtube', 'video_url' => $url, 'liberacao_tipo' => 'imediata',
            ])->assertSessionHasErrors('video_url');
        }
    }

    public function test_extrair_ref_de_varios_formatos(): void
    {
        $this->assertSame('dQw4w9WgXcQ', Video::extrairRef('youtube', 'https://youtu.be/dQw4w9WgXcQ?si=abc'));
        $this->assertSame('dQw4w9WgXcQ', Video::extrairRef('youtube', 'https://www.youtube.com/embed/dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', Video::extrairRef('youtube', 'dQw4w9WgXcQ'));
        $this->assertSame('123456789', Video::extrairRef('vimeo', 'https://vimeo.com/123456789'));
        $this->assertSame('123456789', Video::extrairRef('vimeo', 'https://player.vimeo.com/video/123456789?h=abc'));
        $this->assertNull(Video::extrairRef('youtube', 'https://vimeo.com/123456789'));
        $this->assertNull(Video::extrairRef(null, 'dQw4w9WgXcQ'));
    }

    public function test_liberacao_por_data_guarda_em_utc(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c', aulas: 1);
        $aula = $this->aulasDe($a, $curso)->first();
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/aulas/{$aula->id}"), [
            'titulo' => 'A', 'tipo' => 'texto', 'conteudo_texto' => 'Olá', 'liberacao_tipo' => 'data', 'liberacao_data' => '2030-05-10T09:00',
        ])->assertSessionHasNoErrors();

        // 09:00 em São Paulo (UTC-3) são 12:00 UTC.
        $salva = $this->contexto($a, fn () => Aula::query()->whereKey($aula->id)->value('liberacao_data'));
        $this->assertStringContainsString('2030-05-10 12:00:00', (string) $salva);

        $this->post($this->url($a, "/aulas/{$aula->id}"), [
            'titulo' => 'A', 'tipo' => 'texto', 'liberacao_tipo' => 'data',
        ])->assertSessionHasErrors('liberacao_data');
    }

    public function test_liberacao_apos_aula_so_aceita_aula_do_mesmo_curso(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $curso = $this->criarCurso($a, 'c', aulas: 2);
        $outroCurso = $this->criarCurso($a, 'd', aulas: 1);
        $alheia = $this->aulasDe($b, $this->criarCurso($b, 'e', aulas: 1))->first();
        $aulas = $this->aulasDe($a, $curso);
        $this->actingAs($this->admin($a), 'equipe');

        $base = ['titulo' => 'A', 'tipo' => 'texto', 'liberacao_tipo' => 'apos_aula'];

        $this->post($this->url($a, "/aulas/{$aulas[1]->id}"), $base + ['liberacao_aula_id' => $this->aulasDe($a, $outroCurso)->first()->id])->assertSessionHasErrors('liberacao_aula_id');
        $this->post($this->url($a, "/aulas/{$aulas[1]->id}"), $base + ['liberacao_aula_id' => $alheia->id])->assertSessionHasErrors('liberacao_aula_id');
        $this->post($this->url($a, "/aulas/{$aulas[1]->id}"), $base + ['liberacao_aula_id' => $aulas[1]->id])->assertSessionHasErrors('liberacao_aula_id');
        $this->post($this->url($a, "/aulas/{$aulas[1]->id}"), $base + ['liberacao_aula_id' => $aulas[0]->id])->assertSessionHasNoErrors();
    }

    public function test_aula_de_outro_parceiro_da_404_no_painel(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $alheia = $this->aulasDe($b, $this->criarCurso($b, 'e', aulas: 1))->first();
        $this->actingAs($this->admin($a), 'equipe');

        $this->get($this->url($a, "/aulas/{$alheia->id}"))->assertNotFound();
        $this->post($this->url($a, "/aulas/{$alheia->id}/publicar"), ['publicada' => 1])->assertNotFound();
        $this->post($this->url($a, "/aulas/{$alheia->id}/apagar"))->assertNotFound();
    }

    public function test_aula_com_progresso_de_aluno_nao_e_apagada(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c', aulas: 2);
        $aluno = $this->criarAluno($a);
        $matricula = $this->matricular($a, $aluno, $curso);
        $aulas = $this->aulasDe($a, $curso);
        $this->contexto($a, fn () => ProgressoAula::create(['matricula_id' => $matricula->id, 'aula_id' => $aulas[0]->id, 'concluida' => true]));
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/aulas/{$aulas[0]->id}/apagar"))->assertSessionHasErrors('conteudo');
        $this->assertNotNull($this->contexto($a, fn () => Aula::find($aulas[0]->id)));

        $this->post($this->url($a, "/aulas/{$aulas[1]->id}/apagar"))->assertSessionHasNoErrors();
        $this->assertNull($this->contexto($a, fn () => Aula::find($aulas[1]->id)));
    }

    public function test_aula_da_qual_outra_depende_nao_e_apagada(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c', aulas: 2);
        $aulas = $this->aulasDe($a, $curso);
        $this->contexto($a, fn () => $aulas[1]->update(['liberacao_tipo' => 'apos_aula', 'liberacao_aula_id' => $aulas[0]->id]));
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/aulas/{$aulas[0]->id}/apagar"))->assertSessionHasErrors('conteudo');
    }

    public function test_titulo_com_html_aparece_escapado_no_painel(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c', aulas: 1);
        $aula = $this->aulasDe($a, $curso)->first();
        $this->contexto($a, fn () => $aula->update(['titulo' => '<script>alert(1)</script>']));
        $this->actingAs($this->admin($a), 'equipe');

        $this->get($this->url($a, "/cursos/{$curso->id}?aba=conteudo"))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    // ---------- arquivos ----------

    public function test_capa_valida_e_guardada_como_publica_e_servida(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c');
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/cursos/{$curso->id}/capa"), ['capa' => UploadedFile::fake()->image('capa.jpg', 400, 600)])->assertSessionHasNoErrors();

        $arquivo = $this->contexto($a, fn () => Arquivo::query()->first());
        $this->assertTrue($arquivo->publico);
        $this->assertSame($arquivo->id, $this->contexto($a, fn () => Curso::find($curso->id)->capa_arquivo_id));
        Storage::disk('local')->assertExists($arquivo->caminho);
        $this->assertStringStartsWith("parceiros/{$a->id}/capas/", $arquivo->caminho);

        $this->get($this->urlDe($a, "/midia/{$arquivo->uuid}"))->assertOk();
        // o mesmo endereço no domínio de outro parceiro não entrega
        $b = $this->criarParceiro('beta');
        $this->get($this->urlDe($b, "/midia/{$arquivo->uuid}"))->assertNotFound();
    }

    public function test_capa_invalida_e_recusada(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c');
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/cursos/{$curso->id}/capa"), ['capa' => UploadedFile::fake()->create('script.php', 10, 'application/x-php')])->assertSessionHasErrors('capa');
        $this->post($this->url($a, "/cursos/{$curso->id}/capa"), ['capa' => UploadedFile::fake()->createWithContent('falsa.jpg', '<?php echo 1;')])->assertSessionHasErrors('capa');
        $this->post($this->url($a, "/cursos/{$curso->id}/capa"), ['capa' => UploadedFile::fake()->image('pequena.png', 50, 50)])->assertSessionHasErrors('capa');
        $this->post($this->url($a, "/cursos/{$curso->id}/capa"), ['capa' => UploadedFile::fake()->image('grande.jpg', 800, 1200)->size(3000)])->assertSessionHasErrors('capa');
        $this->assertSame(0, $this->contexto($a, fn () => Arquivo::query()->count()));
    }

    public function test_material_enviado_e_baixado_pelo_aluno_matriculado_e_removido_apaga_o_arquivo(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c', aulas: 1);
        $aula = $this->aulasDe($a, $curso)->first();
        $aluno = $this->criarAluno($a);
        $this->matricular($a, $aluno, $curso);
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/aulas/{$aula->id}/materiais"), [
            'arquivo' => UploadedFile::fake()->createWithContent('../../Apostila Final.pdf', "%PDF-1.4\nconteudo"), 'titulo' => 'Apostila',
        ])->assertSessionHasNoErrors();

        $material = $this->contexto($a, fn () => Material::query()->with('arquivo')->first());
        $this->assertSame('Apostila', $material->titulo);
        $this->assertFalse($material->arquivo->publico);
        $this->assertStringNotContainsString('..', $material->arquivo->nome_original);
        $this->assertStringNotContainsString('/', $material->arquivo->nome_original);
        $this->assertStringNotContainsString('Apostila Final', $material->arquivo->caminho);

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, "/materiais/{$material->id}"))->assertOk();

        $this->actingAs($this->admin($a), 'equipe')->post($this->url($a, "/materiais/{$material->id}/apagar"));
        Storage::disk('local')->assertMissing($material->arquivo->caminho);
        $this->assertSame(0, $this->contexto($a, fn () => Material::query()->count()));
    }

    public function test_material_com_tipo_proibido_e_recusado(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'c', aulas: 1);
        $aula = $this->aulasDe($a, $curso)->first();
        $this->actingAs($this->admin($a), 'equipe');

        foreach (['virus.php', 'script.html', 'app.exe', 'imagem.svg'] as $nome) {
            $this->post($this->url($a, "/aulas/{$aula->id}/materiais"), ['arquivo' => UploadedFile::fake()->create($nome, 10)])->assertSessionHasErrors('arquivo');
        }
        $this->assertSame(0, $this->contexto($a, fn () => Material::query()->count()));
    }

    // ---------- categorias e painel ----------

    public function test_categoria_criada_gera_slug_unico_e_instrutor_nao_gerencia(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->actingAs($this->admin($a), 'equipe');
        $this->post($this->url($a, '/categorias'), ['nome' => 'Tecnologia']);
        $this->post($this->url($a, '/categorias'), ['nome' => 'Tecnologia']);
        $this->assertSame(['tecnologia', 'tecnologia-2'], $this->contexto($a, fn () => Categoria::query()->orderBy('id')->pluck('slug')->all()));

        $ana = $this->criarMembro($a, MembroEquipe::INSTRUTOR, 'ana@exemplo.com');
        $this->actingAs($ana, 'equipe')->get($this->url($a, '/categorias'))->assertForbidden();
    }

    public function test_curso_so_recebe_categorias_do_proprio_parceiro(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $alheia = $this->contexto($b, fn () => Categoria::create(['nome' => 'Alheia', 'slug' => 'alheia']));
        $minha = $this->contexto($a, fn () => Categoria::create(['nome' => 'Minha', 'slug' => 'minha']));
        $curso = $this->criarCurso($a, 'c');
        $this->actingAs($this->admin($a), 'equipe');

        $this->post($this->url($a, "/cursos/{$curso->id}"), ['aba' => 'info', 'titulo' => 'Curso C', 'categorias' => [$alheia->id, $minha->id]])->assertSessionHasNoErrors();

        $this->assertSame([$minha->id], $this->contexto($a, fn () => Curso::find($curso->id)->categorias()->pluck('categorias.id')->all()));
    }

    public function test_painel_inicial_mostra_numeros(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarCurso($a, 'um');
        $this->criarCurso($a, 'dois', estado: Curso::RASCUNHO);
        $this->actingAs($this->admin($a), 'equipe');

        $this->get($this->url($a, ''))->assertOk()->assertSee('cursos publicados')->assertSee('em rascunho');
    }
}
