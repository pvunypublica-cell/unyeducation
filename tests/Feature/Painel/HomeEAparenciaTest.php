<?php

namespace Tests\Feature\Painel;

use App\Ambiente\Personalizacao;
use App\Models\Arquivo;
use App\Models\Categoria;
use App\Models\ConfiguracaoVisual;
use App\Models\Curso;
use App\Models\MembroEquipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CriaCatalogo;
use Tests\Concerns\CriaParceiros;
use Tests\TestCase;

class HomeEAparenciaTest extends TestCase
{
    use CriaCatalogo, CriaParceiros, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function url($parceiro, string $caminho): string
    {
        return $this->urlDe($parceiro, '/equipe'.$caminho);
    }

    private function entrar($parceiro): MembroEquipe
    {
        $admin = $this->criarMembro($parceiro, MembroEquipe::ADMINISTRADOR, 'admin@exemplo.com');
        $this->actingAs($admin, 'equipe');

        return $admin;
    }

    /** Começa de uma home sem as vitrines padrão, para o teste enxergar só o que cria. */
    private function homeVazia($parceiro): void
    {
        $this->contexto($parceiro, fn () => ConfiguracaoVisual::create([
            'tipo' => 'home', 'estado' => ConfiguracaoVisual::RASCUNHO, 'conteudo_json' => ['banner' => [], 'vitrines' => []],
        ]));
    }

    private function tema(array $extra = []): array
    {
        return $extra + [
            'cor_principal' => '#00C2A8', 'cor_secundaria' => '#F5B942', 'fonte_titulo' => 'sora',
            'fonte_texto' => 'inter', 'raio_borda' => 'suave',
        ];
    }

    // ---------- permissões ----------

    public function test_so_administrador_edita_home_e_aparencia(): void
    {
        $a = $this->criarParceiro('alfa');
        $instrutor = $this->criarMembro($a, MembroEquipe::INSTRUTOR, 'ins@exemplo.com');
        $suporte = $this->criarMembro($a, MembroEquipe::SUPORTE, 'sup@exemplo.com');

        foreach ([$instrutor, $suporte] as $membro) {
            $this->actingAs($membro, 'equipe');
            $this->get($this->url($a, '/home'))->assertForbidden();
            $this->get($this->url($a, '/aparencia'))->assertForbidden();
            $this->get($this->url($a, '/previa'))->assertForbidden();
            $this->post($this->url($a, '/aparencia'), $this->tema())->assertForbidden();
            $this->post($this->url($a, '/home/publicar'))->assertForbidden();
        }
    }

    public function test_aluno_e_visitante_nao_abrem_o_editor(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->get($this->url($a, '/home'))->assertRedirect();

        $this->actingAs($this->criarAluno($a), 'aluno')->get($this->url($a, '/previa'))->assertRedirect();
    }

    // ---------- aparência: rascunho e publicação ----------

