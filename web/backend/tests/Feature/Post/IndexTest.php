<?php

declare(strict_types=1);

namespace Tests\Feature\Post;

use App\Models\AiStatus;
use App\Models\AmountType;
use App\Models\KakeiboDefaultCategory;
use App\Models\KakeiboRecord;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    private ApiEndpoint $endpoint;


    protected User $user;

    protected int $recordId;

    protected int $categoryId;

    protected int $amountTypeId;

    protected int $pendingStatusId;

    protected int $processingStatusId;

    protected int $completedStatusId;

    protected int $failedStatusId;

    /**
     * 投稿一覧取得テストで利用するユーザー、家計簿レコード、
     * AIステータスを準備する
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = new V1ApiEndpoint();

        // API実行用の認証ユーザーを作成
        $this->user = User::create([
            'login_id' => 'testuser',
            'email' => 'test@example.com',
            'name' => 'テストユーザー',
            'password_hash' => Hash::make('password123'),
        ]);

        // 家計簿レコード作成用の収支区分を作成
        $amountType = new AmountType();
        $amountType->type_name = '支出';
        $amountType->save();

        // 家計簿レコード作成用のカテゴリを作成
        $category = KakeiboDefaultCategory::create([
            'amount_type_id' => $amountType->id,
            'category_name' => '食費',
        ]);

        $this->amountTypeId = $amountType->id;
        $this->categoryId = $category->id;

        // 投稿一覧取得対象となる家計簿レコードを作成
        $record = KakeiboRecord::create([
            'user_id' => $this->user->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => $this->amountTypeId,
            'amount' => 1000,
            'details' => '昼食',
            'kakeibo_default_category_id' => $this->categoryId,
        ]);

        $this->recordId = $record->id;

        // AI投稿状態用のステータスを作成
        $pending = new AiStatus();
        $pending->status_name = 'pending';
        $pending->save();
        $this->pendingStatusId = $pending->id;

        $processing = new AiStatus();
        $processing->status_name = 'processing';
        $processing->save();
        $this->processingStatusId = $processing->id;

        $completed = new AiStatus();
        $completed->status_name = 'completed';
        $completed->save();
        $this->completedStatusId = $completed->id;

        $failed = new AiStatus();
        $failed->status_name = 'failed';
        $failed->save();
        $this->failedStatusId = $failed->id;

        // 認証済みユーザーとしてAPIを実行
        $this->actingAs($this->user);
    }

    /**
     * 投稿一覧取得APIのエンドポイントを生成する
     */
    private function endpoint(int $recordId): string
    {
        return $this->endpoint->records() . '/' . $recordId . '/posts';
    }

    /**
     * 作成日時を指定した投稿データを作成する
     */
    private function createPostAt(array $attributes, string $createdAt): Post
    {
        // 投稿を作成
        $post = Post::create($attributes);

        // 作成日時を指定値へ変更
        DB::table('posts')->where('id', $post->id)->update(['created_at' => $createdAt]);

        return $post->fresh();
    }

    /** FPI-001 正常: 一覧取得（created_at 昇順） */
    #[Test]
    public function test_index_returns_posts_in_ascending_order(): void
    {
        // ユーザー投稿を作成
        $userPost = $this->createPostAt([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'is_ai' => false,
            'content' => 'アドバイスほしい',
        ], now()->subMinutes(3)->toDateTimeString());

        // AI投稿を作成（作成日時順確認用）
        $aiPost1 = $this->createPostAt([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'is_ai' => true,
            'ai_status_id' => $this->completedStatusId,
            'content' => 'アドバイスその1',
        ], now()->subMinutes(2)->toDateTimeString());

        $aiPost2 = $this->createPostAt([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'is_ai' => true,
            'ai_status_id' => $this->completedStatusId,
            'content' => 'アドバイスその2',
        ], now()->subMinute()->toDateTimeString());

        // 投稿一覧取得APIを実行
        $response = $this->getJson($this->endpoint($this->recordId));

        // 件数と取得順を確認
        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(3, 'data');

        $ids = $response->json('data.*.id');
        $this->assertSame([$userPost->id, $aiPost1->id, $aiPost2->id], $ids);
    }

    /** FPI-002 正常: aiStatus の構造が正しい */
    #[Test]
    public function test_index_ai_status_structure(): void
    {
        // AI投稿を作成
        Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'is_ai' => true,
            'ai_status_id' => $this->completedStatusId,
            'content' => 'アドバイス',
        ]);

        // AIステータス情報が正しく返却されることを確認
        $response = $this->getJson($this->endpoint($this->recordId));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.0.aiStatus.id', $this->completedStatusId)
            ->assertJsonPath('data.0.aiStatus.statusName', 'completed');
    }

    /** FPI-003 正常: pending のAI投稿は content が null */
    #[Test]
    public function test_index_pending_ai_post_has_null_content(): void
    {
        // 処理待ち状態のAI投稿を作成
        Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'is_ai' => true,
            'ai_status_id' => $this->pendingStatusId,
            'content' => null,
        ]);

        // pending状態では本文がnullであることを確認
        $response = $this->getJson($this->endpoint($this->recordId));

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.0.content', null);
    }

    /** FPI-004 正常: 投稿0件 */
    #[Test]
    public function test_index_returns_empty_when_no_posts(): void
    {
        // 投稿が存在しない場合に空配列が返ることを確認
        $response = $this->getJson($this->endpoint($this->recordId));

        $response->assertStatus(Response::HTTP_OK)->assertJsonCount(0, 'data');
    }

    /** FPI-005 異常: 他ユーザーの家計簿レコード */
    #[Test]
    public function test_index_fails_when_record_belongs_to_other_user(): void
    {
        // 別ユーザーを作成
        $otherUser = User::create([
            'login_id' => 'otheruser',
            'email' => 'other@example.com',
            'name' => '別ユーザー',
            'password_hash' => Hash::make('password123'),
        ]);

        // 他ユーザー所有の家計簿レコードを作成
        $otherRecord = KakeiboRecord::create([
            'user_id' => $otherUser->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => $this->amountTypeId,
            'amount' => 2000,
            'details' => '他ユーザー',
            'kakeibo_default_category_id' => $this->categoryId,
        ]);

        // 権限エラーになることを確認
        $response = $this->getJson($this->endpoint($otherRecord->id));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** FPI-006 異常: 存在しない家計簿レコード */
    #[Test]
    public function test_index_fails_when_record_not_found(): void
    {
        // 存在しないレコードIDを指定
        $response = $this->getJson($this->endpoint(999));

        // Not Foundになることを確認
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** FPI-007 異常: 未認証 */
    #[Test]
    public function test_index_requires_authentication(): void
    {
        // 認証状態を解除
        auth()->logout();

        // 未認証状態でAPIを実行
        $response = $this->getJson($this->endpoint($this->recordId));

        // 認証エラーになることを確認
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}
