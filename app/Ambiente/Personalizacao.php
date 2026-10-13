<?php

namespace App\Ambiente;

use App\Auditoria\Auditor;
use App\Models\ConfiguracaoVisual;
use App\Models\MembroEquipe;
use Illuminate\Support\Facades\DB;

/**
 * Rascunho e publicação de tema e home (UNY-DOC-003, FS-REG-030: nada aparece para o aluno
 * antes da publicação explícita). O aluno só lê o publicado; a prévia da equipe lê o rascunho.
 */
class Personalizacao
{
    /** @return array<string, mixed>|null */
    public static function publicado(string $tipo): ?array
    {
        return self::ler($tipo, ConfiguracaoVisual::PUBLICADO);
    }

    /** @return array<string, mixed>|null */
    public static function rascunho(string $tipo): ?array
    {
        return self::ler($tipo, ConfiguracaoVisual::RASCUNHO);
    }

    /** O que o aluno vê; na prévia, o rascunho (se existir) no lugar do publicado. */
    public static function efetivo(string $tipo, bool $previa = false): ?array
    {
        return ($previa ? self::rascunho($tipo) : null) ?? self::publicado($tipo);
    }

    /** O que o editor mostra: rascunho, senão publicado, senão o padrão. */
    public static function paraEditar(string $tipo, array $padrao): array
    {
        return self::rascunho($tipo) ?? self::publicado($tipo) ?? $padrao;
    }

    public static function salvarRascunho(string $tipo, array $conteudo, MembroEquipe $autor): void
    {
        $registro = ConfiguracaoVisual::query()->firstOrNew(['tipo' => $tipo, 'estado' => ConfiguracaoVisual::RASCUNHO]);
        $registro->conteudo_json = $conteudo;
        $registro->atualizado_por = $autor->id;
        $registro->save();

        Auditor::registrar("{$tipo}.rascunho_salvo", $autor, alvo: $registro);
    }

    /** Publica o rascunho. Devolve false se não há rascunho. */
    public static function publicar(string $tipo, MembroEquipe $autor): bool
    {
        $rascunho = ConfiguracaoVisual::query()->where('tipo', $tipo)->where('estado', ConfiguracaoVisual::RASCUNHO)->first();

        if ($rascunho === null) {
            return false;
        }

        DB::transaction(function () use ($tipo, $rascunho, $autor): void {
            $publicado = ConfiguracaoVisual::query()->firstOrNew(['tipo' => $tipo, 'estado' => ConfiguracaoVisual::PUBLICADO]);
            $publicado->versao = $publicado->exists ? $publicado->versao + 1 : 1;
            $publicado->conteudo_json = $rascunho->conteudo_json;
            $publicado->atualizado_por = $autor->id;
            $publicado->publicado_em = now();
            $publicado->save();
        });

        Auditor::registrar("{$tipo}.publicado", $autor, detalhes: ['versao' => self::versaoPublicada($tipo)]);

        return true;
    }

    public static function descartar(string $tipo, MembroEquipe $autor): void
    {
        ConfiguracaoVisual::query()->where('tipo', $tipo)->where('estado', ConfiguracaoVisual::RASCUNHO)->delete();
        Auditor::registrar("{$tipo}.rascunho_descartado", $autor);
    }

    /** Há alterações ainda não publicadas? */
    public static function pendente(string $tipo): bool
    {
        $rascunho = self::rascunho($tipo);

        if ($rascunho === null) {
            return false;
        }

        $publicado = self::publicado($tipo);

        return $publicado === null || self::canonico($rascunho) !== self::canonico($publicado);
    }

    public static function versaoPublicada(string $tipo): int
    {
        return (int) ConfiguracaoVisual::query()->where('tipo', $tipo)->where('estado', ConfiguracaoVisual::PUBLICADO)->value('versao');
    }

    /** @return array<string, mixed>|null */
    private static function ler(string $tipo, string $estado): ?array
    {
        $conteudo = ConfiguracaoVisual::query()->where('tipo', $tipo)->where('estado', $estado)->first()?->conteudo_json;

        return is_array($conteudo) ? $conteudo : null;
    }

    /** O MySQL reordena as chaves do JSON; compara sem depender da ordem. */
    private static function canonico(mixed $valor): string
    {
        $ordenar = function (mixed $v) use (&$ordenar): mixed {
            if (! is_array($v)) {
                return $v;
            }
            $v = array_map($ordenar, $v);
            if (! array_is_list($v)) {
                ksort($v);
            }

            return $v;
        };

        return (string) json_encode($ordenar($valor), JSON_UNESCAPED_UNICODE);
    }
}
