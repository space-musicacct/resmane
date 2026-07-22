<?php

namespace Tests\Feature\User;

use App\Models\User;
use App\Models\KakeiboRecord;
use App\Models\SelfReview;
use App\Models\Post;
use App\Models\UpperLimitSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/user';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 削除対象となるテストユーザーを作成する
        $this->user = User::create([
            'login_id' => 'taro',
            'name' => '太郎',
            'email' => 'taro@example.com',
            'password_hash' => Hash::make('old'),
        ]);
    }


    // 関連データ作成に必要なマスターデータを登録する
    private function createMasterData(): void
    {
        \DB::table('amount_types')->insert([
            [
                'id' => 1,
                'type_name' => '支出',
            ],
            [
                'id' => 2,
                'type_name' => '収入',
            ],
        ]);

        \DB::table('kakeibo_default_categories')->insert([
            [
                'id' => 1,
                'amount_type_id' => 1,
                'category_name' => '飲食',
            ],
        ]);

        \DB::table('ai_statuses')->insert([
            [
                'id' => 1,
                'status_name' => 'pending',
            ],
        ]);

        \DB::table('upper_limit_types')->insert([
            [
                'id' => 1,
                'type_name' => '月額',
            ],
        ]);
    }


    // セッションを利用できる状態にする
private function enableSession(): void
{
    // Laravelのセッションドライバを有効化する
    config([
        'session.driver' => 'array',
    ]);

    // テストリクエストにセッションを付与する
    $this->withSession([
        '_token' => csrf_token(),
    ]);
}




    /**
     * FUD-001
     */
    public function test_can_destroy_user_with_related_data(): void
    {
        // 関連データ作成用のマスターデータを準備する
        $this->createMasterData();

        // 削除対象ユーザーに紐づく家計簿レコードを作成する
        $record = KakeiboRecord::create([
            'user_id' => $this->user->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => 1,
            'amount' => 1000,
            'details' => 'test',
            'kakeibo_default_category_id' => 1,
        ]);

        // 家計簿レコードに紐づく自己レビューを作成する
        SelfReview::create([
            'kakeibo_record_id' => $record->id,
            'review_comment' => 'review',
            'evaluation' => 5,
        ]);

        // 家計簿レコードに紐づく投稿データを作成する
        Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $record->id,
            'ai_status_id' => 1,
            'content' => 'post',
            'is_ai' => false,
        ]);
        // ユーザーに紐づく上限設定を作成する
        UpperLimitSetting::create([
            'user_id' => $this->user->id,
            'upper_limit_type_id' => 1,
            'max_value' => 30,
            'ave_monthly_income' => 200000,
        ]);

        // セッションを有効化する
        $this->enableSession();

        // 削除対象ユーザーとして認証する
        $this->actingAs($this->user, 'sanctum');

        // ユーザー削除APIを実行する
        $response = $this->deleteJson(
            self::ENDPOINT,
            [
                'currentPassword' => 'old',
            ]
        );

        // 削除処理が成功したことを確認する
        $response->assertNoContent();

        // ユーザーが論理削除されていることを確認する
        $this->assertSoftDeleted('users', [
            'id' => $this->user->id,
        ]);

        // 家計簿レコードが論理削除されていることを確認する
        $this->assertSoftDeleted('kakeibo_records', [
            'id' => $record->id,
        ]);

        // 自己レビューが論理削除されていることを確認する
        $this->assertSoftDeleted('self_reviews', [
            'kakeibo_record_id' => $record->id,
        ]);

        // 投稿データが論理削除されていることを確認する
        $this->assertSoftDeleted('posts', [
            'user_id' => $this->user->id,
        ]);

        // 上限設定が論理削除されていることを確認する
        $this->assertSoftDeleted('upper_limit_settings', [
            'user_id' => $this->user->id,
        ]);
    }


    /**
     * FUD-002
     */
    public function test_cannot_get_user_after_destroy(): void
    {
        // セッションを有効化する
        $this->enableSession();

        // 削除対象ユーザーとして認証する
        $this->actingAs($this->user, 'sanctum');

        // ユーザー削除APIを実行する
        $this->deleteJson(
            self::ENDPOINT,
            [
                'currentPassword' => 'old',
            ]
        )
        ->assertNoContent();

        // 削除済みユーザーは取得APIで認証エラーになることを確認する
        $this->getJson('/api/v1/user')
            ->assertStatus(401);
    }


    /**
     * FUD-003
     */
    public function test_can_destroy_user_without_records(): void
    {
        // セッションを有効化する
        $this->enableSession();

        // 削除対象ユーザーとして認証する
        $this->actingAs($this->user, 'sanctum');

        // 関連データが存在しない状態でユーザー削除APIを実行する
        $response = $this->deleteJson(
            self::ENDPOINT,
            [
                'currentPassword' => 'old',
            ]
        );

        // 削除処理が成功したことを確認する
        $response->assertNoContent();

        // ユーザーが論理削除されていることを確認する
        $this->assertSoftDeleted('users', [
            'id' => $this->user->id,
        ]);
    }
    /**
     * FUD-004
     */
    public function test_cannot_destroy_with_wrong_password(): void
    {
        // 削除対象ユーザーとして認証する
        $this->actingAs($this->user, 'sanctum');

        // 誤った現在パスワードで削除を試みる
        $this->deleteJson(
            self::ENDPOINT,
            [
                'currentPassword' => 'wrong',
            ]
        )
        // パスワード不一致によるバリデーションエラーを確認する
        ->assertStatus(422);
    }


    /**
     * FUD-005
     */
    public function test_cannot_destroy_without_current_password(): void
    {
        // 削除対象ユーザーとして認証する
        $this->actingAs($this->user, 'sanctum');

        // 現在パスワードを送信せず削除を試みる
        $this->deleteJson(
            self::ENDPOINT,
            []
        )
        // 必須項目エラーになることを確認する
        ->assertStatus(422);
    }


    /**
     * FUD-006
     */
    public function test_cannot_destroy_without_authentication(): void
    {
        // 認証なしでユーザー削除APIを実行する
        $this->deleteJson(
            self::ENDPOINT,
            [
                'currentPassword' => 'old',
            ]
        )
        // 未認証エラーになることを確認する
        ->assertStatus(401);
    }
}
