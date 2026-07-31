<?php

namespace Tests\Unit\Services;

use App\Models\AiStatus;
use App\Models\KakeiboRecord;
use App\Models\Post;
use App\Repositories\V1\Contracts\KakeiboRecordRepositoryInterface;
use App\Repositories\V1\Contracts\PostRepositoryInterface;
use App\Repositories\V1\Contracts\SelfReviewRepositoryInterface;
use App\Services\V1\KakeiboRecordService;
use App\Services\V1\PostService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithAbort;

/**
 * 単体テスト仕様書 2.3 PostService 対応テスト
 *
 * PostRepositoryInterface をモック化し、DB に依存せずビジネスロジックのみを検証する。
 *
 * KakeiboRecordService は readonly class のため Mockery で直接モック化できない
 * （readonly class を継承する非 readonly なモッククラスをPHPが許可しないため）。
 * そのため KakeiboRecordService 自体は実インスタンスとして生成し、その依存先である
 * KakeiboRecordRepositoryInterface をモック化することで間接的に振る舞いを制御する。
 *
 * store() は DB::transaction() で包まれているため、テスト実行環境に
 * 有効なDB接続（トランザクション開始・コミットが可能な状態）が必要。
 * Repository はすべてモック化しているため、実テーブルへのクエリは発生しない。
 */
class PostServiceTest extends TestCase
{
    use InteractsWithAbort;

    private PostRepositoryInterface&MockInterface $repository;

    private KakeiboRecordRepositoryInterface&MockInterface $kakeiboRecordRepository;

    private PostService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(PostRepositoryInterface::class);
        $this->kakeiboRecordRepository = Mockery::mock(KakeiboRecordRepositoryInterface::class);

        $kakeiboRecordService = new KakeiboRecordService(
            $this->kakeiboRecordRepository,
            Mockery::mock(SelfReviewRepositoryInterface::class),
            Mockery::mock(PostRepositoryInterface::class),
        );

        $this->service = new PostService(
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
     * id, parent_id を持つ Post モックを作成する。
     *
     * PostRepositoryInterface::create() の戻り値型が Post のため、
     * stdClass ではなく Post のモックを返す必要がある。
     */
    private function makePost(int $id, ?int $parentId = null): Post&MockInterface
    {
        $post = Mockery::mock(Post::class)->makePartial();
        $post->id = $id;
        $post->parent_id = $parentId;

        return $post;
    }

    /**
     * SPS-001: list: 一覧取得
     */
    #[Test]
    public function test_sp_s_001_list_calls_find_by_record_id(): void
    {
        $recordId = 10;
        $userId = 1;

        $posts = new Collection([]);

        $this->mockFindById($recordId, $userId);

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
    public function test_sp_s_002_store_with_content_creates_user_and_ai_post(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'content' => 'アドバイスほしい',
            'parentId' => null,
        ];

        $userPost = Mockery::mock(Post::class)->makePartial();
        $userPost->id = 100;

        $this->mockFindByIdForUpdate($recordId, $userId);

        $this->repository
            ->shouldReceive('existsAiPostWithStatuses')
            ->once()
            ->with($recordId, [AiStatus::PENDING_ID, AiStatus::PROCESSING_ID])
            ->andReturn(false);

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
            ->andReturn($this->makePost(101, $userPost->id));

        $result = $this->service->store($recordId, $userId, $validated);

        $this->assertSame($userPost, $result['userPost']);
        $this->assertSame($userPost->id, $result['aiPost']->parent_id);
    }

    /**
     * SPS-003: store: content 省略でAIフィードバック要求のみ
     */
    #[Test]
    public function test_sp_s_003_store_without_content_creates_ai_post_only(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'content' => null,
            'parentId' => null,
        ];

        $aiPost = $this->makePost(200);

        $this->mockFindByIdForUpdate($recordId, $userId);

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
    public function test_sp_s_004_store_without_content_completed_exists_returns_409(): void
    {
        $this->assertConflictWhenAiPostExists();
    }

    /**
     * SPS-005: store: content 省略で既存pending AI投稿がある
     */
    #[Test]
    public function test_sp_s_005_store_without_content_pending_exists_returns_409(): void
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

        $this->mockFindByIdForUpdate($recordId, $userId);

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
        $this->assertSame(Response::HTTP_CONFLICT, $result['status']);
    }

    /**
     * SPS-006: store: content 省略で既存failed AI投稿のみ
     */
    #[Test]
    public function test_sp_s_006_store_without_content_failed_only_allows_retry(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'content' => null,
            'parentId' => null,
        ];

        $newAiPost = $this->makePost(301);

