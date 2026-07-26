<?php

namespace Tests\Unit\Concerns;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * abort() による HttpException（ステータスコード）を検証する共通アサーション。
 */
trait InteractsWithAbort
{
    /**
     * $callback 実行時に指定ステータスコードの HttpException が
     * 投げられることを検証する。
     */
    private function assertAbort(callable $callback, int $status): void
    {
        try {
            $callback();
            $this->fail("Expected HttpException with status $status was not thrown.");
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }
}
