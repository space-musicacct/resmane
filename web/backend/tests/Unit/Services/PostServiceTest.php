<?php

namespace Tests\Unit\Services;

use App\Models\AiStatus;
use App\Models\Post;
use App\Repositories\V1\Contracts\PostRepositoryInterface;
use App\Services\V1\KakeiboRecordService;
use App\Services\V1\PostService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * 単体テスト仕様書 2.3 PostService 対応テスト
 *
 * Repository と KakeiboRecordService をモック化し、DB に依存せず
 * ビジネスロジックのみを検証する。
 *
 * store() は DB::transaction() で包まれているため、テスト実行環境に
 * 有効なDB接続（トランザクション開始・コミットが可能な状態）が必要。
 * Repository・KakeiboRecordService 自体はモック化しているため、
 * 実テーブルへのクエリは発生しない。
 */
class PostServiceTest extends TestCase
{
    private PostRepositoryInterface&MockInterface $repository;
    private KakeiboRecordService&MockInterface $kakeiboRecordService;
    private PostService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(PostRepositoryInterface::class);
        $this->kakeiboRecordService = Mockery::mock(KakeiboRecordService::class);
        $this->service = new PostService(
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
     * SPS-001: list: 一覧取得
     */
    #[Test]
    public function SPS_001_一覧取得でRepositoryのfindByRecordIdが呼ばれる(): void
    {
        $recordId = 10;
        $userId = 1;

        $posts = new Collection([]);

        $this->kakeiboRecordService
            ->shouldReceive('findOrFail')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('findByRecordId')
            ->once()
            ->with($recordId)
            ->andReturn($posts);

        $result = $this->service->list($recordId, $userId);

        $this->assertSame($posts, $result);
    }

    /**
     * SPS-002: store: content 有りでユーザー投稿+AI投稿作成
     */
    #[Test]
    public function SPS_002_content有りでユーザー投稿とAI投稿が作成される(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'content' => 'アドバイスほしい',
            'parentId' => null,
        ];

        $userPost = Mockery::mock(Post::class)->makePartial();
        $userPost->id = 100;

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $userId,
                'kakeibo_record_id' => $recordId,
                'ai_status_id' => null,
                'parent_id' => null,
                'is_ai' => 0,
                'content' => 'アドバイスほしい',
            ])
            ->andReturn($userPost);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $userId,
                'kakeibo_record_id' => $recordId,
                'ai_status_id' => AiStatus::PENDING_ID,
                'parent_id' => $userPost->id,
                'is_ai' => 1,
                'content' => null,
            ])
            ->andReturn((object) ['id' => 101, 'parent_id' => $userPost->id]);

        $result = $this->service->store($recordId, $userId, $validated);

        $this->assertSame($userPost, $result['userPost']);
        $this->assertSame($userPost->id, $result['aiPost']->parent_id);
    }

    /**
     * SPS-003: store: content 省略でAIフィードバック要求のみ
     */
    #[Test]
    public function SPS_003_content省略で既存AI投稿なしの場合はAIフィードバック要求のみ作成される(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'content' => null,
            'parentId' => null,
        ];

        $aiPost = (object) ['id' => 200, 'parent_id' => null];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('existsAiPostWithStatuses')
            ->once()
            ->with($recordId, [
                AiStatus::PENDING_ID,
                AiStatus::PROCESSING_ID,
                AiStatus::COMPLETED_ID,
            ])
            ->andReturn(false);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $userId,
                'kakeibo_record_id' => $recordId,
                'ai_status_id' => AiStatus::PENDING_ID,
                'parent_id' => null,
                'is_ai' => 1,
                'content' => null,
            ])
            ->andReturn($aiPost);

        $result = $this->service->store($recordId, $userId, $validated);

        $this->assertNull($result['userPost']);
        $this->assertSame($aiPost, $result['aiPost']);
    }

    /**
     * SPS-004: store: content 省略で既存completed AI投稿がある
     */
    #[Test]
    public function SPS_004_content省略で既存completedAI投稿がある場合は409を返す(): void
    {
        $this->assertConflictWhenAiPostExists();
    }

    /**
     * SPS-005: store: content 省略で既存pending AI投稿がある
     */
    #[Test]
    public function SPS_005_content省略で既存pendingAI投稿がある場合は409を返す(): void
    {
        $this->assertConflictWhenAiPostExists();
    }

    /**
     * SPS-004/005 共通: 既存AI投稿（pending/processing/completed）がある場合は
     * repository への create が呼ばれず、409 のエラー配列が返る。
     */
    private function assertConflictWhenAiPostExists(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'content' => null,
            'parentId' => null,
        ];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('existsAiPostWithStatuses')
            ->once()
            ->with($recordId, [
                AiStatus::PENDING_ID,
                AiStatus::PROCESSING_ID,
                AiStatus::COMPLETED_ID,
            ])
            ->andReturn(true);

        $this->repository
            ->shouldNotReceive('create');

        $result = $this->service->store($recordId, $userId, $validated);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(409, $result['status']);
    }

    /**
     * SPS-006: store: content 省略で既存failed AI投稿のみ
     */
    #[Test]
    public function SPS_006_content省略で既存failedAI投稿のみの場合は再試行として新規作成される(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'content' => null,
            'parentId' => null,
        ];

        $newAiPost = (object) ['id' => 301, 'parent_id' => null];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        // failed は existsAiPostWithStatuses の対象ステータスに含まれないため false
        $this->repository
            ->shouldReceive('existsAiPostWithStatuses')
            ->once()
            ->with($recordId, [
                AiStatus::PENDING_ID,
                AiStatus::PROCESSING_ID,
                AiStatus::COMPLETED_ID,
            ])
            ->andReturn(false);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $userId,
                'kakeibo_record_id' => $recordId,
                'ai_status_id' => AiStatus::PENDING_ID,
                'parent_id' => null,
                'is_ai' => 1,
                'content' => null,
            ])
            ->andReturn($newAiPost);

        $result = $this->service->store($recordId, $userId, $validated);

        $this->assertNull($result['userPost']);
        $this->assertSame($newAiPost, $result['aiPost']);
    }

    /**
     * SPS-007: store: parentId 指定で存在する投稿
     */
    #[Test]
    public function SPS_007_parentId指定で存在する投稿の場合は正常に投稿作成される(): void
    {
        $recordId = 10;
        $userId = 1;
        $parentId = 50;

        $validated = [
            'content' => '返信です',
            'parentId' => $parentId,
        ];

        $userPost = (object) ['id' => 100];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('existsByIdAndRecordId')
            ->once()
            ->with($parentId, $recordId)
            ->andReturn(true);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $userId,
                'kakeibo_record_id' => $recordId,
                'ai_status_id' => null,
                'parent_id' => $parentId,
                'is_ai' => 0,
                'content' => '返信です',
            ])
            ->andReturn($userPost);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $userId,
                'kakeibo_record_id' => $recordId,
                'ai_status_id' => AiStatus::PENDING_ID,
                'parent_id' => $userPost->id,
                'is_ai' => 1,
                'content' => null,
            ])
            ->andReturn((object) ['id' => 101, 'parent_id' => $userPost->id]);

        $result = $this->service->store($recordId, $userId, $validated);

        $this->assertSame($userPost, $result['userPost']);
        $this->assertSame($userPost->id, $result['aiPost']->parent_id);
    }

    /**
     * SPS-008: store: parentId 指定で存在しない投稿
     */
    #[Test]
    public function SPS_008_parentId指定で存在しない投稿の場合は404になる(): void
    {
        $recordId = 10;
        $userId = 1;
        $parentId = 999;

        $validated = [
            'content' => '返信です',
            'parentId' => $parentId,
        ];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('existsByIdAndRecordId')
            ->once()
            ->with($parentId, $recordId)
            ->andReturn(false);

        $this->repository
            ->shouldNotReceive('create');

        $this->assertAbort(
            fn () => $this->service->store($recordId, $userId, $validated),
            404
        );
    }

    /**
     * SPS-009: store: userPost の parentId が aiPost に引き継がれる
     */
    #[Test]
    public function SPS_009_userPostのIDがaiPostのparent_idに引き継がれる(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'content' => 'アドバイスほしい',
            'parentId' => null,
        ];

        $userPost = (object) ['id' => 555];

        $this->kakeiboRecordService
            ->shouldReceive('findOrFailForUpdate')
            ->once()
            ->with($recordId, $userId);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $userId,
                'kakeibo_record_id' => $recordId,
                'ai_status_id' => null,
                'parent_id' => null,
                'is_ai' => 0,
                'content' => 'アドバイスほしい',
            ])
            ->andReturn($userPost);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) use ($userPost) {
                return ($data['parent_id'] ?? null) === $userPost->id;
            }))
            ->andReturnUsing(fn (array $data) => (object) $data);

        $result = $this->service->store($recordId, $userId, $validated);

        $this->assertSame($userPost->id, $result['aiPost']->parent_id);
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
