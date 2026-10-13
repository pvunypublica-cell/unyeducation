<?php

namespace Tests\Feature\Ambiente;

use App\Ambiente\Busca;
use App\Ambiente\Contraste;
use App\Ambiente\Tema;
use App\Ambiente\Video;
use App\Models\Aula;
use App\Models\ConfiguracaoVisual;
use App\Models\TokenAcesso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CriaCatalogo;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

class TemaEBuscaTest extends TestCase
{
    use CriaCatalogo, CriaParceiros, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_tema_padrao_tem_contraste_suficiente(): void
    {
        $this->assertGreaterThanOrEqual(Tema::CONTRASTE_MINIMO, Contraste::razao(Tema::PADRAO['cor_principal'], '#0E0E14'));
    }

    public function test_cor_de_baixo_contraste_ou_invalida_e_recusada(): void
    {
        $tema = new Tema(['cor_principal' => '#15151C', 'cor_secundaria' => 'red; background:url(x)']);

        $this->assertSame(strtoupper(Tema::PADRAO['cor_principal']), $tema->token('cor_principal'));
        $this->assertSame(Tema::PADRAO['cor_secundaria'], $tema->token('cor_secundaria'));
        $this->assertCount(2, $tema->avisos());
    }

    public function test_css_do_tema_nunca_carrega_texto_livre(): void
    {
        $tema = new Tema([
            'cor_principal' => '#FF6600', 'fonte_titulo' => '</style><script>alert(1)</script>',
            'raio_borda' => '1px;background:url(//x)',
        ]);
        $css = $tema->css();

        $this->assertStringContainsString('--cor-principal:#FF6600', $css);
        $this->assertStringNotContainsString('<', $css);
        $this->assertStringNotContainsString('url(', $css);
    }

    public function test_tema_publicado_do_parceiro_chega_a_pagina_e_nao_vaza_para_outro(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->contexto($a, fn () => ConfiguracaoVisual::create([
            'tipo' => 'tema', 'estado' => ConfiguracaoVisual::PUBLICADO, 'conteudo_json' => ['cor_principal' => '#00C2A8'],
        ]));
        $alunoA = $this->criarAluno($a);
        $alunoB = $this->criarAluno($b);

        $this->actingAs($alunoA, 'aluno')->get($this->urlDe($a))->assertSee('--cor-principal:#00C2A8', false);
        $this->actingAs($alunoB, 'aluno')->get($this->urlDe($b))->assertDontSee('#00C2A8', false);
    }

    public function test_video_so_aceita_provedores_conhecidos_e_ids_no_formato(): void
    {
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', Video::urlIncorporada('youtube', 'dQw4w9WgXcQ'));
        $this->assertSame('https://player.vimeo.com/video/123456789', Video::urlIncorporada('vimeo', '123456789'));
        $this->assertNull(Video::urlIncorporada('youtube', 'x" onload="alert(1)'));
        $this->assertNull(Video::urlIncorporada('youtube', '../../evil'));
        $this->assertNull(Video::urlIncorporada('vimeo', 'abc'));
        $this->assertNull(Video::urlIncorporada('outro', 'https://evil.example'));
        $this->assertNull(Video::urlIncorporada(null, null));
    }

    public function test_busca_acha_curso_proprio_e_nunca_de_outro_parceiro(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->criarCurso($a, 'planilhas');
        $this->criarCurso($b, 'planilhas-beta');
        $aluno = $this->criarAluno($a);

        $resposta = $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, '/busca?q=planilhas'));

        $resposta->assertOk()->assertSee('Curso PLANILHAS')->assertDontSee('PLANILHAS-BETA');
    }

    public function test_busca_com_caracteres_especiais_nao_quebra_nem_casa_tudo(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarCurso($a, 'normal');
        $aluno = $this->criarAluno($a);
        $this->actingAs($aluno, 'aluno');

        foreach (['%', '_', '%%', "'; DROP TABLE cursos;--", '<script>alert(1)</script>', 'a\\b', "D'Ávila"] as $termo) {
            $resposta = $this->get($this->urlDe($a, '/busca?q='.urlencode($termo)));
            $resposta->assertOk();
            $resposta->assertDontSee('Curso NORMAL');
            $resposta->assertDontSee('<script>alert(1)</script>', false);
        }
    }

    public function test_busca_curta_demais_nao_consulta(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarCurso($a, 'normal');
        $aluno = $this->criarAluno($a);

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, '/busca?q=n'))->assertOk()->assertSee('ao menos');
        $this->assertSame('', Busca::normalizar("   \n  "));
        $this->assertSame(Busca::MAXIMO, mb_strlen(Busca::normalizar(str_repeat('a', 500))));
    }

    public function test_busca_de_aulas_so_dentro_de_cursos_matriculados(): void
    {
        $a = $this->criarParceiro('alfa');
        $meu = $this->criarCurso($a, 'meu', aulas: 1);
        $this->criarCurso($a, 'outro', aulas: 1);
        $aluno = $this->criarAluno($a);
        $this->matricular($a, $aluno, $meu);

        $resposta = $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, '/busca?q=Aula'));

        $resposta->assertSee('Aula 1 de meu')->assertDontSee('Aula 1 de outro');
    }

    public function test_aula_nao_publicada_nao_abre_nem_conta(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'gratis', aulas: 2);
        $aluno = $this->criarAluno($a);
        $this->matricular($a, $aluno, $curso);
        $oculta = $this->aulasDe($a, $curso)->last();
        $this->contexto($a, fn () => Aula::query()->whereKey($oculta->id)->update(['publicada' => false]));

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a, "/aulas/{$oculta->id}"))->assertNotFound();
    }

    public function test_usar_token_nao_altera_a_data_de_expiracao(): void
    {
        $a = $this->criarParceiro('alfa');
        $aluno = $this->criarAluno($a);
        $expira = now()->addMinutes(15)->startOfSecond();

        $token = $this->contexto($a, fn () => TokenAcesso::create([
            'aluno_id' => $aluno->id,
            'token_hash' => hash('sha256', 'x'), 'expira_em' => $expira,
        ]));

        $this->contexto($a, fn () => TokenAcesso::query()->whereKey($token->id)->first()->update(['usado_em' => now()]));

        $this->assertTrue($this->contexto($a, fn () => TokenAcesso::query()->whereKey($token->id)->first()->expira_em->equalTo($expira)));
    }
}
