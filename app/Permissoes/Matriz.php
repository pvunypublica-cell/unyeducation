<?php

namespace App\Permissoes;

use App\Models\Aluno;
use App\Models\MembroEquipe;
use App\Models\Operador;

/**
 * Matriz de permissões (UNY-DOC-003, seção 3.2). A verificação acontece no servidor
 * em toda ação (FS-PER-001); esconder botão não conta.
 *
 * Escopos:
 *  - todos: vale para qualquer recurso do parceiro;
 *  - proprios: instrutor, apenas nos recursos dele (a checagem de dono entra com os cursos, no M2);
 *  - proprios_se_permitido: como "proprios", e só se a conta tiver pode_publicar.
 */
class Matriz
{
    public const OPERADOR = 'operador';

    public const ADMIN = 'administrador';

    public const INSTRUTOR = 'instrutor';

    public const SUPORTE = 'suporte';

    public const ALUNO = 'aluno';

    /** @var array<string, array<string, string>> ação => [papel => escopo] */
    private const REGRAS = [
        'parceiros.gerenciar' => [self::OPERADOR => 'todos'],
        'suporte.acessar_parceiro' => [self::OPERADOR => 'todos'],
        'aparencia.configurar' => [self::ADMIN => 'todos'],
        'integracoes.configurar' => [self::ADMIN => 'todos'],
        'equipe.gerenciar' => [self::ADMIN => 'todos'],
        'cursos.editar' => [self::ADMIN => 'todos', self::INSTRUTOR => 'proprios'],
        'cursos.publicar' => [self::ADMIN => 'todos', self::INSTRUTOR => 'proprios_se_permitido'],
        'alunos.ver' => [self::ADMIN => 'todos', self::INSTRUTOR => 'proprios', self::SUPORTE => 'todos'],
        'matriculas.criar_manual' => [self::ADMIN => 'todos', self::SUPORTE => 'todos'],
        'certificados.revogar' => [self::ADMIN => 'todos'],
        'tarefas.corrigir' => [self::ADMIN => 'todos', self::INSTRUTOR => 'proprios'],
        'comentarios.moderar' => [self::ADMIN => 'todos', self::INSTRUTOR => 'proprios', self::SUPORTE => 'todos'],
        'relatorios.ver' => [self::ADMIN => 'todos', self::INSTRUTOR => 'proprios'],
        'cursos.assistir' => [self::ALUNO => 'todos'],
    ];

    /** @return list<string> */
    public static function acoes(): array
    {
        return array_keys(self::REGRAS);
    }

    public static function papelDe(object $usuario): ?string
    {
        return match (true) {
            $usuario instanceof Operador => $usuario->ativo ? self::OPERADOR : null,
            $usuario instanceof MembroEquipe => $usuario->estado === MembroEquipe::ATIVO ? $usuario->papel : null,
            $usuario instanceof Aluno => $usuario->estado === 'ativo' ? self::ALUNO : null,
            default => null,
        };
    }

    /** Escopo do papel para a ação, ou null se não pode. */
    public static function escopo(?string $papel, string $acao): ?string
    {
        if ($papel === null) {
            return null;
        }

        return self::REGRAS[$acao][$papel] ?? null;
    }

    public static function permite(object $usuario, string $acao): bool
    {
        $escopo = self::escopo(self::papelDe($usuario), $acao);

        if ($escopo === null) {
            return false;
        }

        if ($escopo === 'proprios_se_permitido') {
            return (bool) ($usuario->pode_publicar ?? false);
        }

        return true;
    }
}
