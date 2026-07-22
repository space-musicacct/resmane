<?php

declare(strict_types=1);

namespace Tests\Feature\KakeiboRecord;

use App\Models\AmountType;
use App\Models\KakeiboDefaultCategory;
use App\Models\KakeiboRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/records';

    protected User $user;

    protected int $recordId;

    protected int $otherUserRecordId;

    protected int $amountTypeId;

    protected int $categoryId;

    /**
     * 更新テストで使用するユーザー、収支区分、カテゴリ、更新対象レコードを準備する
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

        // 更新対象レコードで使用する収支区分を作成
        $amountType = new AmountType();
        $amountType->type_name = '支出';
        $amountType->save();

        // 更新対象レコードで使用するカテゴリを作成
        $category = KakeiboDefaultCategory::create([
            'amount_type_id' => $amountType->id,
            'category_name' => '食費',
        ]);

        $this->amountTypeId = $amountType->id;
        $this->categoryId = $category->id;

        // 更新対象となる家計簿レコードを作成
        $record = KakeiboRecord::create([
            'user_id' => $this->user->id,
            'purchase_date' => '2026-07-01',
            'amount_type_id' => $this->amountTypeId,
            'amount' => 1000,
            'details' => '昼食',
            'kakeibo_default_category_id' => $this->categoryId,
        ]);

        $this->recordId = $record->id;

        // 認証済みユーザーとしてAPIを実行
        $this->actingAs($this->user);
    }

    /** @test FKU-001 正常: 更新成功 */
    public function test_update_success(): void
    {
        // 自分のレコードを正常に更新できることを確認
        $response = $this->putJson(
            self::ENDPOINT . '/' . $this->recordId,
            [
                'amountTypeId' => $this->amountTypeId,
                'kakeiboDefaultCategoryId' => $this->categoryId,
                'amount' => 2000,
                'details' => '夕食',
            ]
        );

        // 更新後のレスポンス内容とDB更新結果を確認
        $response
            ->assertStatus(200)
            ->assertJsonPath('data.amount', 2000)
            ->assertJsonPath('data.details', '夕食');

        $this->assertDatabaseHas('kakeibo_records', [
            'id' => $this->recordId,
            'amount' => 2000,
        ]);
    }

    /** @test FKU-002 正常: purchaseDate省略時に既存値を維持 */
    public function test_update_keeps_purchase_date_when_omitted(): void
    {
        // purchaseDate未指定時に既存の日付が維持されることを確認
        $response = $this->putJson(
            self::ENDPOINT . '/' . $this->recordId,
            [
                'amountTypeId' => $this->amountTypeId,
                'kakeiboDefaultCategoryId' => $this->categoryId,
                'amount' => 2000,
                'details' => '更新',
            ]
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath(
                'data.purchaseDate',
                '2026-07-01'
            );
    }

    /** @test FKU-003 異常: 他ユーザーのレコード */
    public function test_update_fails_when_record_belongs_to_other_user(): void
    {
        // 別ユーザーを作成
        $otherUser = User::create([
            'login_id' => 'otheruser',
            'email' => 'other@example.com',
            'name' => '別ユーザー',
            'password_hash' => Hash::make('password123'),
        ]);

        // 他ユーザー所有のレコードを作成
        $record = KakeiboRecord::create([
            'user_id' => $otherUser->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => $this->amountTypeId,
            'amount' => 3000,
            'details' => '他ユーザー',
            'kakeibo_default_category_id' => $this->categoryId,
        ]);

        // 他ユーザーのレコードを更新できないことを確認
        $response = $this->putJson(
            self::ENDPOINT . '/' . $record->id,
            [
                'amountTypeId' => $this->amountTypeId,
                'kakeiboDefaultCategoryId' => $this->categoryId,
                'amount' => 5000,
                'details' => '更新',
            ]
        );

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'このレコードへのアクセス権限がありません',
            ]);
    }

    /** @test FKU-004 異常: 存在しないレコード */
    public function test_update_fails_when_record_not_found(): void
    {
        // 存在しないレコードIDを指定した場合のエラーを確認
        $response = $this->putJson(
            self::ENDPOINT . '/999',
            [
                'amountTypeId' => $this->amountTypeId,
                'kakeiboDefaultCategoryId' => $this->categoryId,
                'amount' => 2000,
                'details' => '更新',
            ]
        );

        $response
            ->assertStatus(404)
            ->assertJson([
                'message' => '指定された家計簿レコードが見つかりませんでした',
            ]);
    }

    /** @test FKU-005 異常: バリデーションエラー */
    public function test_update_fails_with_validation_error(): void
    {
        // 不正な値を指定した場合にバリデーションエラーになることを確認
        $response = $this->putJson(
            self::ENDPOINT . '/' . $this->recordId,
            [
                'amountTypeId' => $this->amountTypeId,
                'kakeiboDefaultCategoryId' => $this->categoryId,
                'amount' => 0,
                'details' => '更新',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'amount',
            ]);
    }

    /** @test FKU-006 異常: 未認証 */
    public function test_update_requires_authentication(): void
    {
        // 認証状態を解除
        auth()->logout();

        // 未認証状態では更新できないことを確認
        $response = $this->putJson(
            self::ENDPOINT . '/' . $this->recordId,
            [
                'amountTypeId' => $this->amountTypeId,
                'kakeiboDefaultCategoryId' => $this->categoryId,
                'amount' => 2000,
                'details' => '更新',
            ]
        );

        $response->assertStatus(401);
    }
}
