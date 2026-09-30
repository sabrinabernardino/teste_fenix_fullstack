<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Trava de segurança: RefreshDatabase apaga tudo. Se por qualquer motivo os testes
     * apontarem para o Postgres real, abortamos ANTES de rodar as migrations.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if ($app['config']->get('database.default') !== 'sqlite') {
            throw new RuntimeException('Os testes devem rodar em SQLite em memória, nunca no banco real.');
        }

        return $app;
    }
}
