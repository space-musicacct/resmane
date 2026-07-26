<?php

namespace Tests\Unit\Services;

use App\Models\UpperLimitSetting;
use App\Repositories\V1\Contracts\UpperLimitSettingRepositoryInterface;
use App\Services\V1\SettingLimitService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 単体テスト仕様書 2.4 SettingLimitService 対応テスト
 *
 * Repository をモック化し、DB に依存せずビジネスロジックのみを検証する。
 */
class SettingLimitServiceTest extends TestCase
{
    private UpperLimitSettingRepositoryInterface&MockInterface $repository;
    private SettingLimitService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(UpperLimitSettingRepositoryInterface::class);
        $this->service = new SettingLimitService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * SSL-001: find: 設定が存在する
     */
    #[Test]
    public function test_SSL_001_find_returns_setting(): void
    {
        $userId = 1;

        $setting = Mockery::mock(UpperLimitSetting::class);

        $this->repository
            ->shouldReceive('findByUserId')
            ->once()
            ->with($userId)
            ->andReturn($setting);

        $result = $this->service->find($userId);

        $this->assertSame($setting, $result);
    }

    /**
     * SSL-002: find: 設定が存在しない
     */
    #[Test]
    public function test_SSL_002_find_returns_null_when_not_found(): void
    {
        $userId = 1;

        $this->repository
            ->shouldReceive('findByUserId')
            ->once()
            ->with($userId)
            ->andReturn(null);

        $result = $this->service->find($userId);

        $this->assertNull($result);
    }

    /**
     * SSL-003: upsert: 正常作成/更新
     */
    #[Test]
    public function test_SSL_003_upsert_calls_repository_with_correct_data(): void
    {
        $userId = 1;

        $validated = [
            'upperLimitTypeId' => 1,
            'maxValue' => 30,
            'aveMonthlyIncome' => 200000,
        ];

        $setting = Mockery::mock(UpperLimitSetting::class);

        $this->repository
            ->shouldReceive('upsert')
            ->once()
            ->with($userId, [
                'upper_limit_type_id' => 1,
                'max_value' => 30,
                'ave_monthly_income' => 200000,
            ])
            ->andReturn($setting);

        $result = $this->service->upsert($userId, $validated);

        $this->assertSame($setting, $result);
    }

    /**
     * SSL-004: upsert: aveMonthlyIncome 省略時に null が設定される
     */
    #[Test]
    public function test_SSL_004_upsert_defaults_aveMonthlyIncome_to_null(): void
    {
        $userId = 1;

        $validated = [
            'upperLimitTypeId' => 2,
            'maxValue' => 50000,
        ];

        $setting = Mockery::mock(UpperLimitSetting::class);

        $this->repository
            ->shouldReceive('upsert')
            ->once()
            ->with($userId, [
                'upper_limit_type_id' => 2,
                'max_value' => 50000,
                'ave_monthly_income' => null,
            ])
            ->andReturn($setting);

        $result = $this->service->upsert($userId, $validated);

        $this->assertSame($setting, $result);
    }
}
