<?php

declare(strict_types=1);

namespace Tests\Feature\SelfReview;

use App\Models\AiStatus;
use App\Models\AmountType;
use App\Models\KakeiboDefaultCategory;
use App\Models\KakeiboRecord;
use App\Models\Post;
use App\Models\SelfReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/records';

    protected User $user;

    protected int $recordId;

    protected int $reviewId;

    protected int $categoryId;

    protected int $amountTypeId;

    /**
     * 自己レビュー削除テストで使用するユーザー、家計簿レコード、レビューを準備する
     */
    protected function setUp(): void
    {
        parent::setUp();

        // API実行用の認証済みユーザーを作成
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

        // 削除対象となる家計簿レコードを作成
        $record = KakeiboRecord::create([
            'user_id' => $this->user->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => $this->amountTypeId,
            'amount' => 1000,
            'details' => '昼食',
            'kakeibo_default_category_id' => $this->categoryId,
        ]);

        $this->recordId = $record->id;

        // 削除対象となる自己レビューを作成
        $review = SelfReview::create([
            'kakeibo_record_id' => $this->recordId,
            'review_comment' => '普通だった',
            'evaluation' => 3,
        ]);

        $this->reviewId = $review->id;

        // 認証済みユーザーとしてAPIを実行
        $this->actingAs($this->user);
    }

    /**
     * 自己レビュー削除APIのエンドポイントを生成する
     */
    private function endpoint(int $recordId, int $reviewId): string
    {
        return self::ENDPOINT . '/' . $recordId . '/reviews/' . $reviewId;
    }

    /** @test FSRD-001 正常: 論理削除成功 */
    public function test_destroy_success(): void
    {
        // 自己レビューが正常に論理削除されることを確認
        $response = $this->deleteJson($this->endpoint($this->recordId, $this->reviewId));

        $response->assertStatus(204);

        // 物理削除ではなく論理削除されていることを確認
        $this->assertSoftDeleted('self_reviews', [
            'id' => $this->reviewId,
        ]);
    }

    /** @test FSRD-002 正常: 紐づく投稿は削除されない */
    public function test_destroy_does_not_delete_related_posts(): void
    {
        // 投稿作成用のAIステータスを作成
        $status = new AiStatus();
        $status->status_name = 'completed';
        $status->save();

        // 削除対象レビューに関連する投稿を作成
        $post = Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'ai_status_id' => $status->id,
            'is_ai' => true,
            'content' => 'AIからの返信',
        ]);

        // レビュー削除後も投稿が残ることを確認
        $response = $this->deleteJson($this->endpoint($this->recordId, $this->reviewId));

        $response->assertStatus(204);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'deleted_at' => null,
        ]);
    }

    /** @test FSRD-003 異常: 他ユーザーの家計簿レコード */
    public function test_destroy_fails_when_record_belongs_to_other_user(): void
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

        // 他ユーザー所有の自己レビューを作成
        $otherReview = SelfReview::create([
            'kakeibo_record_id' => $otherRecord->id,
            'review_comment' => '他ユーザーのレビュー',
            'evaluation' => 2,
        ]);

        // 他ユーザーのレビューを削除できないことを確認
        $response = $this->deleteJson($this->endpoint($otherRecord->id, $otherReview->id));

        $response->assertStatus(403);
    }

    /** @test FSRD-004 異常: 存在しないレビュー */
    public function test_destroy_fails_when_review_not_found(): void
    {
        // 存在しないレビューIDを指定した場合のエラーを確認
        $response = $this->deleteJson($this->endpoint($this->recordId, 999));

        $response->assertStatus(404);
    }

    /** @test FSRD-005 異常: 未認証 */
    public function test_destroy_requires_authentication(): void
    {
        // 認証状態を解除
        auth()->logout();

        // 未認証ユーザーでは削除できないことを確認
        $response = $this->deleteJson($this->endpoint($this->recordId, $this->reviewId));

        $response->assertStatus(401);
    }
}
