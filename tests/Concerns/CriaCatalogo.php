<?php

namespace Tests\Concerns;

use App\Models\Arquivo;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Material;
use App\Models\Matricula;
use App\Models\Modulo;
use App\Models\Parceiro;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

trait CriaCatalogo
{
    /** Cria um curso publicado com N aulas publicadas num único módulo. */
    protected function criarCurso(Parceiro $parceiro, string $slug = 'curso-a', int $aulas = 3, bool $gratuito = true, string $estado = Curso::PUBLICADO): Curso
    {
        return app(TenantContext::class)->runAs($parceiro->id, function () use ($slug, $aulas, $gratuito, $estado) {
            $curso = Curso::create([
                'titulo' => 'Curso '.strtoupper($slug), 'slug' => $slug, 'estado' => $estado,
                'gratuito' => $gratuito, 'publicado_em' => now(),
            ]);
            $modulo = Modulo::create(['curso_id' => $curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1]);

            for ($i = 1; $i <= $aulas; $i++) {
                Aula::create([
                    'modulo_id' => $modulo->id, 'curso_id' => $curso->id, 'titulo' => "Aula {$i} de {$slug}",
                    'ordem' => $i, 'publicada' => true, 'duracao_seg' => 600,
                ]);
            }

            return $curso;
        });
    }

    protected function aulasDe(Parceiro $parceiro, Curso $curso): Collection
    {
        return app(TenantContext::class)->runAs($parceiro->id, fn () => Aula::query()
            ->where('curso_id', $curso->id)->orderBy('ordem')->get());
    }

    protected function matricular(Parceiro $parceiro, $aluno, Curso $curso, string $estado = Matricula::ATIVA): Matricula
    {
        return app(TenantContext::class)->runAs($parceiro->id, fn () => Matricula::create([
            'aluno_id' => $aluno->id, 'curso_id' => $curso->id, 'estado' => $estado, 'origem' => 'nativo',
        ]));
    }

    protected function criarMaterial(Parceiro $parceiro, Aula $aula, string $conteudo = 'conteudo secreto'): Material
    {
        return app(TenantContext::class)->runAs($parceiro->id, function () use ($parceiro, $aula, $conteudo) {
            $caminho = "parceiros/{$parceiro->id}/materiais/teste-{$aula->id}.txt";
            Storage::disk('local')->put($caminho, $conteudo);

            $arquivo = Arquivo::create([
                'caminho' => $caminho, 'nome_original' => 'apoio.txt', 'mime' => 'text/plain',
                'tamanho_bytes' => strlen($conteudo), 'hash_sha256' => hash('sha256', $conteudo), 'publico' => false,
            ]);

            return Material::create(['aula_id' => $aula->id, 'arquivo_id' => $arquivo->id, 'titulo' => 'Apoio']);
        });
    }
}
