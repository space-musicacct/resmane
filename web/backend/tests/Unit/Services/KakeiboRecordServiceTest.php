<?php

namespace Tests\Unit\Services;

use App\Repositories\Contracts\KakeiboRecordRepositoryInterface;
use App\Services\KakeiboRecordService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * 単体テスト仕様書 2.1 KakeiboRecordService 対応テスト
 *
 * Repository をモック化し、DB に依存せずビジネスロジックのみを検証する。
 *
 * NOTE: KakeiboRecordRepositoryInterface のメソッド名・シグネチャ、
 * および Service 側のメソッド名は実装未確認のため想定で記述している。
 * 実装と差異がある場合は要修正。
 */
class KakeiboRecordServiceTest extends TestCase
{
    private KakeiboRecordRepositoryInterface&MockInterface $repository;
    private KakeiboRecordService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(KakeiboRecordRepositoryInterface::class);
        $this->service = new KakeiboRecordService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * SKR-001: list: 一覧取得が正しく動作する
     */
    #[Test]
    public function SKR_001_一覧取得が正しく動作する(): void
    {
        $userId = 1;
        $filters = [];

        $paginator = Mockery::mock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

        $this->repository
            ->shouldReceive('paginate')
            ->once()
            ->with($userId, $filters)
            ->andReturn($paginator);

        $this->repository
            ->shouldReceive('sumAmountByType')
            ->once()
            ->with($userId, $filters, 'income')
            ->andReturn(300000);

        $this->repository
            ->shouldReceive('sumAmountByType')
            ->once()
            ->with($userId, $filters, 'expense')
            ->andReturn(150000);

        $result = $this->service->list($userId, $filters);

        $this->assertArrayHasKey('records', $result);
        $this->assertArrayHasKey('totalIncome', $result);
        $this->assertArrayHasKey('totalExpense', $result);
        $this->assertSame($paginator, $result['records']);
        $this->assertSame(300000, $result['totalIncome']);
        $this->assertSame(150000, $result['totalExpense']);
    }

    /**
     * SKR-002: findOrFail: 正常取得
     */
    #[Test]
    public function SKR_002_findOrFailで正常取得できる(): void
    {
        $userId = 1;
        $recordId = 10;

        $record = (object) ['id' => $recordId, 'user_id' => $userId];

        $this->repository
            ->shouldReceive('findOrFail')
            ->once()
            ->with($recordId)
            ->andReturn($record);

        $result = $this->service->findOrFail($userId, $recordId);

        $this->assertSame($record, $result);
    }

    /**
     * SKR-003: findOrFail: レコードが存在しない
     */
    #[Test]
    public function SKR_003_findOrFailでレコードが存在しない場合は404になる(): void
    {
        $userId = 1;
        $recordId = 999;

        $this->repository
            ->shouldReceive('findOrFail')
            ->once()
            ->with($recordId)
            ->andReturn(null);

        $this->assertAbort(
            fn () => $this->service->findOrFail($userId, $recordId),
            404
        );
    }

    /**
     * SKR-004: findOrFail: 他ユーザーのレコード
     */
    #[Test]
    public function SKR_004_findOrFailで他ユーザーのレコードは403になる(): void
    {
        $userId = 1;
        $recordId = 10;

        $record = (object) ['id' => $recordId, 'user_id' => 999];

        $this->repository
            ->shouldReceive('findOrFail')
            ->once()
            ->with($recordId)
            ->andReturn($record);

        $this->assertAbort(
            fn () => $this->service->findOrFail($userId, $recordId),
            403
        );
    }

    /**
     * SKR-005: findOrFailForUpdate: 正常取得
     */
    #[Test]
    public function SKR_005_findOrFailForUpdateで正常取得できる(): void
    {
        $userId = 1;
        $recordId = 10;

        $record = (object) ['id' => $recordId, 'user_id' => $userId];

        $this->repository
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId)
            ->andReturn($record);

        $result = $this->service->findOrFailForUpdate($userId, $recordId);

