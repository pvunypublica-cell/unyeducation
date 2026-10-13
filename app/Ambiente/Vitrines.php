<?php

namespace App\Ambiente;

use App\Aprendizado\Progresso;
use App\Models\Aluno;
use App\Models\Arquivo;
use App\Models\Categoria;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Parceiro;
use Illuminate\Support\Collection;

/**
 * Monta a home do aluno a partir da configuração publicada do parceiro (banner e vitrines).
 * Referências a cursos são conferidas na hora de exibir: curso arquivado ou removido some
 * da vitrine (UNY-DOC-005, 13.1).
 */
class Vitrines
{
    private const LIMITE_PADRAO = 12;

    private const LIMITE_MAXIMO = 24;

    public function __construct(private readonly Progresso $progresso) {}

    /** @return array<string, mixed> */
    public static function configuracaoPadrao(Parceiro $parceiro): array
    {
        return [
            'banner' => [
                'titulo' => $parceiro->nome,
                'texto' => 'Aprenda no seu ritmo, onde e quando quiser.',
                'botao_rotulo' => 'Explorar cursos',
            ],
            'vitrines' => [
                ['titulo' => 'Novidades', 'tipo' => 'recentes'],
                ['titulo' => 'Para começar agora', 'tipo' => 'gratuitos'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function configuracao(Parceiro $parceiro, bool $previa = false): array
    {
        $padrao = self::configuracaoPadrao($parceiro);
        $publicada = Personalizacao::efetivo('home', $previa);

        if (! is_array($publicada)) {
            return $padrao;
        }

        return [
            'banner' => array_merge($padrao['banner'], is_array($publicada['banner'] ?? null) ? $publicada['banner'] : []),
            'vitrines' => self::normalizar(is_array($publicada['vitrines'] ?? null) ? $publicada['vitrines'] : $padrao['vitrines']),
        ];
    }

    /**
     * Garante um identificador estável em cada vitrine. Configurações antigas, sem id, recebem
     * um derivado da posição; ao salvar o rascunho o id passa a fazer parte do conteúdo.
     *
     * @param  array<int, mixed>  $vitrines
     * @return list<array<string, mixed>>
     */
    public static function normalizar(array $vitrines): array
    {
        $saida = [];
        foreach (array_values($vitrines) as $i => $v) {
            if (! is_array($v)) {
                continue;
            }
            $v['id'] = isset($v['id']) && is_string($v['id']) && $v['id'] !== '' ? $v['id'] : "legado-{$i}";
            $saida[] = $v;
        }

        return $saida;
    }

    /**
     * @return array{banner: array<string, mixed>, continuar: list<array<string, mixed>>, vitrines: list<array<string, mixed>>}
     */
    public function montar(?Aluno $aluno, Parceiro $parceiro, bool $previa = false): array
    {
        $config = $this->configuracao($parceiro, $previa);
        // Na prévia da equipe não há aluno: nada de matrícula, progresso ou favoritos.
        $matriculas = $aluno ? Matricula::query()->where('aluno_id', $aluno->id)->get()->keyBy('curso_id') : collect();
        $favoritos = $aluno ? $aluno->favoritosIds() : [];

        $vitrines = [];
        foreach ($config['vitrines'] as $definicao) {
            if (($definicao['ativa'] ?? true) === false) {
                continue;
            }

            $cursos = $this->cursosDaVitrine($definicao);

            if ($cursos->isEmpty()) {
                continue; // vitrine vazia não aparece
            }

            $vitrines[] = [
                'titulo' => (string) ($definicao['titulo'] ?? ''),
                'cards' => $cursos->map(fn (Curso $curso) => $this->card($curso, $matriculas->get($curso->id), $favoritos))->all(),
            ];
        }

        return [
            'banner' => $this->banner($config['banner']),
            'continuar' => $this->continuarAssistindo($matriculas),
            'vitrines' => $vitrines,
        ];
    }

    /** @return Collection<int, Curso> */
    private function cursosDaVitrine(array $definicao): Collection
    {
        $limite = max(1, min(self::LIMITE_MAXIMO, (int) ($definicao['limite'] ?? self::LIMITE_PADRAO)));
        $consulta = Curso::query()->with('capa')->publicados()->orderByDesc('publicado_em')->orderByDesc('id')->limit($limite);

        return match ($definicao['tipo'] ?? null) {
            'recentes' => $consulta->get(),
            'gratuitos' => $consulta->where('gratuito', true)->get(),
            'categoria' => $this->daCategoria($consulta, (string) ($definicao['categoria'] ?? '')),
            'manual' => $this->manual($definicao),
            default => collect(),
        };
    }

    /**
     * Seleção manual: respeita a ordem escolhida e ignora curso que deixou de estar publicado.
     *
     * @return Collection<int, Curso>
     */
    private function manual(array $definicao): Collection
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($definicao['cursos'] ?? []))));

        if ($ids === []) {
            return collect();
        }

        $cursos = Curso::query()->with('capa')->publicados()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $cursos->get($id))->filter()->values();
    }

    /**
     * Banner pronto para exibir: destino resolvido e imagens só se forem públicas do parceiro.
     *
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private function banner(array $b): array
    {
        $href = match ($b['botao_tipo'] ?? 'busca') {
            'curso' => ($slug = Curso::query()->publicados()->whereKey((int) ($b['botao_curso_id'] ?? 0))->value('slug')) ? route('curso', $slug) : null,
            'url' => preg_match('~^https://~i', (string) ($b['botao_url'] ?? '')) === 1 ? (string) $b['botao_url'] : null,
            'nenhum' => null,
            default => route('busca'),
        };

        $imagem = function (mixed $id): ?string {
            $arquivo = $id ? Arquivo::query()->whereKey((int) $id)->where('publico', true)->first() : null;

            return $arquivo !== null && str_starts_with($arquivo->mime, 'image/') ? route('midia', $arquivo->uuid) : null;
        };

        $desktop = $imagem($b['imagem_desktop_id'] ?? null);

        return [
            'titulo' => (string) ($b['titulo'] ?? ''),
            'texto' => (string) ($b['texto'] ?? ''),
            'botao_rotulo' => (string) ($b['botao_rotulo'] ?? ''),
            'href' => $href,
            'imagem_desktop' => $desktop,
            'imagem_mobile' => $imagem($b['imagem_mobile_id'] ?? null) ?? $desktop,
        ];
    }

    /** @return Collection<int, Curso> */
    private function daCategoria($consulta, string $slug): Collection
    {
        $categoria = Categoria::query()->where('slug', $slug)->where('ativa', true)->first();

        if ($categoria === null) {
            return collect();
        }

        return $consulta->whereIn('id', $categoria->cursos()->select('cursos.id'))->get();
    }

    /**
     * @param  array<int, true>  $favoritos
     * @return array<string, mixed>
     */
    public function card(Curso $curso, ?Matricula $matricula, array $favoritos = []): array
    {
        return [
            'curso' => $curso,
            'percentual' => $matricula?->estaAtiva() ? $matricula->percentual_concluido : null,
            'favorito' => isset($favoritos[$curso->id]),
            'incluido' => $curso->gratuito || ($matricula?->estaAtiva() ?? false),
        ];
    }

    /**
     * @param  Collection<int, Matricula>  $matriculas
     * @return list<array<string, mixed>>
     */
    private function continuarAssistindo(Collection $matriculas): array
    {
        $ativas = $matriculas->filter(fn (Matricula $m) => $m->estaAtiva());

        if ($ativas->isEmpty()) {
            return [];
        }

        $ultimos = Matricula::query()
            ->whereIn('id', $ativas->pluck('id'))
            ->withMax('progressos as ultimo_progresso_em', 'atualizado_em')
            ->get()
            ->filter(fn (Matricula $m) => $m->ultimo_progresso_em !== null && $m->percentual_concluido < 100)
            ->sortByDesc('ultimo_progresso_em')
            ->take(12);

        $cursos = Curso::query()->with('capa')->acessiveis()->whereIn('id', $ultimos->pluck('curso_id'))->get()->keyBy('id');

        $itens = [];
        foreach ($ultimos as $matricula) {
            $curso = $cursos->get($matricula->curso_id);
            $aula = $curso ? $this->progresso->aulaParaContinuar($matricula) : null;

            if ($curso === null || $aula === null) {
                continue;
            }

            $itens[] = ['curso' => $curso, 'aula' => $aula, 'percentual' => $matricula->percentual_concluido];
        }

        return $itens;
    }
}
