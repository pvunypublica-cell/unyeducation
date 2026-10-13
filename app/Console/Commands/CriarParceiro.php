<?php

namespace App\Console\Commands;

use App\Auditoria\Auditor;
use App\Models\Dominio;
use App\Models\MembroEquipe;
use App\Models\Parceiro;
use App\Models\Plano;
use App\Tenancy\RegrasDeSlug;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CriarParceiro extends Command
{
    protected $signature = 'parceiro:criar
        {slug : Identificador do parceiro (minúsculas, números e hífen)}
        {nome : Nome exibido}
        {--host= : Endereço próprio, no lugar do padrão ava-{slug}.{domínio} (útil em desenvolvimento)}
        {--admin-email= : Cria o primeiro administrador com este e-mail}
        {--admin-nome=Administrador : Nome do primeiro administrador}';

    protected $description = 'Cria um parceiro com endereço ativo e, se pedido, o primeiro administrador';

    public function handle(TenantContext $contexto): int
    {
        $slug = (string) $this->argument('slug');

        if ($erro = RegrasDeSlug::erro($slug)) {
            $this->error($erro);

            return self::FAILURE;
        }

        if (Parceiro::query()->where('slug', $slug)->exists()) {
            $this->error("Já existe um parceiro com o slug \"{$slug}\".");

            return self::FAILURE;
        }

        $host = mb_strtolower((string) ($this->option('host') ?: RegrasDeSlug::hostPadrao($slug)));

        if (Dominio::query()->where('host', $host)->exists()) {
            $this->error("O endereço {$host} já está em uso.");

            return self::FAILURE;
        }

        $senha = null;

        DB::transaction(function () use ($slug, $host, $contexto, &$senha, &$parceiro): void {
            $plano = Plano::query()->where('ativo', true)->orderBy('id')->first()
                ?? Plano::create(['nome' => 'Piloto', 'ativo' => true]);

            $parceiro = Parceiro::create([
                'slug' => $slug,
                'nome' => (string) $this->argument('nome'),
                'plano_id' => $plano->id,
                'estado' => Parceiro::ATIVO,
            ]);

            Dominio::create([
                'tenant_id' => $parceiro->id,
                'host' => $host,
                'tipo' => $this->option('host') ? 'proprio' : 'subdominio_padrao',
                'estado' => Dominio::ATIVO,
                'principal' => true,
                'verificado_em' => now(),
            ]);

            if ($email = $this->option('admin-email')) {
                $senha = Str::password(16, symbols: false);

                $contexto->runAs($parceiro->id, fn () => MembroEquipe::create([
                    'nome' => (string) $this->option('admin-nome'),
                    'email' => $email,
                    'senha_hash' => Hash::make($senha),
                    'papel' => MembroEquipe::ADMINISTRADOR,
                    'pode_publicar' => true,
                    'estado' => MembroEquipe::ATIVO,
                ]));
            }
        });

        Auditor::registrar('parceiro.criado', alvo: $parceiro, tenantId: $parceiro->id, detalhes: ['slug' => $slug, 'host' => $host], ip: 'console');

        $this->info("Parceiro criado: {$slug} → http://{$host}");

        if ($senha !== null) {
            $this->line('Administrador: '.$this->option('admin-email'));
            $this->line("Senha inicial (anote agora, ela não é mostrada de novo): {$senha}");
        }

        return self::SUCCESS;
    }
}
