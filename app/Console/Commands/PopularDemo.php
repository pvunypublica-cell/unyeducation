<?php

namespace App\Console\Commands;

use App\Aprendizado\Progresso;
use App\Models\Aluno;
use App\Models\Arquivo;
use App\Models\Aula;
use App\Models\Categoria;
use App\Models\ConfiguracaoVisual;
use App\Models\Curso;
use App\Models\Dominio;
use App\Models\Material;
use App\Models\Matricula;
use App\Models\MembroEquipe;
use App\Models\Modulo;
use App\Models\Parceiro;
use App\Models\Plano;
use App\Models\ProgressoAula;
use App\Tenancy\RegrasDeSlug;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cria um parceiro de demonstração com cursos, aulas e um aluno, para ver o ambiente funcionando.
 * Nunca roda em produção.
 */
class PopularDemo extends Command
{
    protected $signature = 'demo:popular
        {--slug=demo : Identificador do parceiro de demonstração}
        {--host= : Endereço do parceiro (padrão: o endereço padrão do slug)}';

    protected $description = 'Cria um parceiro de demonstração com catálogo e um aluno (bloqueado em produção)';

    public function handle(TenantContext $contexto): int
    {
        if (app()->isProduction()) {
            $this->error('Este comando não roda em produção.');

            return self::FAILURE;
        }

        $slug = (string) $this->option('slug');

        if ($erro = RegrasDeSlug::erro($slug)) {
            $this->error($erro);

            return self::FAILURE;
        }

        if (Parceiro::query()->where('slug', $slug)->exists()) {
            $this->error("Já existe o parceiro \"{$slug}\". Use outro --slug.");

            return self::FAILURE;
        }

        $host = mb_strtolower((string) ($this->option('host') ?: RegrasDeSlug::hostPadrao($slug)));
        $senha = Str::password(14, symbols: false);
        $senhaEquipe = Str::password(14, symbols: false);

        DB::transaction(function () use ($slug, $host, $senha, $senhaEquipe, $contexto): void {
            $plano = Plano::query()->where('ativo', true)->orderBy('id')->first()
                ?? Plano::create(['nome' => 'Piloto', 'ativo' => true]);

            $parceiro = Parceiro::create(['slug' => $slug, 'nome' => 'Escola Demo', 'plano_id' => $plano->id, 'estado' => Parceiro::ATIVO]);
            Dominio::create([
                'tenant_id' => $parceiro->id, 'host' => $host, 'tipo' => $this->option('host') ? 'proprio' : 'subdominio_padrao',
                'estado' => Dominio::ATIVO, 'principal' => true, 'verificado_em' => now(),
            ]);

            $contexto->runAs($parceiro->id, function () use ($parceiro, $senha, $senhaEquipe): void {
                $this->popular($parceiro, $senha);

                MembroEquipe::create([
                    'nome' => 'Administrador Demonstração', 'email' => 'admin@exemplo.com', 'senha_hash' => Hash::make($senhaEquipe),
                    'papel' => MembroEquipe::ADMINISTRADOR, 'pode_publicar' => true, 'estado' => MembroEquipe::ATIVO,
                ]);
            });
        });

        $this->info("Pronto. Abra: http://{$host}");
        $this->line('Aluno de demonstração: demo@exemplo.com');
        $this->line("Senha do aluno (anote agora): {$senha}");
        $this->line("Equipe: admin@exemplo.com — entre em http://{$host}/equipe/entrar");
        $this->line("Senha da equipe (anote agora): {$senhaEquipe}");

        return self::SUCCESS;
    }

