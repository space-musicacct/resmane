<?php

namespace Tests\Unit\Services;

use App\Models\SelfReview;
use App\Repositories\V1\Contracts\SelfReviewRepositoryInterface;
use App\Services\V1\KakeiboRecordService;
use App\Services\V1\SelfReviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithAbort;

/**
 * 単体テスト仕様書 2.2 SelfReviewService 対応テスト
 *
 * Repository と KakeiboRecordService をモック化し、DB に依存せず
 * ビジネスロジックのみを検証する。
 *
 * create() / update() / delete() は DB::transaction() で包まれているため、
 * テスト実行環境に有効なDB接続（トランザクション開始・コミットが可能な状態）が必要。
 */
class SelfReviewServiceTest extends TestCase
{
    use InteractsWithAbort;

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
        $recordId = 10;
        $userId = 1;

        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->kakeiboRecordService
            ->shouldReceive('findOrFail')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('paginateByRecordId')
            ->once()
            ->with($recordId)
            ->andReturn($paginator);

        $result = $this->service->list($recordId, $userId);

        $this->assertSame($paginator, $result);
    }

    /**
     * SSR-002: list: 親レコードが存在しない
     */
    #[Test]
    public function SSR_002_親レコードが存在しない場合は404になる(): void
    {
        $recordId = 999;
        $userId = 1;

        $this->kakeiboRecordService
            ->shouldReceive('findOrFail')
            ->once()
            ->with($recordId, $userId)
            ->andThrow(new HttpException(404));

        $this->repository
            ->shouldNotReceive('paginateByRecordId');

        $this->assertAbort(
            fn () => $this->service->list($recordId, $userId),
            404
        );
    }

    /**
     * SSR-003: create: 正常作成
     */
    #[Test]
    public function SSR_003_createでRepositoryのcreateが呼ばれる(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'reviewComment' => '良い買い物だった',
            'evaluation' => 3,
        ];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'kakeibo_record_id' => $recordId,
                'review_comment' => '良い買い物だった',
                'evaluation' => 3,
            ])
            ->andReturn(Mockery::mock(SelfReview::class));

        $this->service->create($recordId, $userId, $validated);
    }

    /**
     * SSR-004: update: 正常更新
     */
    #[Test]
    public function SSR_004_updateでRepositoryのupdateが呼ばれる(): void
    {
        $recordId = 10;
        $id = 20;
        $userId = 1;

        $review = Mockery::mock(SelfReview::class)->makePartial();
        $review->id = $id;

        $validated = [
            'reviewComment' => '更新後コメント',
            'evaluation' => 4,
        ];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($id, $recordId)
            ->andReturn($review);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($review, [
                'review_comment' => '更新後コメント',
                'evaluation' => 4,
            ])
            ->andReturn($review);

        $this->service->update($recordId, $id, $userId, $validated);
    }

    /**
     * SSR-005: update: レビューが存在しない
     */
    #[Test]
    public function SSR_005_updateでレビューが存在しない場合は404になる(): void
    {
        $recordId = 10;
        $id = 999;
        $userId = 1;

        $validated = [
            'reviewComment' => '更新後コメント',
            'evaluation' => 4,
        ];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($id, $recordId)
            ->andReturn(null);

        $this->repository
            ->shouldNotReceive('update');

        $this->assertAbort(
            fn () => $this->service->update($recordId, $id, $userId, $validated),
            404
        );
    }

    /**
     * SSR-006: delete: 正常削除
     */
    #[Test]
    public function SSR_006_deleteでRepositoryのdeleteが呼ばれる(): void
    {
        $recordId = 10;
        $id = 20;
        $userId = 1;

        $review = Mockery::mock(SelfReview::class)->makePartial();
        $review->id = $id;

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($id, $recordId)
            ->andReturn($review);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($review);

        $this->service->delete($recordId, $id, $userId);
    }

    /**
     * SSR-007: delete: レビューが存在しない
     */
    #[Test]
    public function SSR_007_deleteでレビューが存在しない場合は404になる(): void
    {
        $recordId = 10;
        $id = 999;
        $userId = 1;

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($id, $recordId)
            ->andReturn(null);

        $this->repository
            ->shouldNotReceive('delete');

        $this->assertAbort(
            fn () => $this->service->delete($recordId, $id, $userId),
            404
        );
    }

}
