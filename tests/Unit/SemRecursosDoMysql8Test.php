<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * UNY-DOC-008, seção 9.4: o servidor é MySQL 5.7. O código não pode usar recursos exclusivos do 8.0.
 * Este teste procura esses recursos no texto do código (rede de segurança; o CI roda no 5.7 de verdade).
 */
class SemRecursosDoMysql8Test extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function proibidos(): array
    {
        return [
            'CTE recursiva' => ['/\bWITH\s+RECURSIVE\b/i', 'WITH RECURSIVE'],
            'funcao de janela (OVER)' => ['/\bOVER\s*\(/i', 'OVER ('],
            'ROW_NUMBER e similares' => ['/\b(ROW_NUMBER|DENSE_RANK|PERCENT_RANK|NTILE)\s*\(/i', 'ROW_NUMBER()'],
            'JSON_TABLE' => ['/\bJSON_TABLE\b/i', 'JSON_TABLE'],
            'SKIP LOCKED' => ['/SKIP\s+LOCKED/i', 'SKIP LOCKED'],
            'skipLocked()' => ['/->skipLocked\s*\(/', '->skipLocked()'],
            'coluna invisivel' => ['/->invisible\s*\(/', '->invisible()'],
            'CHECK (nao e aplicado no 5.7)' => ['/\bCHECK\s*\(/i', 'CHECK ('],
        ];
    }

    #[DataProvider('proibidos')]
    public function test_o_codigo_nao_usa_recurso_exclusivo_do_mysql_8(string $padrao, string $nome): void
    {
        $raiz = dirname(__DIR__, 2);
        $achados = [];

        foreach (['app', 'database', 'routes'] as $pasta) {
            $caminho = $raiz.'/'.$pasta;

            if (! is_dir($caminho)) {
                continue;
            }

            $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($caminho, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($iterador as $arquivo) {
                if ($arquivo->getExtension() !== 'php') {
                    continue;
                }

                if (preg_match($padrao, (string) file_get_contents($arquivo->getPathname())) === 1) {
                    $achados[] = str_replace($raiz.'/', '', $arquivo->getPathname());
                }
            }
        }

        $this->assertSame([], $achados, "Recurso exclusivo do MySQL 8 ({$nome}) em: ".implode(', ', $achados));
    }
}
