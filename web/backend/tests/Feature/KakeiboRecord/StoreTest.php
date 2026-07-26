<?php

declare(strict_types=1);

namespace Tests\Feature\KakeiboRecord;

use App\Models\AmountType;
use App\Models\KakeiboDefaultCategory;
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

    protected int $expenseAmountTypeId;

    protected int $expenseCategoryId;

    protected int $incomeCategoryId;

    /**
     * 登録テストで使用するユーザー、収支区分、カテゴリを準備する
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

        // 支出用の収支区分を作成
        $expenseType = new AmountType();
        $expenseType->type_name = '支出';
        $expenseType->save();

        // 収入用の収支区分を作成
        $incomeType = new AmountType();
        $incomeType->type_name = '収入';
        $incomeType->save();

        $this->expenseAmountTypeId = $expenseType->id;

        // 支出カテゴリを作成
        $expenseCategory = KakeiboDefaultCategory::create([
            'amount_type_id' => $expenseType->id,
            'category_name' => '食費',
        ]);

        // 収入カテゴリを作成
        $incomeCategory = KakeiboDefaultCategory::create([
            'amount_type_id' => $incomeType->id,
            'category_name' => '給与',
        ]);

        $this->expenseCategoryId = $expenseCategory->id;
        $this->incomeCategoryId = $incomeCategory->id;

        // 認証済みユーザーとしてAPIを実行
        $this->actingAs($this->user);
    }

    /** FKS-001 正常: 登録成功 */
    #[Test]
    public function test_store_success(): void
    {
        // 家計簿レコードを正常に登録できることを確認
        $response = $this->postJson($this->endpoint->records(), [
            'purchaseDate' => '2026-07-22',
            'amountTypeId' => $this->expenseAmountTypeId,
            'kakeiboDefaultCategoryId' => $this->expenseCategoryId,
            'amount' => 1000,
            'details' => '昼食',
        ]);

        // 登録成功レスポンスとDB保存結果を確認
        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure([
                'data',
            ]);

        $this->assertDatabaseHas('kakeibo_records', [
            'amount' => 1000,
        ]);
    }

    /** FKS-002 正常: purchaseDate省略時に今日の日付 */
    #[Test]
    public function test_purchase_date_defaults_to_today(): void
    {
        // purchaseDateを指定しない場合のデフォルト値を確認
        $response = $this->postJson($this->endpoint->records(), [
            'amountTypeId' => $this->expenseAmountTypeId,
            'kakeiboDefaultCategoryId' => $this->expenseCategoryId,
            'amount' => 1000,
            'details' => '昼食',
        ]);

        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath(
                'data.purchaseDate',
                now()->toDateString()
            );
    }

    /** FKS-003 正常: amountTypeName, categoryNameを含む */
    #[Test]
    public function test_response_contains_amount_type_name_and_category_name(): void
    {
        // レスポンスに関連情報が含まれることを確認
        $response = $this->postJson($this->endpoint->records(), [
            'purchaseDate' => now()->toDateString(),
            'amountTypeId' => $this->expenseAmountTypeId,
            'kakeiboDefaultCategoryId' => $this->expenseCategoryId,
            'amount' => 500,
            'details' => '昼食',
        ]);

        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.amountTypeName', '支出')
            ->assertJsonPath('data.categoryName', '食費')
            ->assertJsonStructure([
                'data' => [
                    'amountTypeName',
                    'categoryName',
                ],
            ]);
    }

    /** FKS-004 異常: カテゴリと収支区分の不整合 */
    #[Test]
    public function test_store_fails_when_category_and_amount_type_do_not_match(): void
    {
        // 収支区分と一致しないカテゴリを指定した場合のエラーを確認
        $response = $this->postJson($this->endpoint->records(), [
            'purchaseDate' => now()->toDateString(),
            'amountTypeId' => $this->expenseAmountTypeId,
            'kakeiboDefaultCategoryId' => $this->incomeCategoryId,
            'amount' => 500,
            'details' => 'テスト',
        ]);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors([
                'kakeiboDefaultCategoryId',
            ]);
    }

    /** FKS-005 異常: 存在しない amountTypeId */
    #[Test]
    public function test_store_fails_when_amount_type_not_exists(): void
    {
        // 存在しない収支区分ID指定時のバリデーションを確認
        $response = $this->postJson($this->endpoint->records(), [
            'purchaseDate' => now()->toDateString(),
            'amountTypeId' => 999,
            'kakeiboDefaultCategoryId' => $this->expenseCategoryId,
            'amount' => 500,
            'details' => 'テスト',
        ]);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors([
                'amountTypeId',
            ]);
    }

    /** FKS-006 異常: 必須項目未入力 */
    #[Test]
    public function test_store_fails_with_validation_errors(): void
    {
        // 必須項目が未入力の場合のバリデーションを確認
        $response = $this->postJson($this->endpoint->records(), [
            'purchaseDate' => now()->toDateString(),
            'amountTypeId' => $this->expenseAmountTypeId,
            'kakeiboDefaultCategoryId' => $this->expenseCategoryId,
            'amount' => null,
            'details' => 'テスト',
        ]);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors([
                'amount',
            ]);
    }

    /** FKS-007 異常: 未認証 */
    #[Test]
    public function test_store_requires_authentication(): void
    {
        // 認証状態を解除
        auth()->logout();

        // 未認証状態で登録できないことを確認
        $response = $this->postJson($this->endpoint->records(), [
            'purchaseDate' => now()->toDateString(),
            'amountTypeId' => $this->expenseAmountTypeId,
            'kakeiboDefaultCategoryId' => $this->expenseCategoryId,
            'amount' => 1000,
            'details' => '昼食',
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}
