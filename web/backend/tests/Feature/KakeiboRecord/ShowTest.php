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

class ShowTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/records';

    protected User $user;

    protected int $recordId;

    protected int $otherUserRecordId;

    /**
     * 詳細取得テストで使用するユーザー、カテゴリ、家計簿レコードを準備する
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

        // 家計簿レコード作成に必要な支出区分を作成
        $expenseType = new AmountType();
        $expenseType->type_name = '支出';
        $expenseType->save();

        // 家計簿レコード作成に必要なカテゴリを作成
        $category = KakeiboDefaultCategory::create([
            'amount_type_id' => $expenseType->id,
            'category_name' => '食費',
        ]);

        // 詳細取得対象となる家計簿レコードを作成
        $record = KakeiboRecord::create([
            'user_id' => $this->user->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => $expenseType->id,
            'amount' => 1000,
            'details' => '昼食',
            'kakeibo_default_category_id' => $category->id,
        ]);

        $this->recordId = $record->id;

        // 認証済みユーザーとしてAPIを実行
        $this->actingAs($this->user);
    }

    /** @test FKD-001 正常: 詳細取得 */
    public function test_show_success(): void
    {
        // 自分自身のレコード詳細を取得できることを確認
        $response = $this->getJson(
            self::ENDPOINT . '/' . $this->recordId
        );

        // レスポンス形式と取得対象レコードを確認
        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'userId',
                    'purchaseDate',
                    'amountTypeId',
                    'amountTypeName',
                    'amount',
                    'details',
                    'categoryId',
                    'categoryName',
                    'createdAt',
                    'updatedAt',
                ],
            ])
            ->assertJsonPath(
                'data.id',
                $this->recordId
            );
    }

    /** @test FKD-002 異常: 他ユーザーのレコード */
    public function test_show_fails_when_record_belongs_to_other_user(): void
    {
        // 別ユーザーを作成
        $otherUser = User::create([
            'login_id' => 'otheruser',
            'email' => 'other@example.com',
            'name' => '別ユーザー',
            'password_hash' => Hash::make('password123'),
        ]);

        // 既存の支出区分を取得
        $expenseType = AmountType::first();

        // 既存のカテゴリを取得
        $category = KakeiboDefaultCategory::first();

        // 他ユーザー所有のレコードを作成
        $record = KakeiboRecord::create([
            'user_id' => $otherUser->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => $expenseType->id,
            'amount' => 2000,
            'details' => '他ユーザー記録',
            'kakeibo_default_category_id' => $category->id,
        ]);

        // 他ユーザーのレコードを取得できないことを確認
        $response = $this->getJson(
            self::ENDPOINT . '/' . $record->id
        );

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'このレコードへのアクセス権限がありません',
            ]);
    }

    /** @test FKD-003 異常: 存在しないレコード */
    public function test_show_fails_when_record_not_found(): void
    {
        // 存在しないIDを指定した場合のエラーを確認
        $response = $this->getJson(
            self::ENDPOINT . '/999'
        );

        $response
            ->assertStatus(404)
            ->assertJson([
                'message' => '指定された家計簿レコードが見つかりませんでした',
            ]);
    }

    /** @test FKD-004 異常: 論理削除済みレコード */
    public function test_show_fails_when_record_is_soft_deleted(): void
    {
        // 対象レコードを論理削除
        $record = KakeiboRecord::find($this->recordId);

        $record->delete();

        // 論理削除済みレコードが取得できないことを確認
        $response = $this->getJson(
            self::ENDPOINT . '/' . $this->recordId
        );

        $response->assertStatus(404);
    }

    /** @test FKD-005 異常: 未認証 */
    public function test_show_requires_authentication(): void
    {
        // 認証状態を解除
        auth()->logout();

        // 未認証ユーザーではアクセスできないことを確認
        $response = $this->getJson(
            self::ENDPOINT . '/' . $this->recordId
        );

        $response->assertStatus(401);
    }
}
