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
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private ApiEndpoint $endpoint;


    protected User $user;

    protected int $recordId;

    protected int $categoryId;

    protected int $amountTypeId;

    protected int $pendingStatusId = 1;

    protected int $processingStatusId = 2;

    protected int $completedStatusId = 3;

    protected int $failedStatusId = 4;

    /**
     * 投稿作成テストに必要なユーザー、家計簿レコード、
     * AIステータスを準備する
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = new V1ApiEndpoint();

        // テスト用ユーザーを作成
        $this->user = User::create([
            'login_id' => 'testuser',
            'email' => 'test@example.com',
            'name' => 'テストユーザー',
            'password_hash' => Hash::make('password123'),
        ]);

        // 家計簿レコード作成に使用する収支区分を作成
        $amountType = new AmountType();
        $amountType->type_name = '支出';
        $amountType->save();

        // 家計簿レコード作成に使用するカテゴリを作成
        $category = KakeiboDefaultCategory::create([
            'amount_type_id' => $amountType->id,
            'category_name' => '食費',
        ]);

        $this->amountTypeId = $amountType->id;
        $this->categoryId = $category->id;

        // 投稿紐付け対象となる家計簿レコードを作成
        $record = KakeiboRecord::create([
            'user_id' => $this->user->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => $this->amountTypeId,
            'amount' => 1000,
            'details' => '昼食',
            'kakeibo_default_category_id' => $this->categoryId,
        ]);

        $this->recordId = $record->id;

        // ai_statuses はアプリ側でID固定参照されているため、IDを明示的に指定して作成する
        $pending = new AiStatus();
        $pending->id = $this->pendingStatusId;
        $pending->status_name = 'pending';
        $pending->save();

        $processing = new AiStatus();
        $processing->id = $this->processingStatusId;
        $processing->status_name = 'processing';
        $processing->save();

        $completed = new AiStatus();
        $completed->id = $this->completedStatusId;
        $completed->status_name = 'completed';
        $completed->save();

        $failed = new AiStatus();
        $failed->id = $this->failedStatusId;
        $failed->status_name = 'failed';
        $failed->save();

        // 認証済みユーザーとしてAPIを実行
        $this->actingAs($this->user);
    }

    /**
     * 投稿APIのエンドポイントを生成する
     */
    private function endpoint(int $recordId): string
    {
        return $this->endpoint->records() . '/' . $recordId . '/posts';
    }

    /** FPS-001 正常: ユーザー投稿+AI投稿作成 */
    #[Test]
    public function test_store_creates_user_post_and_ai_post(): void
    {
        // ユーザー投稿とAI投稿が作成されることを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'content' => 'アドバイスほしい',
        ]);

        // 作成結果とAI投稿の初期状態を確認
        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.userPost.content', 'アドバイスほしい')
            ->assertJsonPath('data.aiPost.aiStatus.statusName', 'pending');
    }

    /** FPS-002 正常: content 省略でAIフィードバック要求 */
    #[Test]
    public function test_store_requests_ai_feedback_without_content(): void
    {
        // content未指定の場合にAIフィードバックのみ作成されることを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'content' => null,
        ]);

        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.userPost', null)
            ->assertJsonPath('data.aiPost.aiStatus.statusName', 'pending');
    }

    /** FPS-003 正常: parentId 指定 */
    #[Test]
    public function test_store_with_parent_id(): void
    {
        // リプライ元となる投稿を作成
        $parent = Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'is_ai' => false,
            'content' => '最初の投稿',
        ]);

        // parentIdを指定して投稿を作成
        $response = $this->postJson($this->endpoint($this->recordId), [
            'content' => 'テスト',
            'parentId' => $parent->id,
        ]);

        // 親子関係が正しく設定されていることを確認
        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.userPost.parentId', $parent->id);

        $userPostId = $response->json('data.userPost.id');
        $response->assertJsonPath('data.aiPost.parentId', $userPostId);
    }

    /** FPS-004 正常: failed 状態のAI投稿がある場合に再試行可能 */
    #[Test]
    public function test_store_allows_retry_when_ai_post_failed(): void
    {
        // 失敗状態のAI投稿を作成
        Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'is_ai' => true,
            'ai_status_id' => $this->failedStatusId,
            'content' => null,
        ]);

        // 再度AI投稿要求が可能であることを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'content' => null,
        ]);

        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.aiPost.aiStatus.statusName', 'pending');
    }
    /** FPS-005 異常: content 省略で既存 completed AI投稿がある */
    #[Test]
    public function test_store_fails_when_ai_post_completed_exists(): void
    {
        // 完了済みのAI投稿を作成
        Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'is_ai' => true,
            'ai_status_id' => $this->completedStatusId,
            'content' => 'すでに完了',
        ]);

        // 既存の完了済みAI投稿がある場合は作成できないことを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'content' => null,
        ]);

        $response
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('message', 'AIフィードバックは既に生成中または生成済みです');
    }

    /** FPS-006 異常: content 省略で既存 pending AI投稿がある */
    #[Test]
    public function test_store_fails_when_ai_post_pending_exists(): void
    {
        // 処理待ち状態のAI投稿を作成
        Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'is_ai' => true,
            'ai_status_id' => $this->pendingStatusId,
            'content' => null,
        ]);

        // 既にAI生成待ちの場合は重複作成できないことを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'content' => null,
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT);
    }

    /** FPS-007 異常: content 省略で既存 processing AI投稿がある */
    #[Test]
    public function test_store_fails_when_ai_post_processing_exists(): void
    {
        // 処理中状態のAI投稿を作成
        Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'is_ai' => true,
            'ai_status_id' => $this->processingStatusId,
            'content' => null,
        ]);

        // AI生成処理中の場合は重複作成できないことを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'content' => null,
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT);
    }

    /** FPS-008 異常: parentId が存在しない投稿 */
    #[Test]
    public function test_store_fails_when_parent_id_not_found(): void
    {
        // 存在しない親投稿IDを指定
        $response = $this->postJson($this->endpoint($this->recordId), [
            'content' => 'テスト',
            'parentId' => 999,
        ]);

        // リプライ先が存在しない場合のエラーを確認
        $response
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJsonPath('message', 'リプライ先の投稿が見つかりません');
    }

    /** FPS-009 異常: parentId が別の recordId の投稿 */
    #[Test]
    public function test_store_fails_when_parent_id_belongs_to_other_record(): void
    {
        // 別レコードを作成
        $record2 = KakeiboRecord::create([
            'user_id' => $this->user->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => $this->amountTypeId,
            'amount' => 500,
            'details' => '別レコード',
            'kakeibo_default_category_id' => $this->categoryId,
        ]);

        // 別レコードに紐づく投稿を作成
        $otherPost = Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $record2->id,
            'is_ai' => false,
            'content' => '別レコードの投稿',
        ]);

        // 別レコードの投稿を親として指定した場合のエラーを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'content' => 'テスト',
            'parentId' => $otherPost->id,
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** FPS-010 異常: 他ユーザーの家計簿レコード */
    #[Test]
    public function test_store_fails_for_other_users_record(): void
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

        // 他ユーザーのレコードには投稿できないことを確認
        $response = $this->postJson($this->endpoint($otherRecord->id), [
            'content' => 'テスト',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** FPS-011 異常: 存在しない家計簿レコード */
    #[Test]
    public function test_store_fails_when_record_not_found(): void
    {
        // 存在しない家計簿レコードIDを指定
        $response = $this->postJson($this->endpoint(999), [
            'content' => 'テスト',
        ]);

        // レコード未存在エラーを確認
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** FPS-012 異常: content 3001文字 */
    #[Test]
    public function test_store_fails_when_content_too_long(): void
    {
        // 最大文字数を超える投稿内容を指定
        $response = $this->postJson($this->endpoint($this->recordId), [
            'content' => str_repeat('あ', 3001),
        ]);

        // バリデーションエラーを確認
        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['content']);
    }

    /** FPS-013 異常: 未認証 */
    #[Test]
    public function test_store_requires_authentication(): void
    {
        // 認証状態を解除
        auth()->logout();

        // 未認証状態で投稿APIを実行
        $response = $this->postJson($this->endpoint($this->recordId), [
            'content' => 'テスト',
        ]);

        // 認証エラーを確認
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}
