<?php

namespace Tests\Concerns;

use App\Models\Aluno;
use App\Models\Dominio;
use App\Models\MembroEquipe;
use App\Models\Parceiro;
use App\Models\Plano;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;

trait CriaParceiros
{
    protected function criarParceiro(
        string $slug,
        ?string $host = null,
        string $estado = Parceiro::ATIVO,
        string $estadoDominio = Dominio::ATIVO,
    ): Parceiro {
        $plano = Plano::query()->first() ?? Plano::create(['nome' => 'Teste', 'ativo' => true]);

        $parceiro = Parceiro::create([
            'slug' => $slug,
            'nome' => 'Parceiro '.strtoupper($slug),
            'plano_id' => $plano->id,
            'estado' => $estado,
        ]);

        Dominio::create([
            'tenant_id' => $parceiro->id,
            'host' => $host ?? "ava-{$slug}.faculdadeunypublica.edu.br",
            'tipo' => 'subdominio_padrao',
            'estado' => $estadoDominio,
            'principal' => true,
        ]);

        return $parceiro;
    }

    protected function urlDe(Parceiro $parceiro, string $caminho = '/'): string
    {
        return 'http://'.$parceiro->dominios()->where('principal', true)->value('host').$caminho;
    }

    protected function criarAluno(Parceiro $parceiro, string $email = 'aluno@exemplo.com', string $senha = 'senha-forte-123', array $extra = []): Aluno
    {
        return app(TenantContext::class)->runAs($parceiro->id, fn () => Aluno::create(array_merge([
            'nome' => 'Aluno Teste',
            'email' => $email,
            'senha_hash' => Hash::make($senha),
        ], $extra)));
    }

    protected function criarMembro(
        Parceiro $parceiro,
        string $papel = MembroEquipe::ADMINISTRADOR,
        string $email = 'equipe@exemplo.com',
        string $senha = 'senha-forte-123',
        string $estado = MembroEquipe::ATIVO,
        bool $podePublicar = false,
    ): MembroEquipe {
        return app(TenantContext::class)->runAs($parceiro->id, fn () => MembroEquipe::create([
            'nome' => 'Equipe Teste',
            'email' => $email,
            'senha_hash' => Hash::make($senha),
            'papel' => $papel,
            'estado' => $estado,
            'pode_publicar' => $podePublicar,
        ]));
    }
}