        $this->mockFindByIdForUpdate($recordId, $userId);

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
    public function test_sp_s_007_store_with_valid_parent_id_succeeds(): void
    {
        $recordId = 10;
        $userId = 1;
        $parentId = 50;

        $validated = [
            'content' => '返信です',
            'parentId' => $parentId,
        ];

        $userPost = $this->makePost(100);

        $this->mockFindByIdForUpdate($recordId, $userId);

        $this->repository
            ->shouldReceive('existsByIdAndRecordId')
            ->once()
            ->with($parentId, $recordId)
            ->andReturn(true);

        $this->repository
            ->shouldReceive('existsAiPostWithStatuses')
            ->once()
            ->with($recordId, [AiStatus::PENDING_ID, AiStatus::PROCESSING_ID])
            ->andReturn(false);

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
            ->andReturn($this->makePost(101, $userPost->id));

        $result = $this->service->store($recordId, $userId, $validated);

        $this->assertSame($userPost, $result['userPost']);
        $this->assertSame($userPost->id, $result['aiPost']->parent_id);
    }

    /**
     * SPS-008: store: parentId 指定で存在しない投稿
     */
    #[Test]
    public function test_sp_s_008_store_with_invalid_parent_id_aborts_404(): void
    {
        $recordId = 10;
        $userId = 1;
        $parentId = 999;

        $validated = [
            'content' => '返信です',
            'parentId' => $parentId,
        ];

        $this->mockFindByIdForUpdate($recordId, $userId);

        $this->repository
            ->shouldReceive('existsByIdAndRecordId')
            ->once()
            ->with($parentId, $recordId)
            ->andReturn(false);

        $this->repository
            ->shouldNotReceive('create');

        $this->assertAbort(
            fn () => $this->service->store($recordId, $userId, $validated),
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * SPS-009: store: userPost の parentId が aiPost に引き継がれる
     */
    #[Test]
    public function test_sp_s_009_ai_post_parent_id_inherits_user_post_id(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'content' => 'アドバイスほしい',
            'parentId' => null,
        ];

        $userPost = $this->makePost(555);

        $this->mockFindByIdForUpdate($recordId, $userId);

        $this->repository
            ->shouldReceive('existsAiPostWithStatuses')
            ->once()
            ->with($recordId, [AiStatus::PENDING_ID, AiStatus::PROCESSING_ID])
            ->andReturn(false);

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
            ->andReturnUsing(fn (array $data) => $this->makePost(999, $data['parent_id'] ?? null));

        $result = $this->service->store($recordId, $userId, $validated);

        $this->assertSame($userPost->id, $result['aiPost']->parent_id);
    }

    /**
     * SPS-010: store: content 有りで pending AI投稿がある場合は 409
     */
    #[Test]
    public function test_sp_s_010_store_with_content_pending_ai_returns_409(): void
    {
        $this->assertConflictWhenChatDuringPendingOrProcessing();
    }

    /**
     * SPS-011: store: content 有りで processing AI投稿がある場合は 409
     */
    #[Test]
    public function test_sp_s_011_store_with_content_processing_ai_returns_409(): void
    {
        $this->assertConflictWhenChatDuringPendingOrProcessing();
    }

    /**
     * SPS-010/011 共通: content 有りで pending/processing AI投稿がある場合は
     * repository への create が呼ばれず、409 のエラー配列が返る。
     */
    private function assertConflictWhenChatDuringPendingOrProcessing(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'content' => '追加の質問',
            'parentId' => null,
        ];

        $this->mockFindByIdForUpdate($recordId, $userId);

        $this->repository
            ->shouldReceive('existsAiPostWithStatuses')
            ->once()
            ->with($recordId, [
                AiStatus::PENDING_ID,
                AiStatus::PROCESSING_ID,
            ])
            ->andReturn(true);

        $this->repository
            ->shouldNotReceive('create');

        $result = $this->service->store($recordId, $userId, $validated);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(Response::HTTP_CONFLICT, $result['status']);
    }

    /**
     * SPS-012: store: content 有りで completed AI投稿のみの場合は追加チャット可能
     */
    #[Test]
    public function test_sp_s_012_store_with_content_completed_only_allows_chat(): void
    {
        $recordId = 10;
        $userId = 1;

        $validated = [
            'content' => '追加の質問',
            'parentId' => null,
        ];

        $userPost = $this->makePost(100);

        $this->mockFindByIdForUpdate($recordId, $userId);

        $this->repository
            ->shouldReceive('existsAiPostWithStatuses')
            ->once()
            ->with($recordId, [
                AiStatus::PENDING_ID,
                AiStatus::PROCESSING_ID,
            ])
            ->andReturn(false);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $userId,
                'kakeibo_record_id' => $recordId,
                'ai_status_id' => null,
                'parent_id' => null,
                'is_ai' => 0,
                'content' => '追加の質問',
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
            ->andReturn($this->makePost(101, $userPost->id));

        $result = $this->service->store($recordId, $userId, $validated);

        $this->assertSame($userPost, $result['userPost']);
        $this->assertNotNull($result['aiPost']);
    }
}
