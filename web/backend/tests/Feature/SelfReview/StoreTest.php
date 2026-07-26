<?php

declare(strict_types=1);

namespace Tests\Feature\SelfReview;

use App\Models\AmountType;
use App\Models\KakeiboDefaultCategory;
use App\Models\KakeiboRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/records';

    protected User $user;

    protected int $recordId;

    protected int $categoryId;

    protected int $amountTypeId;

    /**
     * 自己レビュー登録テストで使用するユーザー、家計簿レコードを準備する
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

        // レビュー登録対象となる家計簿レコードを作成
        $record = KakeiboRecord::create([
            'user_id' => $this->user->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => $this->amountTypeId,
            'amount' => 1000,
            'details' => '昼食',
            'kakeibo_default_category_id' => $this->categoryId,
        ]);

        $this->recordId = $record->id;

        // 認証済みユーザーとしてAPIを実行
        $this->actingAs($this->user);
    }

    /**
     * 自己レビュー登録APIのエンドポイントを生成する
     */
    private function endpoint(int $recordId): string
    {
        return self::ENDPOINT . '/' . $recordId . '/reviews';
    }

    /** @test FSRS-001 正常: 投稿成功 */
    public function test_store_success(): void
    {
        // 自己レビューを正常に登録できることを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'reviewComment' => '良い買い物だった',
            'evaluation' => 4,
        ]);

        // レスポンス内容とDB登録結果を確認
        $response
            ->assertStatus(201)
            ->assertJsonPath('data.evaluation', 4)
            ->assertJsonPath('data.reviewComment', '良い買い物だった');

        $this->assertDatabaseHas('self_reviews', [
            'kakeibo_record_id' => $this->recordId,
            'evaluation' => 4,
        ]);
    }

    /** @test FSRS-002 異常: 他ユーザーの家計簿レコード */
    public function test_store_fails_when_record_belongs_to_other_user(): void
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

        // 他ユーザーのレコードへレビュー登録できないことを確認
        $response = $this->postJson($this->endpoint($otherRecord->id), [
            'reviewComment' => 'テスト',
            'evaluation' => 3,
        ]);

        $response->assertStatus(403);
    }

    /** @test FSRS-003 異常: 存在しない家計簿レコード */
    public function test_store_fails_when_record_not_found(): void
    {
        // 存在しない家計簿レコードIDを指定
        $response = $this->postJson($this->endpoint(999), [
            'reviewComment' => 'テスト',
            'evaluation' => 3,
        ]);

        // 対象レコードが存在しない場合のエラーを確認
        $response->assertStatus(404);
    }

    /** @test FSRS-004 異常: バリデーションエラー（reviewComment） */
    public function test_store_fails_when_review_comment_empty(): void
    {
        // reviewComment未入力時にバリデーションエラーになることを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'reviewComment' => '',
            'evaluation' => 3,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reviewComment']);
    }

    /** @test FSRS-006 異常: バリデーションエラー（evaluation 範囲外） */
    public function test_store_fails_when_evaluation_out_of_range(): void
    {
        // evaluationが許容範囲外の場合にエラーになることを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'reviewComment' => 'テスト',
            'evaluation' => 0,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['evaluation']);
    }

    /** @test FSRS-007 異常: バリデーションエラー（evaluation 未入力） */
    public function test_store_fails_when_evaluation_empty(): void
    {
        // evaluation未入力時にバリデーションエラーになることを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'reviewComment' => 'テスト',
            'evaluation' => '',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['evaluation']);
    }

    /** @test FSRS-005 異常: 未認証 */
    public function test_store_requires_authentication(): void
    {
        // 認証状態を解除
        auth()->logout();

        // 未認証ユーザーではレビュー登録できないことを確認
        $response = $this->postJson($this->endpoint($this->recordId), [
            'reviewComment' => 'テスト',
            'evaluation' => 3,
        ]);

        $response->assertStatus(401);
    }
}
