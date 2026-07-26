<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Models\User;
use App\Models\KakeiboRecord;
use App\Models\SelfReview;
use App\Models\Post;
use App\Models\UpperLimitSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Session\Middleware\StartSession;
use Tests\TestCase;

/**
 * 結合テスト仕様書 6.3 DELETE /api/v1/user（退会）
 */
class DestroyTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/user';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);

        $this->app['router']->pushMiddlewareToGroup(
            'api',
            \Illuminate\Cookie\Middleware\EncryptCookies::class
        );
        $this->app['router']->pushMiddlewareToGroup(
            'api',
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class
        );
        $this->app['router']->pushMiddlewareToGroup(
            'api',
            StartSession::class
        );

        $this->user = User::create([
            'login_id' => 'taro',
            'name' => '太郎',
            'email' => 'taro@example.com',
            'password_hash' => Hash::make('password123'),
        ]);
    }

    private function seedMasterData(): void
    {
        \DB::table('amount_types')->insert([
            ['id' => 1, 'type_name' => '支出'],
            ['id' => 2, 'type_name' => '収入'],
        ]);

        \DB::table('kakeibo_default_categories')->insert([
            ['id' => 1, 'amount_type_id' => 1, 'category_name' => '飲食'],
        ]);

        \DB::table('ai_statuses')->insert([
            ['id' => 1, 'status_name' => 'pending'],
        ]);

        \DB::table('upper_limit_types')->insert([
            ['id' => 1, 'type_name' => '割合'],
        ]);
    }

    /** @test FUD-001 正常: 退会成功（関連データ全て論理削除） */
    public function test_can_destroy_user_with_related_data(): void
    {
        $this->seedMasterData();

        $record = KakeiboRecord::create([
            'user_id' => $this->user->id,
            'purchase_date' => now()->toDateString(),
            'amount_type_id' => 1,
            'amount' => 1000,
            'details' => 'テスト',
            'kakeibo_default_category_id' => 1,
        ]);

        SelfReview::create([
            'kakeibo_record_id' => $record->id,
            'review_comment' => 'レビュー',
            'evaluation' => 5,
        ]);

        Post::create([
            'user_id' => $this->user->id,
            'kakeibo_record_id' => $record->id,
            'ai_status_id' => 1,
            'content' => '投稿',
            'is_ai' => false,
        ]);

        UpperLimitSetting::create([
            'user_id' => $this->user->id,
            'upper_limit_type_id' => 1,
            'max_value' => 30,
            'ave_monthly_income' => 200000,
        ]);

        $this->postJson('/api/v1/login', [
            'loginId' => 'taro',
            'password' => 'password123',
        ])->assertStatus(200);

        $response = $this->deleteJson(self::ENDPOINT, [
            'currentPassword' => 'password123',
        ]);

        $response->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $this->user->id]);
        $this->assertSoftDeleted('kakeibo_records', ['id' => $record->id]);
        $this->assertSoftDeleted('self_reviews', ['kakeibo_record_id' => $record->id]);
        $this->assertSoftDeleted('posts', ['user_id' => $this->user->id]);
        $this->assertSoftDeleted('upper_limit_settings', ['user_id' => $this->user->id]);
    }

    /** @test FUD-002 正常: 退会後に認証済みリクエストが通らない */
    public function test_cannot_get_user_after_destroy(): void
    {
        $this->postJson('/api/v1/login', [
            'loginId' => 'taro',
            'password' => 'password123',
        ])->assertStatus(200);

        $this->deleteJson(self::ENDPOINT, [
            'currentPassword' => 'password123',
        ])->assertNoContent();

        Auth::forgetGuards();

        $this->getJson('/api/v1/user')
            ->assertStatus(401);
    }

    /** @test FUD-003 正常: 家計簿レコードがないユーザーの退会 */
    public function test_can_destroy_user_without_records(): void
    {
        $this->postJson('/api/v1/login', [
            'loginId' => 'taro',
            'password' => 'password123',
        ])->assertStatus(200);

        $response = $this->deleteJson(self::ENDPOINT, [
            'currentPassword' => 'password123',
        ]);

        $response->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $this->user->id]);
    }

    /** @test FUD-004 異常: パスワード不一致 */
    public function test_cannot_destroy_with_wrong_password(): void
    {
        $this->postJson('/api/v1/login', [
            'loginId' => 'taro',
            'password' => 'password123',
        ])->assertStatus(200);

        $this->deleteJson(self::ENDPOINT, [
            'currentPassword' => 'wrong',
        ])->assertStatus(422);
    }

    /** @test FUD-005 異常: currentPassword 未入力 */
    public function test_cannot_destroy_without_current_password(): void
    {
        $this->postJson('/api/v1/login', [
            'loginId' => 'taro',
            'password' => 'password123',
        ])->assertStatus(200);

        $this->deleteJson(self::ENDPOINT, [])
            ->assertStatus(422);
    }

    /** @test FUD-006 異常: 未認証 */
    public function test_cannot_destroy_without_authentication(): void
    {
        $this->deleteJson(self::ENDPOINT, [
            'currentPassword' => 'password123',
        ])->assertStatus(401);
    }
}
