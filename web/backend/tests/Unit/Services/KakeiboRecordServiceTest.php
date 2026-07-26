<?php

namespace Tests\Unit\Services;

use App\Models\KakeiboRecord;
use App\Repositories\V1\Contracts\KakeiboRecordRepositoryInterface;
use App\Repositories\V1\Contracts\PostRepositoryInterface;
use App\Repositories\V1\Contracts\SelfReviewRepositoryInterface;
use App\Services\V1\KakeiboRecordService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithAbort;

/**
 * 単体テスト仕様書 2.1 KakeiboRecordService 対応テスト
 *
 * Repository群をモック化し、DB に依存せずビジネスロジックのみを検証する。
 *
 * update() / delete() は DB::transaction() で包まれているため、
 * テスト実行環境に有効なDB接続（トランザクション開始・コミットが可能な状態）が必要。
 */
class KakeiboRecordServiceTest extends TestCase
{
    use InteractsWithAbort;

    private KakeiboRecordRepositoryInterface&MockInterface $repository;
    private SelfReviewRepositoryInterface&MockInterface $selfReviewRepository;
    private PostRepositoryInterface&MockInterface $postRepository;
    private KakeiboRecordService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(KakeiboRecordRepositoryInterface::class);
        $this->selfReviewRepository = Mockery::mock(SelfReviewRepositoryInterface::class);
        $this->postRepository = Mockery::mock(PostRepositoryInterface::class);

