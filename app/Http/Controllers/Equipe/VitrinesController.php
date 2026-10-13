<?php

namespace App\Http\Controllers\Equipe;

use App\Ambiente\Personalizacao;
use App\Ambiente\Vitrines;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Equipe\Concerns\Autoriza;
use App\Models\Categoria;
use App\Models\Curso;
use App\Models\Parceiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VitrinesController extends Controller
{
    use Autoriza;

    private const TIPOS = [
        'recentes' => 'Novidades (cursos publicados mais recentemente)',
        'gratuitos' => 'Cursos gratuitos',
        'categoria' => 'Cursos de uma categoria',
        'manual' => 'Seleção manual de cursos',
    ];

    public function index(Request $request)
    {
        $this->autorizar('aparencia.configurar');
        $home = $this->rascunho($request);

        return view('equipe.home', [
            'banner' => $home['banner'],
            'vitrines' => $home['vitrines'],
            'tipos' => self::TIPOS,
            'categorias' => Categoria::query()->where('ativa', true)->orderBy('ordem')->orderBy('nome')->get(),
            'cursos' => Curso::query()->publicados()->orderBy('titulo')->get(['id', 'titulo']),
            'pendente' => Personalizacao::pendente('home'),
        ]);
    }

    public function banner(Request $request): RedirectResponse
    {
        $this->autorizar('aparencia.configurar');

        $dados = $request->validate([
            'titulo' => ['nullable', 'string', 'max:80'],
            'texto' => ['nullable', 'string', 'max:200'],
            'botao_rotulo' => ['nullable', 'string', 'max:30'],
            'botao_tipo' => ['required', Rule::in(['busca', 'curso', 'url', 'nenhum'])],
            'botao_curso_id' => ['nullable', 'integer'],
            'botao_url' => ['nullable', 'string', 'max:300'],
            'imagem_desktop' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=600,min_height=200'],
            'imagem_mobile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=300,min_height=200'],
            'remover_desktop' => ['nullable', 'boolean'],
            'remover_mobile' => ['nullable', 'boolean'],
        ], [
            'imagem_desktop.dimensions' => 'A imagem do computador é pequena demais (mínimo 600 × 200 pixels).',
            'imagem_mobile.dimensions' => 'A imagem do celular é pequena demais (mínimo 300 × 200 pixels).',
            'imagem_desktop.max' => 'A imagem do computador pode ter no máximo 3 MB.',
            'imagem_mobile.max' => 'A imagem do celular pode ter no máximo 3 MB.',
        ]);

        $cursoId = null;
        if ($dados['botao_tipo'] === 'curso') {
            $cursoId = Curso::query()->publicados()->whereKey((int) ($dados['botao_curso_id'] ?? 0))->value('id');
            if ($cursoId === null) {
                return back()->withInput()->withErrors(['botao_curso_id' => 'Escolha um curso publicado para o botão.']);
            }
        }

        $url = null;
        if ($dados['botao_tipo'] === 'url') {
            $url = trim((string) ($dados['botao_url'] ?? ''));
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('~^https://~i', $url)) {
                return back()->withInput()->withErrors(['botao_url' => 'Informe um endereço completo que comece com https://']);
            }
        }

        $home = $this->rascunho($request);
        $anterior = $home['banner'];

        $banner = [
            'titulo' => trim((string) ($dados['titulo'] ?? '')),
            'texto' => trim((string) ($dados['texto'] ?? '')),
            'botao_rotulo' => trim((string) ($dados['botao_rotulo'] ?? '')),
            'botao_tipo' => $dados['botao_tipo'],
            'botao_curso_id' => $cursoId,
            'botao_url' => $url,
            'imagem_desktop_id' => $request->boolean('remover_desktop') ? null : ($anterior['imagem_desktop_id'] ?? null),
            'imagem_mobile_id' => $request->boolean('remover_mobile') ? null : ($anterior['imagem_mobile_id'] ?? null),
        ];

        if ($request->hasFile('imagem_desktop')) {
            $banner['imagem_desktop_id'] = CursoController::guardarArquivo($request, 'imagem_desktop', 'banners', true, $this->membro())->id;
        }
        if ($request->hasFile('imagem_mobile')) {
            $banner['imagem_mobile_id'] = CursoController::guardarArquivo($request, 'imagem_mobile', 'banners', true, $this->membro())->id;
        }

        $home['banner'] = $banner;
        $this->guardar($home);

        return redirect()->route('equipe.home')->with('status', 'Banner salvo no rascunho.');
    }

    public function adicionar(Request $request): RedirectResponse
    {
        $this->autorizar('aparencia.configurar');
        $home = $this->rascunho($request);

        if (count($home['vitrines']) >= 12) {
            return back()->withErrors(['vitrines' => 'O limite é de 12 vitrines na home.']);
        }

        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:80'],
            'tipo' => ['required', Rule::in(array_keys(self::TIPOS))],
        ], ['titulo.required' => 'Dê um título à vitrine.']);

        $home['vitrines'][] = [
            'id' => (string) Str::uuid(), 'titulo' => trim($dados['titulo']), 'tipo' => $dados['tipo'],
            'ativa' => true, 'limite' => 12, 'cursos' => [], 'categoria' => null,
        ];
        $this->guardar($home);

        return redirect()->route('equipe.home')->with('status', 'Vitrine adicionada. Ajuste os detalhes abaixo.');
    }

    public function atualizar(Request $request, string $id): RedirectResponse
    {
        $this->autorizar('aparencia.configurar');
        $home = $this->rascunho($request);
        $indice = $this->indice($home, $id);

        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:80'],
            'tipo' => ['required', Rule::in(array_keys(self::TIPOS))],
            'categoria' => ['nullable', 'string', 'max:100'],
            'cursos' => ['nullable', 'array', 'max:24'],
            'cursos.*' => ['integer'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:24'],
            'ativa' => ['nullable', 'boolean'],
        ], ['titulo.required' => 'Dê um título à vitrine.']);

        $categoria = null;
        if ($dados['tipo'] === 'categoria') {
            $categoria = Categoria::query()->where('ativa', true)->where('slug', (string) ($dados['categoria'] ?? ''))->value('slug');
            if ($categoria === null) {
                return back()->withErrors(['vitrine-'.$id => 'Escolha uma categoria para esta vitrine.']);
            }
        }

        // Só entram cursos publicados deste parceiro, na ordem escolhida.
        $cursos = $dados['tipo'] === 'manual'
            ? Curso::query()->publicados()->whereIn('id', $dados['cursos'] ?? [])->pluck('id')->map(fn ($v) => (int) $v)->all()
            : [];
        if ($dados['tipo'] === 'manual') {
            $ordem = array_flip(array_map('intval', $dados['cursos'] ?? []));
            usort($cursos, fn ($a, $b) => $ordem[$a] <=> $ordem[$b]);
        }

        $home['vitrines'][$indice] = [
            'id' => $home['vitrines'][$indice]['id'],
            'titulo' => trim($dados['titulo']), 'tipo' => $dados['tipo'], 'categoria' => $categoria,
            'cursos' => $cursos, 'limite' => (int) ($dados['limite'] ?? 12), 'ativa' => $request->boolean('ativa'),
        ];
        $this->guardar($home);

        return redirect()->route('equipe.home')->with('status', 'Vitrine salva no rascunho.');
    }

    public function mover(Request $request, string $id): RedirectResponse
    {
        $this->autorizar('aparencia.configurar');
        $dados = $request->validate(['sentido' => ['required', 'in:sobe,desce']]);
        $home = $this->rascunho($request);
        $i = $this->indice($home, $id);
        $j = $dados['sentido'] === 'sobe' ? $i - 1 : $i + 1;

        if (isset($home['vitrines'][$j])) {
            [$home['vitrines'][$i], $home['vitrines'][$j]] = [$home['vitrines'][$j], $home['vitrines'][$i]];
            $this->guardar($home);
        }

        return redirect()->route('equipe.home');
    }

    public function remover(Request $request, string $id): RedirectResponse
    {
        $this->autorizar('aparencia.configurar');
        $home = $this->rascunho($request);
        unset($home['vitrines'][$this->indice($home, $id)]);
        $home['vitrines'] = array_values($home['vitrines']);
        $this->guardar($home);

        return redirect()->route('equipe.home')->with('status', 'Vitrine removida do rascunho.');
    }

    public function publicar(): RedirectResponse
    {
        $this->autorizar('aparencia.configurar');
        $publicou = Personalizacao::publicar('home', $this->membro());

        return redirect()->route('equipe.home')->with('status', $publicou ? 'Home publicada. Os alunos já veem as mudanças.' : 'Não há alterações para publicar.');
    }

    public function descartar(): RedirectResponse
    {
        $this->autorizar('aparencia.configurar');
        Personalizacao::descartar('home', $this->membro());

        return redirect()->route('equipe.home')->with('status', 'Alterações descartadas.');
    }

    /** Home como o aluno vê, com o rascunho de tema e de home aplicados. Só leitura. */
    public function previa(Request $request, Vitrines $vitrines)
    {
        $this->autorizar('aparencia.configurar');
        $request->attributes->set('previa', true);

        return view('ambiente.home', $vitrines->montar(null, $request->attributes->get('parceiro'), true) + ['previa' => true]);
    }

    /** @return array{banner: array<string, mixed>, vitrines: list<array<string, mixed>>} */
    private function rascunho(Request $request): array
    {
        /** @var Parceiro $parceiro */
        $parceiro = $request->attributes->get('parceiro');
        $padrao = Vitrines::configuracaoPadrao($parceiro);
        $home = Personalizacao::paraEditar('home', $padrao);

        return [
            'banner' => array_merge(['botao_tipo' => 'busca'], $padrao['banner'], is_array($home['banner'] ?? null) ? $home['banner'] : []),
            'vitrines' => Vitrines::normalizar(is_array($home['vitrines'] ?? null) ? $home['vitrines'] : $padrao['vitrines']),
        ];
    }

    /** @param  array{banner: array<string, mixed>, vitrines: list<array<string, mixed>>}  $home */
    private function guardar(array $home): void
    {
        Personalizacao::salvarRascunho('home', ['banner' => $home['banner'], 'vitrines' => array_values($home['vitrines'])], $this->membro());
    }

    /** @param  array{banner: array<string, mixed>, vitrines: list<array<string, mixed>>}  $home */
    private function indice(array $home, string $id): int
    {
        foreach ($home['vitrines'] as $i => $v) {
            if ($v['id'] === $id) {
                return $i;
            }
        }

        abort(404);
    }
}
