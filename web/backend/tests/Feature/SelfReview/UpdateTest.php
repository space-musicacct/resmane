<?php

declare(strict_types=1);

namespace Tests\Feature\SelfReview;

use App\Models\AmountType;
use App\Models\KakeiboDefaultCategory;
use App\Models\KakeiboRecord;
use App\Models\SelfReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    private ApiEndpoint $endpoint;


    protected User $user;

    protected int $recordId;

    protected int $reviewId;

    protected int $categoryId;

    protected int $amountTypeId;

    /**
     * 自己レビュー更新テストで使用するユーザー、家計簿レコード、レビューを準備する
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = new V1ApiEndpoint();

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

        // 更新対象となる家計簿レコードを作成
        $record = KakeiboRecord::create([
            'user_id' => $this->user->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => $this->amountTypeId,
            'amount' => 1000,
            'details' => '昼食',
            'kakeibo_default_category_id' => $this->categoryId,
        ]);

        $this->recordId = $record->id;

        // 更新対象となる自己レビューを作成
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
     * 自己レビュー更新APIのエンドポイントを生成する
     */
    private function endpoint(int $recordId, int $reviewId): string
    {
        return $this->endpoint->records() . '/' . $recordId . '/reviews/' . $reviewId;
    }

    /** FSRU-001 正常: 更新成功 */
    #[Test]
    public function test_update_success(): void
    {
        // 自己レビューを正常に更新できることを確認
        $response = $this->putJson($this->endpoint($this->recordId, $this->reviewId), [
            'reviewComment' => 'やっぱり良かった',
            'evaluation' => 5,
        ]);

        // 更新後のレスポンス内容とDB更新結果を確認
        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.evaluation', 5)
            ->assertJsonPath('data.reviewComment', 'やっぱり良かった');

        $this->assertDatabaseHas('self_reviews', [
            'id' => $this->reviewId,
            'evaluation' => 5,
        ]);
    }

    /** FSRU-002 異常: 他ユーザーの家計簿レコード */
    #[Test]
    public function test_update_fails_when_record_belongs_to_other_user(): void
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

        // 他ユーザーのレビューを更新できないことを確認
        $response = $this->putJson($this->endpoint($otherRecord->id, $otherReview->id), [
            'reviewComment' => 'テスト',
            'evaluation' => 4,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** FSRU-003 異常: 存在しないレビュー */
    #[Test]
    public function test_update_fails_when_review_not_found(): void
    {
        // 存在しないレビューIDを指定した場合のエラーを確認
        $response = $this->putJson($this->endpoint($this->recordId, 999), [
            'reviewComment' => 'テスト',
            'evaluation' => 4,
        ]);

        $response
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'message' => '指定された自己レビューが見つかりません',
            ]);
    }

    /** FSRU-004 異常: 存在しない家計簿レコード */
    #[Test]
    public function test_update_fails_when_record_not_found(): void
    {
        // 存在しない家計簿レコードIDを指定した場合のエラーを確認
        $response = $this->putJson($this->endpoint(999, $this->reviewId), [
            'reviewComment' => 'テスト',
            'evaluation' => 4,
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** FSRU-005 異常: バリデーションエラー */
    #[Test]
    public function test_update_fails_with_validation_error(): void
    {
        // コメント文字数超過によるバリデーションエラーを確認
        $response = $this->putJson($this->endpoint($this->recordId, $this->reviewId), [
            'reviewComment' => str_repeat('あ', 251),
            'evaluation' => 4,
        ]);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['reviewComment']);
    }

    /** FSRU-006 異常: 未認証 */
    #[Test]
    public function test_update_requires_authentication(): void
    {
        // 認証状態を解除
        auth()->logout();

        // 未認証ユーザーでは更新できないことを確認
        $response = $this->putJson($this->endpoint($this->recordId, $this->reviewId), [
            'reviewComment' => 'テスト',
            'evaluation' => 4,
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}
