<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        if ($database !== 'resmane_test') {
            $this->fail("テストが本番 DB ({$database}) に接続しています。phpunit.xml の DB_DATABASE=resmane_test を確認してください。");
        }
    }
}
