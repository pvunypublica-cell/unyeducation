<?php

namespace App\Ambiente;

use App\Aprendizado\Progresso;
use App\Models\Aluno;
use App\Models\Categoria;
use App\Models\ConfiguracaoVisual;
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
    public function configuracao(Parceiro $parceiro): array
    {
        $padrao = self::configuracaoPadrao($parceiro);
        $publicada = ConfiguracaoVisual::query()
            ->where('tipo', 'home')->where('estado', ConfiguracaoVisual::PUBLICADO)->first()?->conteudo_json;

        if (! is_array($publicada)) {
            return $padrao;
        }

        return [
            'banner' => array_merge($padrao['banner'], is_array($publicada['banner'] ?? null) ? $publicada['banner'] : []),
            'vitrines' => is_array($publicada['vitrines'] ?? null) ? $publicada['vitrines'] : $padrao['vitrines'],
        ];
    }

    /**
     * @return array{banner: array<string, mixed>, continuar: list<array<string, mixed>>, vitrines: list<array<string, mixed>>}
     */
    public function montar(Aluno $aluno, Parceiro $parceiro): array
    {
        $config = $this->configuracao($parceiro);
        $matriculas = Matricula::query()->where('aluno_id', $aluno->id)->get()->keyBy('curso_id');
        $favoritos = $aluno->favoritosIds();

        $vitrines = [];
        foreach ($config['vitrines'] as $definicao) {
            $cursos = $this->cursosDaVitrine(is_array($definicao) ? $definicao : []);

            if ($cursos->isEmpty()) {
                continue; // vitrine vazia não aparece
            }

            $vitrines[] = [
                'titulo' => (string) ($definicao['titulo'] ?? ''),
                'cards' => $cursos->map(fn (Curso $curso) => $this->card($curso, $matriculas->get($curso->id), $favoritos))->all(),
            ];
        }

        return [
            'banner' => $config['banner'],
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
            default => collect(),
        };
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

        $cursos = Curso::query()->with('capa')->publicados()->whereIn('id', $ultimos->pluck('curso_id'))->get()->keyBy('id');

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
