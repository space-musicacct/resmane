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
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/records';

    protected User $user;

    protected int $recordId;

    protected int $categoryId;

    protected int $amountTypeId;

    /**
     * 自己レビュー一覧取得テストで使用するユーザー、家計簿レコードを準備する
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

        // レビュー取得対象となる家計簿レコードを作成
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
     * 自己レビュー一覧取得APIのエンドポイントを生成する
     */
    private function endpoint(int $recordId): string
    {
        return self::ENDPOINT . '/' . $recordId . '/reviews';
    }

    /** @test FSRI-001 正常: 一覧取得 */
    public function test_index_returns_reviews(): void
    {
        // 取得対象となる複数の自己レビューを作成
        SelfReview::create([
            'kakeibo_record_id' => $this->recordId,
            'review_comment' => 'レビュー1',
            'evaluation' => 3,
        ]);
        SelfReview::create([
            'kakeibo_record_id' => $this->recordId,
            'review_comment' => 'レビュー2',
            'evaluation' => 4,
        ]);
        SelfReview::create([
            'kakeibo_record_id' => $this->recordId,
            'review_comment' => 'レビュー3',
            'evaluation' => 5,
        ]);

        // 作成したレビュー一覧を取得
        $response = $this->getJson($this->endpoint($this->recordId));

        // レビュー件数とレスポンス形式を確認
        $response
            ->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['evaluation'],
                ],
            ]);
    }

    /** @test FSRI-002 正常: レビュー0件 */
    public function test_index_returns_empty_when_no_reviews(): void
    {
        // レビューが存在しない場合、空配列が返却されることを確認
        $response = $this->getJson($this->endpoint($this->recordId));

        $response
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    /** @test FSRI-003 異常: 他ユーザーの家計簿レコード */
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

        // 他ユーザーのレコードに紐づくレビュー一覧を取得できないことを確認
        $response = $this->getJson($this->endpoint($otherRecord->id));

        $response->assertStatus(403);
    }

    /** @test FSRI-004 異常: 存在しない家計簿レコード */
    public function test_index_fails_when_record_not_found(): void
    {
        // 存在しない家計簿レコードIDを指定
        $response = $this->getJson($this->endpoint(999));

        // レコードが存在しない場合のエラーを確認
        $response
            ->assertStatus(404)
            ->assertJson([
                'message' => '指定された家計簿レコードが見つかりませんでした',
            ]);
    }

    /** @test FSRI-005 異常: 未認証 */
    public function test_index_requires_authentication(): void
    {
        // 認証状態を解除
        auth()->logout();

        // 未認証ユーザーではアクセスできないことを確認
        $response = $this->getJson($this->endpoint($this->recordId));

        $response->assertStatus(401);
    }
}
