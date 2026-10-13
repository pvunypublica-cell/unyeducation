<?php

namespace App\Http\Controllers\Equipe;

use App\Ambiente\Video;
use App\Auditoria\Auditor;
use App\Catalogo\Publicacao;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Equipe\Concerns\Autoriza;
use App\Models\Arquivo;
use App\Models\Aula;
use App\Models\Categoria;
use App\Models\Curso;
use App\Models\MembroEquipe;
use App\Models\Modulo;
use App\Permissoes\Matriz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CursoController extends Controller
{
    use Autoriza;

    private const ABAS = ['info' => 'Informações', 'conteudo' => 'Conteúdo', 'acesso' => 'Acesso', 'publicacao' => 'Publicação'];

    public function index(Request $request)
    {
        $this->autorizar('cursos.editar');

        $consulta = Curso::query()->withCount(['aulas', 'matriculas as alunos'])->with('categorias');

        // Instrutor enxerga só os seus cursos.
        if (Matriz::escopo(Matriz::papelDe($this->membro()), 'cursos.editar') !== 'todos') {
            $consulta->where('instrutor_id', $this->membro()->id);
        }

        $estado = (string) $request->query('estado', '');
        if (in_array($estado, [Curso::RASCUNHO, Curso::PUBLICADO, Curso::ARQUIVADO], true)) {
            $consulta->where('estado', $estado);
        }

        if ($categoria = (int) $request->query('categoria', 0)) {
            $consulta->whereIn('id', DB::table('curso_categoria')
                ->where('tenant_id', $this->membro()->tenant_id)->where('categoria_id', $categoria)->select('curso_id'));
        }

        return view('equipe.cursos.index', [
            'cursos' => $consulta->orderByDesc('atualizado_em')->orderByDesc('id')->get(),
            'categorias' => Categoria::query()->orderBy('ordem')->orderBy('nome')->get(),
            'estado' => $estado,
            'categoriaId' => $categoria ?: null,
        ]);
    }

    public function criar()
    {
        $this->autorizar('cursos.editar');

        return view('equipe.cursos.criar');
    }

    public function salvar(Request $request): RedirectResponse
    {
        $this->autorizar('cursos.editar');

        $dados = $request->validate([
            'titulo' => ['required', 'string', 'min:3', 'max:160'],
        ], ['titulo.required' => 'Dê um título ao curso.', 'titulo.min' => 'O título precisa de ao menos 3 letras.']);

        $membro = $this->membro();

        $curso = Curso::create([
            'titulo' => trim($dados['titulo']),
            'slug' => $this->slugLivre($dados['titulo']),
            'estado' => Curso::RASCUNHO,
            // Instrutor cria cursos já como instrutor deles; o administrador escolhe depois.
            'instrutor_id' => $membro->papel === MembroEquipe::INSTRUTOR ? $membro->id : null,
        ]);

        Auditor::registrar('curso.criado', $membro, alvo: $curso, detalhes: ['titulo' => $curso->titulo]);

        return redirect()->route('equipe.cursos.editar', $curso->id)->with('status', 'Curso criado como rascunho.');
    }

    public function editar(Request $request, int $id)
    {
        $curso = $this->cursoDoPainel($id);
        $aba = array_key_exists((string) $request->query('aba'), self::ABAS) ? (string) $request->query('aba') : 'info';

        $dados = ['curso' => $curso, 'aba' => $aba, 'abas' => self::ABAS];

        if ($aba === 'info') {
            $dados += [
                'categorias' => Categoria::query()->orderBy('ordem')->orderBy('nome')->get(),
                'selecionadas' => $curso->categorias()->pluck('categorias.id')->all(),
                'instrutores' => MembroEquipe::query()->where('estado', MembroEquipe::ATIVO)
                    ->whereIn('papel', [MembroEquipe::ADMINISTRADOR, MembroEquipe::INSTRUTOR])->orderBy('nome')->get(),
                'capa' => $curso->capa,
            ];
        }

        if ($aba === 'conteudo') {
            $dados += [
                'modulos' => Modulo::query()->where('curso_id', $curso->id)->orderBy('ordem')->orderBy('id')->get(),
                'aulas' => Aula::query()->where('curso_id', $curso->id)->orderBy('ordem')->orderBy('id')->get()->groupBy('modulo_id'),
            ];
        }

        if ($aba === 'publicacao') {
            $dados += ['pendencias' => Publicacao::pendencias($curso)];
        }

        return view('equipe.cursos.editar', $dados);
    }

    public function atualizar(Request $request, int $id): RedirectResponse
    {
        $curso = $this->cursoDoPainel($id);
        $aba = (string) $request->input('aba', 'info');

        if ($aba === 'acesso') {
            $dados = $request->validate(['gratuito' => ['nullable', 'boolean'], 'certificado_ativo' => ['nullable', 'boolean']]);
            $curso->update(['gratuito' => (bool) ($dados['gratuito'] ?? false), 'certificado_ativo' => (bool) ($dados['certificado_ativo'] ?? false)]);
            Auditor::registrar('curso.acesso_alterado', $this->membro(), alvo: $curso, detalhes: ['gratuito' => $curso->gratuito]);

            return redirect()->route('equipe.cursos.editar', [$curso->id, 'aba' => 'acesso'])->with('status', 'Acesso salvo.');
        }

        $dados = $request->validate([
            'titulo' => ['required', 'string', 'min:3', 'max:160'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'trailer_provedor' => ['nullable', Rule::in(['youtube', 'vimeo'])],
            'trailer_url' => ['nullable', 'string', 'max:300'],
            'categorias' => ['nullable', 'array', 'max:10'],
            'categorias.*' => ['integer'],
            'instrutor_id' => ['nullable', 'integer'],
        ], ['titulo.required' => 'Dê um título ao curso.']);

        $trailerRef = null;
        if (! empty($dados['trailer_url'])) {
            $trailerRef = Video::extrairRef($dados['trailer_provedor'] ?? null, $dados['trailer_url']);
            if ($trailerRef === null) {
                return back()->withInput()->withErrors(['trailer_url' => 'Não reconheci esse endereço de vídeo. Escolha o provedor e cole o endereço da página do vídeo.']);
            }
        }

        $alteracoes = [
            'titulo' => trim($dados['titulo']),
            'descricao' => $dados['descricao'] ?? null,
            'trailer_provedor' => $trailerRef ? $dados['trailer_provedor'] : null,
            'trailer_ref' => $trailerRef,
        ];

        // Só quem enxerga todos os cursos (administrador) troca o instrutor.
        if (Matriz::escopo(Matriz::papelDe($this->membro()), 'cursos.editar') === 'todos' && array_key_exists('instrutor_id', $dados)) {
            $alteracoes['instrutor_id'] = $dados['instrutor_id']
                ? MembroEquipe::query()->where('estado', MembroEquipe::ATIVO)
                    ->whereIn('papel', [MembroEquipe::ADMINISTRADOR, MembroEquipe::INSTRUTOR])
                    ->whereKey($dados['instrutor_id'])->value('id')
                : null;
        }

        DB::transaction(function () use ($curso, $alteracoes, $dados): void {
            $curso->update($alteracoes);

            $validas = Categoria::query()->whereIn('id', $dados['categorias'] ?? [])->pluck('id')->all();
            $curso->categorias()->sync(collect($validas)->mapWithKeys(
                fn ($categoriaId) => [$categoriaId => ['tenant_id' => $curso->tenant_id]],
            )->all());
        });

        Auditor::registrar('curso.editado', $this->membro(), alvo: $curso);

        return redirect()->route('equipe.cursos.editar', $curso->id)->with('status', 'Informações salvas.');
    }

    public function capa(Request $request, int $id): RedirectResponse
    {
        $curso = $this->cursoDoPainel($id);

        $request->validate([
            'capa' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=200,min_height=200'],
        ], [
            'capa.required' => 'Escolha uma imagem.',
            'capa.mimes' => 'Use uma imagem JPG, PNG ou WEBP.',
            'capa.max' => 'A imagem pode ter no máximo 2 MB.',
            'capa.dimensions' => 'A imagem é pequena demais (mínimo 200 × 200 pixels).',
            'capa.uploaded' => 'Não foi possível enviar a imagem (talvez seja grande demais).',
        ]);

        $arquivo = $this->guardar($request, 'capa', 'capas', publico: true);
        $curso->update(['capa_arquivo_id' => $arquivo->id]);

        Auditor::registrar('curso.capa_alterada', $this->membro(), alvo: $curso);

        return redirect()->route('equipe.cursos.editar', $curso->id)->with('status', 'Capa atualizada.');
    }

    public function publicar(int $id): RedirectResponse
    {
        $curso = $this->cursoDoPainel($id, 'cursos.publicar');
        $pendencias = Publicacao::pendencias($curso);

        if ($pendencias['bloqueios'] !== []) {
            return redirect()->route('equipe.cursos.editar', [$curso->id, 'aba' => 'publicacao'])
                ->withErrors(['publicacao' => implode(' ', $pendencias['bloqueios'])]);
        }

        $curso->estado = Curso::PUBLICADO;
        $curso->publicado_em ??= now();
        $curso->save();

        Auditor::registrar('curso.publicado', $this->membro(), alvo: $curso);

        return redirect()->route('equipe.cursos.editar', [$curso->id, 'aba' => 'publicacao'])->with('status', 'Curso publicado.');
    }

    public function arquivar(int $id): RedirectResponse
    {
        $curso = $this->cursoDoPainel($id, 'cursos.publicar');
        $curso->update(['estado' => Curso::ARQUIVADO]);
        Auditor::registrar('curso.arquivado', $this->membro(), alvo: $curso);

        return redirect()->route('equipe.cursos.editar', [$curso->id, 'aba' => 'publicacao'])
            ->with('status', 'Curso arquivado. Quem já está matriculado continua com acesso.');
    }

    /** Volta para rascunho. Só é possível se ninguém está matriculado; senão, arquive. */
    public function rascunho(int $id): RedirectResponse
    {
        $curso = $this->cursoDoPainel($id, 'cursos.publicar');

        if ($curso->matriculas()->exists()) {
            return redirect()->route('equipe.cursos.editar', [$curso->id, 'aba' => 'publicacao'])
                ->withErrors(['publicacao' => 'Há alunos matriculados neste curso. Para tirá-lo do catálogo, arquive-o.']);
        }

        $curso->update(['estado' => Curso::RASCUNHO]);
        Auditor::registrar('curso.voltou_rascunho', $this->membro(), alvo: $curso);

        return redirect()->route('equipe.cursos.editar', [$curso->id, 'aba' => 'publicacao'])->with('status', 'O curso voltou a ser rascunho.');
    }

    /** Página do curso como o aluno vê, mesmo em rascunho. Só leitura. */
    public function previa(int $id)
    {
        $curso = $this->cursoDoPainel($id);

        $modulos = Modulo::query()->where('curso_id', $curso->id)->orderBy('ordem')->orderBy('id')->get();
        $aulas = Aula::query()->where('curso_id', $curso->id)->publicadas()->orderBy('ordem')->orderBy('id')->get()->groupBy('modulo_id');

        $estruturas = [];
        $totalAulas = 0;
        $totalSeg = 0;
        foreach ($modulos as $modulo) {
            $doModulo = $aulas->get($modulo->id, collect());
            if ($doModulo->isEmpty()) {
                continue;
            }
            $totalAulas += $doModulo->count();
            $totalSeg += (int) $doModulo->sum('duracao_seg');
            $estruturas[] = ['modulo' => $modulo, 'aulas' => $doModulo->map(fn (Aula $a) => ['aula' => $a, 'feita' => false, 'estado' => 'disponivel'])->all()];
        }

        return view('ambiente.curso', [
            'previa' => true, 'curso' => $curso->load('capa'), 'matricula' => null, 'estruturas' => $estruturas,
            'totalAulas' => $totalAulas, 'totalSeg' => $totalSeg, 'continuar' => null, 'primeira' => null,
            'favorito' => false, 'categorias' => $curso->categorias()->where('ativa', true)->get(),
        ]);
    }

    /** Guarda o arquivo enviado fora da pasta pública, com nome gerado (nunca o nome do usuário). */
    public static function guardarArquivo(Request $request, string $campo, string $pasta, bool $publico, ?MembroEquipe $autor): Arquivo
    {
        $enviado = $request->file($campo);
        $extensao = $enviado->guessExtension() ?: 'bin';
        $uuid = (string) Str::uuid();
        $caminho = "parceiros/{$autor->tenant_id}/{$pasta}/{$uuid}.{$extensao}";

        $conteudo = file_get_contents($enviado->getRealPath());
        Storage::disk('local')->put($caminho, $conteudo);

        $nome = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename((string) $enviado->getClientOriginalName())) ?? 'arquivo';

        return Arquivo::create([
            'caminho' => $caminho,
            'nome_original' => mb_substr($nome !== '' ? $nome : "arquivo.{$extensao}", 0, 180),
            'mime' => $enviado->getMimeType() ?: 'application/octet-stream',
            'tamanho_bytes' => strlen($conteudo),
            'hash_sha256' => hash('sha256', $conteudo),
            'publico' => $publico,
            'enviado_por_tipo' => 'MembroEquipe',
            'enviado_por_id' => $autor->id,
        ]);
    }

    private function guardar(Request $request, string $campo, string $pasta, bool $publico): Arquivo
    {
        return self::guardarArquivo($request, $campo, $pasta, $publico, $this->membro());
    }

    private function slugLivre(string $titulo): string
    {
        $base = Str::limit(Str::slug($titulo) ?: 'curso', 100, '');
        $slug = $base;
        $n = 2;

        while (Curso::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
