<?php

namespace Tests\Unit\Services;

use App\Repositories\Contracts\SelfReviewRepositoryInterface;
use App\Services\KakeiboRecordService;
use App\Services\SelfReviewService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * 単体テスト仕様書 2.2 SelfReviewService 対応テスト
 *
 * Repository と KakeiboRecordService をモック化し、DB に依存せず
 * ビジネスロジックのみを検証する。
 */
class SelfReviewServiceTest extends TestCase
{
    private SelfReviewRepositoryInterface&MockInterface $repository;
    private KakeiboRecordService&MockInterface $kakeiboRecordService;
    private SelfReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SelfReviewRepositoryInterface::class);
        $this->kakeiboRecordService = Mockery::mock(KakeiboRecordService::class);
        $this->service = new SelfReviewService(
            $this->repository,
            $this->kakeiboRecordService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * SSR-001: list: 一覧取得
     */
    #[Test]
    public function SSR_001_一覧取得でRepositoryのpaginateByRecordIdが呼ばれる(): void
    {
        $userId = 1;
        $recordId = 10;

        $record = (object) ['id' => $recordId, 'user_id' => $userId];
        $paginator = Mockery::mock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);

        $this->kakeiboRecordService
            ->shouldReceive('findOrFail')
            ->once()
            ->with($userId, $recordId)
            ->andReturn($record);

        $this->repository
            ->shouldReceive('paginateByRecordId')
            ->once()
            ->with($recordId)
            ->andReturn($paginator);

        $result = $this->service->list($userId, $recordId);

        $this->assertSame($paginator, $result);
    }

    /**
     * SSR-002: list: 親レコードが存在しない
     */
    #[Test]
    public function SSR_002_親レコードが存在しない場合は404になる(): void
    {
        $userId = 1;
        $recordId = 999;

        $this->kakeiboRecordService
            ->shouldReceive('findOrFail')
            ->once()
            ->with($userId, $recordId)
            ->andThrow(new HttpException(404));

        $this->repository
            ->shouldNotReceive('paginateByRecordId');

        $this->assertAbort(
            fn () => $this->service->list($userId, $recordId),
            404
        );
    }

    /**
     * SSR-003: create: 正常作成
     */
    #[Test]
    public function SSR_003_createでRepositoryのcreateが呼ばれる(): void
    {
        $userId = 1;
        $recordId = 10;

        $record = (object) ['id' => $recordId, 'user_id' => $userId];

        $validated = [
            'reviewComment' => '良い買い物だった',
            'evaluation' => 3,
        ];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFail')
            ->once()
            ->with($userId, $recordId)
            ->andReturn($record);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) use ($recordId) {
                return ($data['kakeibo_record_id'] ?? null) === $recordId
                    && ($data['review_comment'] ?? null) === '良い買い物だった'
                    && ($data['evaluation'] ?? null) === 3;
            }))
            ->andReturn((object) []);

        $this->service->create($userId, $recordId, $validated);
    }

    /**
     * SSR-004: update: 正常更新
     */
    #[Test]
    public function SSR_004_updateでRepositoryのupdateが呼ばれる(): void
    {
        $userId = 1;
        $recordId = 10;
        $reviewId = 20;

        $record = (object) ['id' => $recordId, 'user_id' => $userId];
        $review = (object) ['id' => $reviewId, 'kakeibo_record_id' => $recordId];

        $validated = [
            'reviewComment' => '更新後コメント',
            'evaluation' => 4,
        ];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFail')
            ->once()
            ->with($userId, $recordId)
            ->andReturn($record);

        $this->repository
            ->shouldReceive('findOrFail')
            ->once()
            ->with($recordId, $reviewId)
            ->andReturn($review);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($review, Mockery::type('array'))
            ->andReturn($review);

        $this->service->update($userId, $recordId, $reviewId, $validated);
    }

    /**
     * SSR-005: update: レビューが存在しない
     */
    #[Test]
    public function SSR_005_updateでレビューが存在しない場合は404になる(): void
    {
        $userId = 1;
        $recordId = 10;
        $reviewId = 999;

        $record = (object) ['id' => $recordId, 'user_id' => $userId];

        $validated = [
            'reviewComment' => '更新後コメント',
            'evaluation' => 4,
        ];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFail')
            ->once()
            ->with($userId, $recordId)
            ->andReturn($record);

        $this->repository
            ->shouldReceive('findOrFail')
            ->once()
            ->with($recordId, $reviewId)
            ->andReturn(null);

        $this->repository
            ->shouldNotReceive('update');

        $this->assertAbort(
            fn () => $this->service->update($userId, $recordId, $reviewId, $validated),
            404
        );
    }

    /**
     * SSR-006: delete: 正常削除
     */
    #[Test]
    public function SSR_006_deleteでRepositoryのdeleteが呼ばれる(): void
    {
        $userId = 1;
        $recordId = 10;
        $reviewId = 20;

        $record = (object) ['id' => $recordId, 'user_id' => $userId];
        $review = (object) ['id' => $reviewId, 'kakeibo_record_id' => $recordId];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFail')
            ->once()
            ->with($userId, $recordId)
            ->andReturn($record);

        $this->repository
            ->shouldReceive('findOrFail')
            ->once()
            ->with($recordId, $reviewId)
            ->andReturn($review);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($review);

        $this->service->delete($userId, $recordId, $reviewId);
    }

    /**
     * SSR-007: delete: レビューが存在しない
     */
    #[Test]
    public function SSR_007_deleteでレビューが存在しない場合は404になる(): void
    {
        $userId = 1;
        $recordId = 10;
        $reviewId = 999;

        $record = (object) ['id' => $recordId, 'user_id' => $userId];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFail')
            ->once()
            ->with($userId, $recordId)
            ->andReturn($record);

        $this->repository
            ->shouldReceive('findOrFail')
            ->once()
            ->with($recordId, $reviewId)
            ->andReturn(null);

        $this->repository
            ->shouldNotReceive('delete');

        $this->assertAbort(
            fn () => $this->service->delete($userId, $recordId, $reviewId),
            404
        );
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
