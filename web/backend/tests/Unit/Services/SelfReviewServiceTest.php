<?php

namespace Tests\Unit\Services;

use App\Models\KakeiboRecord;
use App\Models\SelfReview;
use App\Repositories\V1\Contracts\KakeiboRecordRepositoryInterface;
use App\Repositories\V1\Contracts\PostRepositoryInterface;
use App\Repositories\V1\Contracts\SelfReviewRepositoryInterface;
use App\Services\V1\KakeiboRecordService;
use App\Services\V1\SelfReviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithAbort;

/**
 * 単体テスト仕様書 2.2 SelfReviewService 対応テスト
 *
 * SelfReviewRepositoryInterface をモック化し、DB に依存せずビジネスロジックのみを検証する。
 *
 * KakeiboRecordService は readonly class のため Mockery で直接モック化できない
 * （readonly class を継承する非 readonly なモッククラスをPHPが許可しないため）。
 * そのため KakeiboRecordService 自体は実インスタンスとして生成し、その依存先である
 * KakeiboRecordRepositoryInterface をモック化することで間接的に振る舞いを制御する。
 *
 * create() / update() / delete() は DB::transaction() で包まれているため、
 * テスト実行環境に有効なDB接続（トランザクション開始・コミットが可能な状態）が必要。
 */
class SelfReviewServiceTest extends TestCase
{
    use InteractsWithAbort;

    private SelfReviewRepositoryInterface&MockInterface $repository;

    private KakeiboRecordRepositoryInterface&MockInterface $kakeiboRecordRepository;

    private SelfReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SelfReviewRepositoryInterface::class);
        $this->kakeiboRecordRepository = Mockery::mock(KakeiboRecordRepositoryInterface::class);

        $kakeiboRecordService = new KakeiboRecordService(
            $this->kakeiboRecordRepository,
            Mockery::mock(SelfReviewRepositoryInterface::class),
            Mockery::mock(PostRepositoryInterface::class),
        );

        $this->service = new SelfReviewService(
            $this->repository,
            $kakeiboRecordService
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
     * KakeiboRecordService::findOrFail() 内部で呼ばれる Repository::findById() をモックする。
     */
    private function mockFindById(int $recordId, int $userId): void
    {
        $this->kakeiboRecordRepository
            ->shouldReceive('findById')
            ->once()
            ->with($recordId)
            ->andReturn($this->makeRecord($recordId, $userId));
    }

    /**
     * KakeiboRecordService::findOrFailForUpdate() 内部で呼ばれる
     * Repository::findByIdForUpdate() をモックする。
     */
    private function mockFindByIdForUpdate(int $recordId, int $userId): void
    {
        $this->kakeiboRecordRepository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($recordId)
            ->andReturn($this->makeRecord($recordId, $userId));
    }

    /**
     * SSR-001: list: 一覧取得
     */
    #[Test]
    public function test_ss_r_001_list_calls_paginate_by_record_id(): void
    {
        $recordId = 10;
        $userId = 1;

        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->mockFindById($recordId, $userId);

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
    public function test_ss_r_002_list_parent_not_found_aborts_404(): void
    {
        $recordId = 999;
        $userId = 1;

        $this->kakeiboRecordRepository
            ->shouldReceive('findById')
            ->once()
            ->with($recordId)
            ->andReturn(null);

        $this->repository
            ->shouldNotReceive('paginateByRecordId');

        $this->assertAbort(
            fn () => $this->service->list($recordId, $userId),
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * SSR-003: create: 正常作成
     */
    #[Test]
    public function test_ss_r_003_create_calls_repository(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'reviewComment' => '良い買い物だった',
            'evaluation' => 3,
        ];

        $createdReview = Mockery::mock(SelfReview::class);

        $this->mockFindByIdForUpdate($recordId, $userId);

        $this->repository
            ->shouldReceive('existsByRecordId')
            ->once()
            ->with($recordId)
            ->andReturn(false);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'kakeibo_record_id' => $recordId,
                'review_comment' => '良い買い物だった',
                'evaluation' => 3,
            ])
            ->andReturn($createdReview);

        $result = $this->service->create($recordId, $userId, $validated);

        $this->assertSame($createdReview, $result);
    }

    /**
     * SSR-008: create: 既に自己レビューが存在する場合は 409
     */
    #[Test]
    public function test_ss_r_008_create_fails_when_review_exists(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'reviewComment' => '2件目',
            'evaluation' => 3,
        ];

        $this->mockFindByIdForUpdate($recordId, $userId);

        $this->repository
            ->shouldReceive('existsByRecordId')
            ->once()
            ->with($recordId)
            ->andReturn(true);

        $this->repository
            ->shouldNotReceive('create');

        $this->assertAbort(
            fn () => $this->service->create($recordId, $userId, $validated),
            Response::HTTP_CONFLICT
        );
    }

    /**
     * SSR-004: update: 正常更新
     */
    #[Test]
    public function test_ss_r_004_update_calls_repository(): void
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

        $this->mockFindByIdForUpdate($recordId, $userId);

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

        $result = $this->service->update($recordId, $id, $userId, $validated);

        $this->assertSame($review, $result);
    }

    /**
     * SSR-005: update: レビューが存在しない
     */
    #[Test]
    public function test_ss_r_005_update_not_found_aborts_404(): void
    {
        $recordId = 10;
        $id = 999;
        $userId = 1;

        $validated = [
            'reviewComment' => '更新後コメント',
            'evaluation' => 4,
        ];

        $this->mockFindByIdForUpdate($recordId, $userId);

        $this->repository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($id, $recordId)
            ->andReturn(null);

        $this->repository
            ->shouldNotReceive('update');

        $this->assertAbort(
            fn () => $this->service->update($recordId, $id, $userId, $validated),
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * SSR-006: delete: 正常削除
     */
    #[Test]
    public function test_ss_r_006_delete_calls_repository(): void
    {
        $recordId = 10;
        $id = 20;
        $userId = 1;

        $review = Mockery::mock(SelfReview::class)->makePartial();
        $review->id = $id;

        $this->mockFindByIdForUpdate($recordId, $userId);

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

        // Mockery expectation の検証（shouldReceive(...)->once()）が
        // このテストの主張であることを明示するための assertion。
        $this->assertTrue(true);
    }

    /**
     * SSR-007: delete: レビューが存在しない
     */
    #[Test]
    public function test_ss_r_007_delete_not_found_aborts_404(): void
    {
        $recordId = 10;
        $id = 999;
        $userId = 1;

        $this->mockFindByIdForUpdate($recordId, $userId);

        $this->repository
            ->shouldReceive('findByIdForUpdate')
            ->once()
            ->with($id, $recordId)
            ->andReturn(null);

        $this->repository
            ->shouldNotReceive('delete');

        $this->assertAbort(
            fn () => $this->service->delete($recordId, $id, $userId),
            Response::HTTP_NOT_FOUND
        );
    }
}
