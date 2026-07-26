<?php

declare(strict_types=1);

namespace Tests\Feature\KakeiboRecord;

use App\Models\AiStatus;
use App\Models\AmountType;
use App\Models\KakeiboDefaultCategory;
use App\Models\KakeiboRecord;
use App\Models\Post;
use App\Models\SelfReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    private ApiEndpoint $endpoint;

    protected User $user;

    protected int $recordId;

    protected int $categoryId;

    protected int $amountTypeId;

    /**
     * テストで使用するユーザー、関連マスタ、削除対象レコードを共通で準備する
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = new V1ApiEndpoint;

        // API実行用の認証済みユーザーを作成
        $this->user = User::create([
            'login_id' => 'testuser',
            'email' => 'test@example.com',
            'name' => 'テストユーザー',
            'password_hash' => Hash::make('password123'),
        ]);

        // 家計簿レコード作成に必要な金額種別を作成
        $amountType = new AmountType;
        $amountType->type_name = '支出';
        $amountType->save();

        // 家計簿レコード作成に必要なカテゴリを作成
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

        // 以降のテストを認証済みユーザーとして実行
        $this->actingAs($this->user);
    }

    /** FKDL-001 正常: 論理削除成功 */
    #[Test]
    public function test_destroy_success(): void
    {
        // 削除API実行後、物理削除ではなく論理削除されることを確認
        $response = $this->deleteJson(
            $this->endpoint->records().'/'.$this->recordId
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertSoftDeleted('kakeibo_records', [
            'id' => $this->recordId,
        ]);
    }

    /** FKDL-002 正常: 紐づく自己レビューも論理削除される */
    #[Test]
    public function test_destroy_deletes_related_reviews(): void
    {
        // 削除対象レコードに紐づく自己レビューを作成
        $review = SelfReview::create([
            'kakeibo_record_id' => $this->recordId,
            'review_comment' => '良い買い物',
            'evaluation' => 5,
        ]);

        // 家計簿レコード削除時に関連レビューも削除されることを確認
        $response = $this->deleteJson(
            $this->endpoint->records().'/'.$this->recordId
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertSoftDeleted('self_reviews', [
            'id' => $review->id,
        ]);
    }

    /** FKDL-003 正常: 紐づく投稿も論理削除される */
    #[Test]
    public function test_destroy_deletes_related_posts(): void
    {
        // 投稿作成に必要なAIステータスを作成
        $status = new AiStatus;
        $status->status_name = 'pending';
        $status->save();

        // 削除対象レコードに紐づく投稿を作成
        $post = Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $this->recordId,
            'ai_status_id' => $status->id,
            'is_ai' => true,
            'content' => 'AIメッセージ',
        ]);

        // 家計簿レコード削除時に関連投稿も削除されることを確認
        $response = $this->deleteJson(
            $this->endpoint->records().'/'.$this->recordId
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertSoftDeleted('posts', [
            'id' => $post->id,
        ]);
    }

    /** FKDL-004 異常: 他ユーザーのレコード */
    #[Test]
    public function test_destroy_fails_when_record_belongs_to_other_user(): void
    {
        // 削除権限を持たない別ユーザーを作成
        $otherUser = User::create([
            'login_id' => 'otheruser',
            'email' => 'other@example.com',
            'name' => '別ユーザー',
            'password_hash' => Hash::make('password123'),
        ]);

        // 別ユーザー所有のレコードを作成
        $record = KakeiboRecord::create([
            'user_id' => $otherUser->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => $this->amountTypeId,
            'amount' => 2000,
            'details' => '他ユーザー',
            'kakeibo_default_category_id' => $this->categoryId,
        ]);

        // 他ユーザーのレコードを削除できないことを確認
        $response = $this->deleteJson(
            $this->endpoint->records().'/'.$record->id
        );

        $response
            ->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson([
                'message' => 'このレコードへのアクセス権限がありません',
            ]);
    }

    /** FKDL-005 異常: 存在しないレコード */
    #[Test]
    public function test_destroy_fails_when_record_not_found(): void
    {
        // 存在しないレコードIDを指定した場合のエラーを確認
        $response = $this->deleteJson(
            $this->endpoint->records().'/999'
        );

        $response
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'message' => '指定された家計簿レコードが見つかりませんでした',
            ]);
    }

    /** FKDL-006 異常: 未認証 */
    #[Test]
    public function test_destroy_requires_authentication(): void
    {
        // 認証情報を解除
        auth()->logout();

        // 未認証ユーザーが削除できないことを確認
        $response = $this->deleteJson(
            $this->endpoint->records().'/'.$this->recordId
        );

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}