        $this->assertSame($record, $result);
    }

    /**
     * SKR-006: findOrFailForUpdate: レコードが存在しない
     */
    #[Test]
    public function SKR_006_findOrFailForUpdateでレコードが存在しない場合は404になる(): void
    {
        $userId = 1;
        $recordId = 999;

        $this->repository
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId)
            ->andReturn(null);

        $this->assertAbort(
            fn () => $this->service->findOrFailForUpdate($userId, $recordId),
            404
        );
    }

    /**
     * SKR-007: findOrFailForUpdate: 他ユーザーのレコード
     */
    #[Test]
    public function SKR_007_findOrFailForUpdateで他ユーザーのレコードは403になる(): void
    {
        $userId = 1;
        $recordId = 10;

        $record = (object) ['id' => $recordId, 'user_id' => 999];

        $this->repository
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId)
            ->andReturn($record);

        $this->assertAbort(
            fn () => $this->service->findOrFailForUpdate($userId, $recordId),
            403
        );
    }

    /**
     * SKR-008: create: 正常作成
     */
    #[Test]
    public function SKR_008_createでRepositoryが正しいsnake_caseデータで呼ばれる(): void
    {
        $userId = 1;

        $validated = [
            'purchaseDate' => '2026-07-01',
            'amountTypeId' => 1,
            'amount' => 1000,
            'details' => 'テスト',
            'kakeiboDefaultCategoryId' => 1,
        ];

        $expected = [
            'user_id' => $userId,
            'purchase_date' => '2026-07-01',
            'amount_type_id' => 1,
            'amount' => 1000,
            'details' => 'テスト',
            'kakeibo_default_category_id' => 1,
        ];

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) use ($expected) {
                foreach ($expected as $key => $value) {
                    if (! array_key_exists($key, $data) || $data[$key] !== $value) {
                        return false;
                    }
                }

                return true;
            }))
            ->andReturn((object) $expected);

        $this->service->create($userId, $validated);
    }

    /**
     * SKR-009: create: purchaseDate 省略時に今日の日付が設定される
     */
    #[Test]
    public function SKR_009_createでpurchaseDate省略時に今日の日付が設定される(): void
    {
        $userId = 1;
        $today = now()->toDateString();

        $validated = [
            'amountTypeId' => 1,
            'amount' => 1000,
            'details' => 'テスト',
            'kakeiboDefaultCategoryId' => 1,
        ];

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) use ($today) {
                return ($data['purchase_date'] ?? null) === $today;
            }))
            ->andReturn((object) []);

        $this->service->create($userId, $validated);
    }

    /**
     * SKR-010: update: 正常更新
     */
    #[Test]
    public function SKR_010_updateでRepositoryのupdateが呼ばれる(): void
    {
        $userId = 1;
        $recordId = 10;

        $record = (object) ['id' => $recordId, 'user_id' => $userId];

        $validated = [
            'purchaseDate' => '2026-07-02',
            'amountTypeId' => 1,
            'amount' => 2000,
            'details' => '更新後',
            'kakeiboDefaultCategoryId' => 2,
        ];

        $this->repository
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId)
            ->andReturn($record);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($record, Mockery::type('array'))
            ->andReturn($record);

        $this->service->update($userId, $recordId, $validated);
    }

    /**
     * SKR-011: delete: 正常削除
     */
    #[Test]
    public function SKR_011_deleteでRepositoryのdeleteが呼ばれる(): void
    {
        $userId = 1;
        $recordId = 10;

        $record = (object) ['id' => $recordId, 'user_id' => $userId];

        $this->repository
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId)
            ->andReturn($record);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($record);

        $this->service->delete($userId, $recordId);
    }

    /**
     * abort() による HttpException（ステータスコード）を検証する共通アサーション。
     */
    private function assertAbort(callable $callback, int $status): void
    {
        try {
            $callback();
            $this->fail("Expected HttpException with status {$status} was not thrown.");
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }
}
