<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Segurança: os testes recriam tabelas. Nunca devem rodar contra um banco de verdade.
        $banco = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_contains($banco, 'test')) {
            throw new RuntimeException(
                "Recusado: o banco '{$banco}' não parece ser de teste (o nome deve conter 'test')."
            );
        }

        $auditoria = (string) config('database.connections.audit.database');

        if (! str_contains($auditoria, 'test')) {
            throw new RuntimeException(
                "Recusado: o banco de auditoria '{$auditoria}' não parece ser de teste."
            );
        }
    }
}
