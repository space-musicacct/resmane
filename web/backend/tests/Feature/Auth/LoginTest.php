<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;
use Tests\TestCase;

/**
 * 結合テスト仕様書 1.2 POST /api/v1/login（ログイン）
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private ApiEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = new V1ApiEndpoint;

        $this->withoutMiddleware([
            PreventRequestForgery::class,
        ]);

        // APIでsession利用可能にする
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
    }

    /** FL-001 正常: ログイン成功 */
    #[Test]
    public function test_login_success(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '田中太郎',
            'password_hash' => Hash::make('password123'),
        ]);

        $response = $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro123',
            'password' => 'password123',
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'loginId',
                    'email',
                    'name',
                    'createdAt',
                ],
            ])
            ->assertJsonPath('user.loginId', 'taro123');

        // セッション認証確認
        $this->assertAuthenticated();
    }

    /** FL-002 異常: パスワード不一致 */
    #[Test]
    public function test_login_fails_when_password_invalid(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '田中太郎',
            'password_hash' => Hash::make('password123'),
        ]);

        $response = $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro123',
            'password' => 'wrong',
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJson([
                'message' => 'ログインIDまたはパスワードが正しくありません',
            ]);
    }

    /** FL-003 異常: 存在しないユーザー */
    #[Test]
    public function test_login_fails_when_user_not_found(): void
    {
        $response = $this->postJson($this->endpoint->login(), [
            'loginId' => 'unknown',
            'password' => 'any',
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /** FL-004 異常: 論理削除済みユーザー */
    #[Test]
    public function test_login_fails_when_user_is_soft_deleted(): void
    {
        $user = User::create([
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '田中太郎',
            'password_hash' => Hash::make('password123'),
        ]);

        $user->delete();

        $this->assertSoftDeleted('users', [
            'login_id' => 'taro123',
        ]);

        $response = $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro123',
            'password' => 'password123',
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /** FL-005 異常: バリデーションエラー */
    #[Test]
    public function test_login_fails_with_validation_errors(): void
    {
        $response = $this->postJson($this->endpoint->login(), [
            'loginId' => '',
            'password' => '',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors([
                'loginId',
                'password',
            ]);
    }

    /** FL-006 異常: レートリミット超過 */
    #[Test]
    public function test_login_fails_when_rate_limit_exceeded(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '田中太郎',
            'password_hash' => Hash::make('password123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->endpoint->login(), [
                'loginId' => 'taro123',
                'password' => 'wrong',
            ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        }

        $response = $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro123',
            'password' => 'wrong',
        ]);

        $response->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        $message = $response->json('message');
        $this->assertStringContainsString('ログイン試行回数が上限に達しました', $message);
        $this->assertMatchesRegularExpression('/\d+分後に再試行してください/', $message);
    }

    /** FL-007 正常: レートリミットリセット */
    #[Test]
    public function test_login_rate_limit_reset_after_success(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '田中太郎',
            'password_hash' => Hash::make('password123'),
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson($this->endpoint->login(), [
                'loginId' => 'taro123',
                'password' => 'wrong',
            ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        }

        // 正しい認証
        $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro123',
            'password' => 'password123',
        ])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('user.loginId', 'taro123');

        // リセットされていることを確認
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->endpoint->login(), [
                'loginId' => 'taro123',
                'password' => 'wrong',
            ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        }

        $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro123',
            'password' => 'wrong',
        ])
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    }
}
