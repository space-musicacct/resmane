<?php

declare(strict_types=1);

namespace Tests\Support;

use Override;

/**
 * API v1 のエンドポイント定義
 *
 * version() で 'v1' を返すことで、基底クラスの全エンドポイントが
 * '/api/v1/...' として解決される。
 */
final class V1ApiEndpoint extends ApiEndpoint
{
    /**
     * @return string 'v1'
     */
    #[Override]
    protected function version(): string
    {
        return 'v1';
    }
}