    private function popular(Parceiro $parceiro, string $senha): void
    {
        $aluno = Aluno::create(['nome' => 'Aluna Demonstração', 'email' => 'demo@exemplo.com', 'senha_hash' => Hash::make($senha)]);

        $cat = [];
        foreach ([['Tecnologia', 'tecnologia'], ['Negócios', 'negocios'], ['Bem-estar', 'bem-estar'], ['Idiomas', 'idiomas']] as $i => [$nome, $s]) {
            $cat[$s] = Categoria::create(['nome' => $nome, 'slug' => $s, 'ordem' => $i]);
        }

        $cursos = [
            ['Excel do zero ao avançado', 'excel', 'tecnologia', true, 'Do primeiro clique às fórmulas, tabelas dinâmicas e painéis. Aulas curtas e exercícios para baixar.', 155],
            ['Introdução à programação', 'programacao', 'tecnologia', true, 'Lógica e primeiros programas, sem pré-requisito.', 12],
            ['Atendimento ao cliente', 'atendimento', 'negocios', true, 'Como atender bem, resolver problemas e fidelizar.', 9],
            ['Gestão do tempo', 'gestao-do-tempo', 'negocios', false, 'Planejamento semanal e foco no que importa.', 8],
            ['Respiração e foco', 'respiracao-e-foco', 'bem-estar', true, 'Práticas curtas para o dia a dia.', 6],
            ['Inglês para o trabalho', 'ingles-trabalho', 'idiomas', false, 'E-mails, reuniões e apresentações.', 10],
            ['Espanhol básico', 'espanhol-basico', 'idiomas', true, 'Frases e situações do cotidiano.', 7],
        ];

        $ids = [];
        foreach ($cursos as $n => [$titulo, $cslug, $categoria, $gratuito, $descricao, $qtd]) {
            $curso = Curso::create([
                'titulo' => $titulo, 'slug' => $cslug, 'descricao' => $descricao, 'estado' => Curso::PUBLICADO,
                'gratuito' => $gratuito, 'certificado_ativo' => true, 'publicado_em' => now()->subDays($n),
            ]);
            $curso->categorias()->attach($cat[$categoria]->id, ['tenant_id' => $parceiro->id]);
            $ids[$cslug] = $curso;
            $this->criarAulas($curso, $qtd, $parceiro);
        }

        // Rascunho e arquivado: não podem aparecer para o aluno.
        Curso::create(['titulo' => 'Curso em rascunho', 'slug' => 'rascunho', 'estado' => Curso::RASCUNHO, 'gratuito' => true]);
        Curso::create(['titulo' => 'Curso arquivado', 'slug' => 'arquivado', 'estado' => Curso::ARQUIVADO, 'gratuito' => true]);

        // A aluna já começou o curso de Excel: algumas aulas feitas, para mostrar "continuar assistindo".
        $matricula = Matricula::create(['aluno_id' => $aluno->id, 'curso_id' => $ids['excel']->id, 'estado' => Matricula::ATIVA, 'origem' => 'nativo']);
        $aulas = Aula::query()->where('curso_id', $ids['excel']->id)->orderBy('modulo_id')->orderBy('ordem')->limit(5)->get();
        foreach ($aulas as $i => $aula) {
            ProgressoAula::create([
                'matricula_id' => $matricula->id, 'aula_id' => $aula->id, 'concluida' => $i < 4,
                'concluida_em' => $i < 4 ? now() : null, 'percentual_assistido' => $i < 4 ? 100 : 30,
                'posicao_seg' => $i < 4 ? 0 : 120, 'atualizado_em' => now()->subMinutes(10 - $i),
            ]);
        }
        app(Progresso::class)->recalcular($matricula);

        ConfiguracaoVisual::create([
            'tipo' => 'home', 'estado' => ConfiguracaoVisual::PUBLICADO, 'publicado_em' => now(),
            'conteudo_json' => [
                'banner' => ['titulo' => 'Aprenda no seu ritmo', 'texto' => 'Cursos livres para o trabalho e para a vida.', 'botao_rotulo' => 'Ver cursos'],
                'vitrines' => [
                    ['titulo' => 'Novidades', 'tipo' => 'recentes'],
                    ['titulo' => 'Para começar agora', 'tipo' => 'gratuitos'],
                    ['titulo' => 'Tecnologia', 'tipo' => 'categoria', 'categoria' => 'tecnologia'],
                    ['titulo' => 'Idiomas', 'tipo' => 'categoria', 'categoria' => 'idiomas'],
                ],
            ],
        ]);
    }

    private function criarAulas(Curso $curso, int $total, Parceiro $parceiro): void
    {
        $porModulo = $total > 40 ? 12 : 4;
        $numero = 0;
        $moduloNumero = 0;

        while ($numero < $total) {
            $moduloNumero++;
            $modulo = Modulo::create(['curso_id' => $curso->id, 'titulo' => "Módulo {$moduloNumero}", 'ordem' => $moduloNumero]);

            for ($i = 1; $i <= $porModulo && $numero < $total; $i++) {
                $numero++;
                $aula = Aula::create([
                    'modulo_id' => $modulo->id, 'curso_id' => $curso->id, 'titulo' => "Aula {$numero}",
                    'ordem' => $i, 'tipo' => 'video', 'duracao_seg' => 360 + ($numero * 37 % 600), 'publicada' => true,
                ]);

                if ($numero === 1) {
                    $this->criarMaterial($aula, $parceiro);
                }
            }
        }
    }

    private function criarMaterial(Aula $aula, Parceiro $parceiro): void
    {
        $caminho = "parceiros/{$parceiro->id}/materiais/".Str::uuid().'.txt';
        $conteudo = "Material de demonstração do curso.\n";
        Storage::disk('local')->put($caminho, $conteudo);

        $arquivo = Arquivo::create([
            'caminho' => $caminho, 'nome_original' => 'material-de-apoio.txt', 'mime' => 'text/plain',
            'tamanho_bytes' => strlen($conteudo), 'hash_sha256' => hash('sha256', $conteudo), 'publico' => false,
        ]);

        Material::create(['aula_id' => $aula->id, 'arquivo_id' => $arquivo->id, 'titulo' => 'Material de apoio']);
    }
}
