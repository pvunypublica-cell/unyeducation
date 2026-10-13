<?php

namespace App\Console\Commands;

use App\Auditoria\Auditor;
use App\Models\Operador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CriarOperador extends Command
{
    protected $signature = 'operador:criar {email} {nome} {--papel=administrador : administrador ou suporte}';

    protected $description = 'Cria uma conta de operador da plataforma (pede a senha sem mostrá-la)';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (Operador::query()->where('email', $email)->exists()) {
            $this->error('Já existe um operador com este e-mail.');

            return self::FAILURE;
        }

        $senha = $this->secret('Senha (mínimo 12 caracteres)');

        if (strlen((string) $senha) < 12) {
            $this->error('Senha curta demais.');

            return self::FAILURE;
        }

        $operador = Operador::create([
            'nome' => (string) $this->argument('nome'),
            'email' => $email,
            'senha_hash' => Hash::make($senha),
            'papel' => (string) $this->option('papel'),
            'ativo' => true,
        ]);

        Auditor::registrar('operador.criado', alvo: $operador, ip: 'console');
        $this->info('Operador criado.');

        return self::SUCCESS;
    }
}