        $this->service = new KakeiboRecordService(
            $this->repository,
            $this->selfReviewRepository,
            $this->postRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * id, user_id を持つ KakeiboRecord モックを作成する。
     */
    private function makeRecord(int $id, int $userId): KakeiboRecord&MockInterface
    {
        $record = Mockery::mock(KakeiboRecord::class)->makePartial();
        $record->id = $id;
        $record->user_id = $userId;

        return $record;
    }

    /**
     * SKR-001: list: 一覧取得が正しく動作する
     */
    #[Test]
    public function test_SKR_001_list_returns_records(): void
    {
        $userId = 1;
        $sortOrder = 'desc';
        $filters = ['from' => '2026-06-01'];
        $perPage = 20;

        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->repository
            ->shouldReceive('paginateByUserId')
            ->once()
            ->with($userId, $sortOrder, $filters, $perPage)
            ->andReturn($paginator);

        $this->repository
            ->shouldReceive('sumByType')
            ->once()
            ->with($userId, 2, $filters)
            ->andReturn(300000);

        $this->repository
            ->shouldReceive('sumByType')
            ->once()
            ->with($userId, 1, $filters)
            ->andReturn(150000);

        $result = $this->service->list($userId, $sortOrder, $filters, $perPage);

        $this->assertSame($paginator, $result['records']);
        $this->assertSame(300000, $result['totalIncome']);
        $this->assertSame(150000, $result['totalExpense']);
    }

    /**
     * SKR-002: findOrFail: 正常取得
     */
    #[Test]
    public function test_SKR_002_findOrFail_returns_record(): void
    {
        $id = 10;
        $userId = 1;

        $record = $this->makeRecord($id, $userId);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with($id)
            ->andReturn($record);

        $result = $this->service->findOrFail($id, $userId);

        $this->assertSame($record, $result);
    }

    /**
     * SKR-003: findOrFail: レコードが存在しない
     */
    #[Test]
    public function test_SKR_003_findOrFail_not_found_aborts_404(): void
    {
        $id = 999;
        $userId = 1;

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with($id)
            ->andReturn(null);

        $this->assertAbort(
            fn () => $this->service->findOrFail($id, $userId),
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * SKR-004: findOrFail: 他ユーザーのレコード
     */
    #[Test]
    public function test_SKR_004_findOrFail_other_user_aborts_403(): void
    {
        $id = 10;
        $userId = 1;

        $record = $this->makeRecord($id, 999);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with($id)
            ->andReturn($record);

        $this->assertAbort(
            fn () => $this->service->findOrFail($id, $userId),
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * SKR-005: findOrFailForUpdate: 正常取得
     */
    #[Test]
    public function test_SKR_005_findOrFailForUpdate_returns_record(): void
    {
        $id = 10;
        $userId = 1;

        $record = $this->makeRecord($id, $userId);

        $this->repository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($id)
            ->andReturn($record);

        $result = $this->service->findOrFailForUpdate($id, $userId);

        $this->assertSame($record, $result);
    }

    /**
     * SKR-006: findOrFailForUpdate: レコードが存在しない
     */
    #[Test]
    public function test_SKR_006_findOrFailForUpdate_not_found_aborts_404(): void
    {
        $id = 999;
        $userId = 1;

        $this->repository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($id)
            ->andReturn(null);

        $this->assertAbort(
            fn () => $this->service->findOrFailForUpdate($id, $userId),
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * SKR-007: findOrFailForUpdate: 他ユーザーのレコード
     */
    #[Test]
    public function test_SKR_007_findOrFailForUpdate_other_user_aborts_403(): void
    {
        $id = 10;
        $userId = 1;

        $record = $this->makeRecord($id, 999);

        $this->repository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($id)
            ->andReturn($record);

        $this->assertAbort(
            fn () => $this->service->findOrFailForUpdate($id, $userId),
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * SKR-008: create: 正常作成
     */
    #[Test]
    public function test_SKR_008_create_calls_repository_with_snake_case(): void
    {
        $userId = 1;

        $validated = [
            'purchaseDate' => '2026-07-01',
            'amountTypeId' => 1,
            'amount' => 1000,
            'details' => 'テスト',
            'kakeiboDefaultCategoryId' => 1,
        ];

        $createdRecord = $this->makeRecord(100, $userId);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $userId,
                'purchase_date' => '2026-07-01',
                'amount_type_id' => 1,
                'amount' => 1000,
                'details' => 'テスト',
                'kakeibo_default_category_id' => 1,
            ])
            ->andReturn($createdRecord);

        $result = $this->service->create($userId, $validated);

        $this->assertSame($createdRecord, $result);
    }

    /**
     * SKR-009: create: purchaseDate 省略時に今日の日付が設定される
     */
    #[Test]
    public function test_SKR_009_create_defaults_purchaseDate_to_today(): void
    {
        $userId = 1;
        $today = now()->toDateString();

        $validated = [
            'amountTypeId' => 1,
            'amount' => 1000,
            'details' => 'テスト',
            'kakeiboDefaultCategoryId' => 1,
        ];

        $createdRecord = $this->makeRecord(100, $userId);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $data) => ($data['purchase_date'] ?? null) === $today))
            ->andReturn($createdRecord);

        $result = $this->service->create($userId, $validated);

        $this->assertSame($createdRecord, $result);
    }

    /**
     * SKR-010: update: 正常更新
     */
    #[Test]
    public function test_SKR_010_update_calls_repository(): void
    {
        $id = 10;
        $userId = 1;

        $record = $this->makeRecord($id, $userId);
        $record->purchase_date = '2026-07-01';
        $record->amount_type_id = 1;
        $record->amount = 1000;
        $record->kakeibo_default_category_id = 1;

        $validated = [
            'purchaseDate' => '2026-07-02',
            'amountTypeId' => 1,
            'amount' => 2000,
            'details' => '更新後',
            'kakeiboDefaultCategoryId' => 2,
        ];

        $this->repository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($id)
            ->andReturn($record);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($record, [
                'purchase_date' => '2026-07-02',
                'amount_type_id' => 1,
                'amount' => 2000,
                'details' => '更新後',
                'kakeibo_default_category_id' => 2,
            ])
            ->andReturn($record);

        $result = $this->service->update($id, $userId, $validated);

        $this->assertSame($record, $result);
    }

    /**
     * SKR-011: delete: 正常削除
     */
    #[Test]
    public function test_SKR_011_delete_calls_repository_and_deletes_related(): void
    {
        $id = 10;
        $userId = 1;

        $record = $this->makeRecord($id, $userId);

        $this->repository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($id)
            ->andReturn($record);

        $this->selfReviewRepository
            ->shouldReceive('deleteByRecordIds')
            ->once()
            ->with(Mockery::on(fn ($ids) => $ids->all() === [$record->id]));

        $this->postRepository
            ->shouldReceive('deleteByRecordIds')
            ->once()
            ->with(Mockery::on(fn ($ids) => $ids->all() === [$record->id]));

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($record);

        $this->service->delete($id, $userId);

        // delete() は void を返すため、Mockery expectation の検証が
        // このテストの主張であることを明示するための assertion。
        $this->assertTrue(true);
    }

}
