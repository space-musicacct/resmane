<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Models\User;
use App\Models\KakeiboRecord;
use App\Models\SelfReview;
use App\Models\Post;
use App\Models\UpperLimitSetting;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;

/**
 * 結合テスト仕様書 6.3 DELETE /api/v1/user（退会）
 */
class DestroyTest extends TestCase
{
    use RefreshDatabase;

    private ApiEndpoint $endpoint;


    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = new V1ApiEndpoint();

        $this->withoutMiddleware([
            PreventRequestForgery::class,
        ]);

        $this->app['router']->pushMiddlewareToGroup(
            'api',
            EncryptCookies::class
        );
        $this->app['router']->pushMiddlewareToGroup(
            'api',
            AddQueuedCookiesToResponse::class
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
        DB::table('amount_types')->insert([
            ['id' => 1, 'type_name' => '支出'],
            ['id' => 2, 'type_name' => '収入'],
        ]);

        DB::table('kakeibo_default_categories')->insert([
            ['id' => 1, 'amount_type_id' => 1, 'category_name' => '飲食'],
        ]);

        DB::table('ai_statuses')->insert([
            ['id' => 1, 'status_name' => 'pending'],
        ]);

        DB::table('upper_limit_types')->insert([
            ['id' => 1, 'type_name' => '割合'],
        ]);
    }

    /** FUD-001 正常: 退会成功（関連データ全て論理削除） */
    #[Test]
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

        $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro',
            'password' => 'password123',
        ])->assertStatus(Response::HTTP_OK);

        $response = $this->deleteJson($this->endpoint->user(), [
            'currentPassword' => 'password123',
        ]);

        $response->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $this->user->id]);
        $this->assertSoftDeleted('kakeibo_records', ['id' => $record->id]);
        $this->assertSoftDeleted('self_reviews', ['kakeibo_record_id' => $record->id]);
        $this->assertSoftDeleted('posts', ['user_id' => $this->user->id]);
        $this->assertSoftDeleted('upper_limit_settings', ['user_id' => $this->user->id]);
    }

    /** FUD-002 正常: 退会後に認証済みリクエストが通らない */
    #[Test]
    public function test_cannot_get_user_after_destroy(): void
    {
        $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro',
            'password' => 'password123',
        ])->assertStatus(Response::HTTP_OK);

        $this->deleteJson($this->endpoint->user(), [
            'currentPassword' => 'password123',
        ])->assertNoContent();

        Auth::forgetGuards();

        $this->getJson($this->endpoint->user())
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /** FUD-003 正常: 家計簿レコードがないユーザーの退会 */
    #[Test]
    public function test_can_destroy_user_without_records(): void
    {
        $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro',
            'password' => 'password123',
        ])->assertStatus(Response::HTTP_OK);

        $response = $this->deleteJson($this->endpoint->user(), [
            'currentPassword' => 'password123',
        ]);

        $response->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $this->user->id]);
    }

    /** FUD-004 異常: パスワード不一致 */
    #[Test]
    public function test_cannot_destroy_with_wrong_password(): void
    {
        $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro',
            'password' => 'password123',
        ])->assertStatus(Response::HTTP_OK);

        $this->deleteJson($this->endpoint->user(), [
            'currentPassword' => 'wrong',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** FUD-005 異常: currentPassword 未入力 */
    #[Test]
    public function test_cannot_destroy_without_current_password(): void
    {
        $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro',
            'password' => 'password123',
        ])->assertStatus(Response::HTTP_OK);

        $this->deleteJson($this->endpoint->user())
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** FUD-006 異常: 未認証 */
    #[Test]
    public function test_cannot_destroy_without_authentication(): void
    {
        $this->deleteJson($this->endpoint->user(), [
            'currentPassword' => 'password123',
        ])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}