    public function test_aparencia_so_chega_ao_aluno_depois_de_publicar(): void
    {
        $a = $this->criarParceiro('alfa');
        $aluno = $this->criarAluno($a);
        $this->entrar($a);

        $this->post($this->url($a, '/aparencia'), $this->tema(['nome_ambiente' => 'Escola Nova']))->assertSessionHasNoErrors();

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a))->assertDontSee('#00C2A8', false)->assertDontSee('Escola Nova');

        $this->actingAs($this->criarMembroDeNovo($a), 'equipe')->post($this->url($a, '/aparencia/publicar'))->assertRedirect();

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a))->assertSee('--cor-principal:#00C2A8', false)->assertSee('Escola Nova');
        $this->assertSame(1, $this->contexto($a, fn () => Personalizacao::versaoPublicada('tema')));
    }

    private function criarMembroDeNovo($parceiro): MembroEquipe
    {
        return $this->contexto($parceiro, fn () => MembroEquipe::query()->where('email', 'admin@exemplo.com')->firstOrFail());
    }

    public function test_publicar_de_novo_aumenta_a_versao_e_descartar_volta_ao_publicado(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->entrar($a);

        $this->post($this->url($a, '/aparencia'), $this->tema());
        $this->post($this->url($a, '/aparencia/publicar'));
        $this->post($this->url($a, '/aparencia'), $this->tema(['cor_principal' => '#FF6600']));
        $this->assertTrue($this->contexto($a, fn () => Personalizacao::pendente('tema')));

        $this->post($this->url($a, '/aparencia/descartar'));
        $this->assertFalse($this->contexto($a, fn () => Personalizacao::pendente('tema')));
        $this->assertSame('#00C2A8', $this->contexto($a, fn () => Personalizacao::publicado('tema'))['cor_principal']);

        $this->post($this->url($a, '/aparencia'), $this->tema(['cor_principal' => '#FF6600']));
        $this->post($this->url($a, '/aparencia/publicar'));
        $this->assertSame(2, $this->contexto($a, fn () => Personalizacao::versaoPublicada('tema')));
    }

    public function test_publicar_sem_rascunho_nao_cria_nada(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->entrar($a);

        $this->post($this->url($a, '/aparencia/publicar'))->assertRedirect();
        $this->assertSame(0, $this->contexto($a, fn () => ConfiguracaoVisual::query()->count()));
    }

    public function test_cores_ruins_ou_invalidas_sao_recusadas(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->entrar($a);

        $this->post($this->url($a, '/aparencia'), $this->tema(['cor_principal' => '#15151C']))->assertSessionHasErrors('cor_principal');
        $this->post($this->url($a, '/aparencia'), $this->tema(['cor_secundaria' => '#101010']))->assertSessionHasErrors('cor_secundaria');
        $this->post($this->url($a, '/aparencia'), $this->tema(['cor_principal' => 'red']))->assertSessionHasErrors('cor_principal');
        $this->post($this->url($a, '/aparencia'), $this->tema(['cor_principal' => '#FFF; background:url(x)']))->assertSessionHasErrors('cor_principal');
        $this->post($this->url($a, '/aparencia'), $this->tema(['fonte_titulo' => 'Comic Sans']))->assertSessionHasErrors('fonte_titulo');
        $this->post($this->url($a, '/aparencia'), $this->tema(['raio_borda' => '50px']))->assertSessionHasErrors('raio_borda');
        $this->assertSame(0, $this->contexto($a, fn () => ConfiguracaoVisual::query()->count()));
    }

    public function test_nome_do_ambiente_com_html_aparece_escapado(): void
    {
        $a = $this->criarParceiro('alfa');
        $aluno = $this->criarAluno($a);
        $this->entrar($a);
        $this->post($this->url($a, '/aparencia'), $this->tema(['nome_ambiente' => '<script>alert(1)</script>']));
        $this->post($this->url($a, '/aparencia/publicar'));

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_logo_e_enviada_publicada_e_removida(): void
    {
        $a = $this->criarParceiro('alfa');
        $aluno = $this->criarAluno($a);
        $this->entrar($a);

        $this->post($this->url($a, '/aparencia'), $this->tema(['logo' => UploadedFile::fake()->image('logo.png', 300, 100)]))->assertSessionHasNoErrors();
        $this->post($this->url($a, '/aparencia/publicar'));

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a))->assertSee('class="marca-logo"', false);

        $this->actingAs($this->criarMembroDeNovo($a), 'equipe')->post($this->url($a, '/aparencia'), $this->tema(['remover_logo' => 1]));
        $this->post($this->url($a, '/aparencia/publicar'));
        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a))->assertDontSee('class="marca-logo"', false);
    }

    public function test_logo_invalida_e_recusada(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->entrar($a);

        $this->post($this->url($a, '/aparencia'), $this->tema(['logo' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml')]))->assertSessionHasErrors('logo');
        $this->post($this->url($a, '/aparencia'), $this->tema(['logo' => UploadedFile::fake()->image('minuscula.png', 20, 20)]))->assertSessionHasErrors('logo');
    }

    public function test_logo_de_outro_parceiro_nao_aparece_mesmo_com_id_forjado(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->criarCurso($b, 'x');
        $arquivoB = $this->contexto($b, fn () => Arquivo::create([
            'caminho' => 'parceiros/x/logo.png', 'nome_original' => 'l.png', 'mime' => 'image/png', 'tamanho_bytes' => 1,
            'hash_sha256' => str_repeat('a', 64), 'publico' => true,
        ]));
        $this->contexto($a, fn () => ConfiguracaoVisual::create([
            'tipo' => 'tema', 'estado' => ConfiguracaoVisual::PUBLICADO, 'conteudo_json' => ['logo_arquivo_id' => $arquivoB->id],
        ]));

        $this->actingAs($this->criarAluno($a), 'aluno')->get($this->urlDe($a))->assertDontSee('class="marca-logo"', false);
    }

    // ---------- home: banner ----------

    public function test_banner_valida_destino_do_botao(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $cursoB = $this->criarCurso($b, 'do-beta');
        $cursoRascunho = $this->criarCurso($a, 'rascunho', estado: Curso::RASCUNHO);
        $this->entrar($a);

        $base = ['titulo' => 'Oi', 'botao_rotulo' => 'Ir'];

        foreach (['javascript:alert(1)', 'http://inseguro.example', 'ftp://x.example', 'texto solto', 'https://'] as $url) {
            $this->post($this->url($a, '/home/banner'), $base + ['botao_tipo' => 'url', 'botao_url' => $url])->assertSessionHasErrors('botao_url');
        }
        $this->post($this->url($a, '/home/banner'), $base + ['botao_tipo' => 'url', 'botao_url' => 'https://exemplo.com/oferta'])->assertSessionHasNoErrors();

        $this->post($this->url($a, '/home/banner'), $base + ['botao_tipo' => 'curso', 'botao_curso_id' => $cursoB->id])->assertSessionHasErrors('botao_curso_id');
        $this->post($this->url($a, '/home/banner'), $base + ['botao_tipo' => 'curso', 'botao_curso_id' => $cursoRascunho->id])->assertSessionHasErrors('botao_curso_id');
    }

    public function test_banner_publicado_aparece_com_titulo_botao_e_imagem(): void
    {
        $a = $this->criarParceiro('alfa');
        $curso = $this->criarCurso($a, 'oferta');
        $aluno = $this->criarAluno($a);
        $this->entrar($a);

        $this->post($this->url($a, '/home/banner'), [
            'titulo' => 'Promoção de outubro', 'texto' => 'Aproveite', 'botao_rotulo' => 'Ver curso',
            'botao_tipo' => 'curso', 'botao_curso_id' => $curso->id,
            'imagem_desktop' => UploadedFile::fake()->image('banner.jpg', 1600, 500),
        ])->assertSessionHasNoErrors();
        $this->post($this->url($a, '/home/publicar'));

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a))
            ->assertSee('Promoção de outubro')->assertSee('Ver curso')->assertSee('/cursos/oferta', false)->assertSee('banner-img', false);
    }

    public function test_banner_com_imagem_invalida_ou_pequena_e_recusado(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->entrar($a);
        $base = ['botao_tipo' => 'busca'];

        $this->post($this->url($a, '/home/banner'), $base + ['imagem_desktop' => UploadedFile::fake()->image('p.jpg', 100, 100)])->assertSessionHasErrors('imagem_desktop');
        $this->post($this->url($a, '/home/banner'), $base + ['imagem_desktop' => UploadedFile::fake()->create('x.php', 10)])->assertSessionHasErrors('imagem_desktop');
        $this->post($this->url($a, '/home/banner'), $base + ['imagem_mobile' => UploadedFile::fake()->image('p.jpg', 100, 100)])->assertSessionHasErrors('imagem_mobile');
    }

    // ---------- home: vitrines ----------

    public function test_home_so_muda_para_o_aluno_depois_de_publicar_e_previa_mostra_o_rascunho(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarCurso($a, 'um');
        $aluno = $this->criarAluno($a);
        $this->entrar($a);

        $this->post($this->url($a, '/home/banner'), ['titulo' => 'Banner Novo', 'botao_tipo' => 'busca']);
        $this->post($this->url($a, '/home/vitrines'), ['titulo' => 'Minha Vitrine Nova', 'tipo' => 'recentes']);

        $this->get($this->url($a, '/previa'))->assertOk()->assertSee('Banner Novo')->assertSee('Minha Vitrine Nova')->assertSee('Prévia');

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a))->assertDontSee('Banner Novo')->assertDontSee('Minha Vitrine Nova');

        $this->actingAs($this->criarMembroDeNovo($a), 'equipe')->post($this->url($a, '/home/publicar'));
        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a))->assertSee('Banner Novo')->assertSee('Minha Vitrine Nova');
    }

    public function test_vitrine_manual_respeita_a_ordem_e_ignora_curso_de_outro_parceiro_e_rascunho(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $c1 = $this->criarCurso($a, 'primeiro');
        $c2 = $this->criarCurso($a, 'segundo');
        $rascunho = $this->criarCurso($a, 'oculto', estado: Curso::RASCUNHO);
        $alheio = $this->criarCurso($b, 'alheio');
        $aluno = $this->criarAluno($a);
        $this->entrar($a);
        $this->homeVazia($a);

        $this->post($this->url($a, '/home/vitrines'), ['titulo' => 'Escolhidos', 'tipo' => 'manual']);
        $id = $this->contexto($a, fn () => Personalizacao::rascunho('home')['vitrines'][0]['id']);
        $this->post($this->url($a, "/home/vitrines/{$id}"), [
            'titulo' => 'Escolhidos', 'tipo' => 'manual', 'ativa' => 1, 'limite' => 12,
            'cursos' => [$c2->id, $rascunho->id, $alheio->id, $c1->id],
        ])->assertSessionHasNoErrors();
        $this->post($this->url($a, '/home/publicar'));

        $salvos = $this->contexto($a, fn () => Personalizacao::publicado('home')['vitrines'][0]['cursos']);
        $this->assertSame([$c2->id, $c1->id], $salvos);

        $html = $this->actingAs($aluno, 'aluno')->get($this->urlDe($a))->assertOk()->getContent();
        $this->assertStringContainsString('Curso SEGUNDO', $html);
        $this->assertLessThan(strpos($html, 'Curso PRIMEIRO'), strpos($html, 'Curso SEGUNDO'));
        $this->assertStringNotContainsString('Curso OCULTO', $html);
        $this->assertStringNotContainsString('Curso ALHEIO', $html);
    }

    public function test_vitrine_desativada_nao_aparece(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->criarCurso($a, 'um');
        $aluno = $this->criarAluno($a);
        $this->entrar($a);
        $this->homeVazia($a);
        $this->post($this->url($a, '/home/vitrines'), ['titulo' => 'Escondida Aqui', 'tipo' => 'recentes']);
        $id = $this->contexto($a, fn () => Personalizacao::rascunho('home')['vitrines'][0]['id']);

        $this->post($this->url($a, "/home/vitrines/{$id}"), ['titulo' => 'Escondida Aqui', 'tipo' => 'recentes', 'limite' => 5]);
        $this->post($this->url($a, '/home/publicar'));

        $this->actingAs($aluno, 'aluno')->get($this->urlDe($a))->assertDontSee('Escondida Aqui');
    }

    public function test_vitrine_de_categoria_exige_categoria_existente_do_parceiro(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->contexto($b, fn () => Categoria::create(['nome' => 'Alheia', 'slug' => 'alheia']));
        $this->contexto($a, fn () => Categoria::create(['nome' => 'Minha', 'slug' => 'minha']));
        $this->entrar($a);
        $this->homeVazia($a);
        $this->post($this->url($a, '/home/vitrines'), ['titulo' => 'Por categoria', 'tipo' => 'categoria']);
        $id = $this->contexto($a, fn () => Personalizacao::rascunho('home')['vitrines'][0]['id']);

        $this->post($this->url($a, "/home/vitrines/{$id}"), ['titulo' => 'x', 'tipo' => 'categoria', 'categoria' => 'alheia', 'ativa' => 1])->assertSessionHasErrors('vitrine-'.$id);
        $this->post($this->url($a, "/home/vitrines/{$id}"), ['titulo' => 'x', 'tipo' => 'categoria', 'categoria' => 'minha', 'ativa' => 1])->assertSessionHasNoErrors();
    }

    public function test_mover_e_remover_vitrines_e_limite(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->entrar($a);
        $this->post($this->url($a, '/home/vitrines'), ['titulo' => 'Primeira V', 'tipo' => 'recentes']);
        $this->post($this->url($a, '/home/vitrines'), ['titulo' => 'Segunda V', 'tipo' => 'gratuitos']);

        // a home padrão já traz 2 vitrines; as novas vêm depois delas
        $titulos = fn () => array_column($this->contexto($a, fn () => Personalizacao::rascunho('home')['vitrines']), 'titulo');
        $this->assertSame(['Novidades', 'Para começar agora', 'Primeira V', 'Segunda V'], $titulos());

        $ultimo = $this->contexto($a, fn () => Personalizacao::rascunho('home')['vitrines'][3]['id']);
        $this->post($this->url($a, "/home/vitrines/{$ultimo}/mover"), ['sentido' => 'sobe']);
        $this->assertSame(['Novidades', 'Para começar agora', 'Segunda V', 'Primeira V'], $titulos());

        $this->post($this->url($a, "/home/vitrines/{$ultimo}/remover"));
        $this->assertSame(['Novidades', 'Para começar agora', 'Segunda V'], $titulos());

        $this->post($this->url($a, '/home/vitrines/nao-existe/remover'))->assertNotFound();

        for ($i = 0; $i < 12; $i++) {
            $this->post($this->url($a, '/home/vitrines'), ['titulo' => "V{$i}", 'tipo' => 'recentes']);
        }
        $this->assertCount(12, $this->contexto($a, fn () => Personalizacao::rascunho('home')['vitrines']));
    }

    public function test_vitrines_antigas_sem_id_podem_ser_editadas(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->contexto($a, fn () => ConfiguracaoVisual::create([
            'tipo' => 'home', 'estado' => ConfiguracaoVisual::PUBLICADO,
            'conteudo_json' => ['banner' => ['titulo' => 'X'], 'vitrines' => [['titulo' => 'Antiga', 'tipo' => 'recentes']]],
        ]));
        $this->entrar($a);

        $this->post($this->url($a, '/home/vitrines/legado-0'), ['titulo' => 'Renomeada', 'tipo' => 'recentes', 'limite' => 8, 'ativa' => 1])->assertSessionHasNoErrors();

        $this->assertSame('Renomeada', $this->contexto($a, fn () => Personalizacao::rascunho('home')['vitrines'][0]['titulo']));
    }

    public function test_rascunho_de_um_parceiro_nao_aparece_para_outro(): void
    {
        $a = $this->criarParceiro('alfa');
        $b = $this->criarParceiro('beta');
        $this->entrar($a);
        $this->post($this->url($a, '/home/banner'), ['titulo' => 'Segredo do Alfa', 'botao_tipo' => 'busca']);

        $this->criarMembro($b, MembroEquipe::ADMINISTRADOR, 'adm@beta.com');
        $this->actingAs($this->contexto($b, fn () => MembroEquipe::query()->first()), 'equipe')
            ->get($this->url($b, '/previa'))->assertOk()->assertDontSee('Segredo do Alfa');
    }

    public function test_pendente_ignora_a_ordem_das_chaves(): void
    {
        $a = $this->criarParceiro('alfa');
        $this->contexto($a, function (): void {
            ConfiguracaoVisual::create(['tipo' => 'home', 'estado' => ConfiguracaoVisual::PUBLICADO, 'conteudo_json' => ['b' => 1, 'a' => ['y' => 2, 'x' => 3]]]);
            ConfiguracaoVisual::create(['tipo' => 'home', 'estado' => ConfiguracaoVisual::RASCUNHO, 'conteudo_json' => ['a' => ['x' => 3, 'y' => 2], 'b' => 1]]);
        });

        $this->assertFalse($this->contexto($a, fn () => Personalizacao::pendente('home')));
    }
}
