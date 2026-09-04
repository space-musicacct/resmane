<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Override;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    #[Override]
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($database !== 'resmane_test') {
            throw new RuntimeException(
                "テスト実行を拒否しました。接続先DB: {$database} (resmane_test 以外への接続は禁止されています)"
            );
        }

        return $app;
    }
}
