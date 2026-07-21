<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * 結合テスト仕様書 1.2 POST /api/v1/login（ログイン）
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/login';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);

        // APIでsession利用可能にする
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
    }


/** @test FL-001 正常: ログイン成功 */
public function test_login_success(): void
{
    User::create([
        'login_id' => 'taro123',
        'email' => 'taro@example.com',
        'name' => '田中太郎',
        'password_hash' => Hash::make('password123'),
    ]);

    $response = $this->postJson(self::ENDPOINT, [
        'loginId' => 'taro123',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
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

    /** @test FL-002 異常: パスワード不一致 */
    public function test_login_fails_when_password_invalid(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '田中太郎',
            'password_hash' => Hash::make('password123'),
        ]);


        $response = $this->postJson(self::ENDPOINT, [
            'loginId' => 'taro123',
            'password' => 'wrong',
        ]);


        $response->assertStatus(401)
            ->assertJson([
                'message' => 'ログインIDまたはパスワードが正しくありません',
            ]);
    }


    /** @test FL-003 異常: 存在しないユーザー */
    public function test_login_fails_when_user_not_found(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'loginId' => 'unknown',
            'password' => 'any',
        ]);


        $response->assertStatus(401);
    }


    /** @test FL-004 異常: 論理削除済みユーザー */
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


        $response = $this->postJson(self::ENDPOINT, [
            'loginId' => 'taro123',
            'password' => 'password123',
        ]);


        $response->assertStatus(401);
    }


    /** @test FL-005 異常: バリデーションエラー */
    public function test_login_fails_with_validation_errors(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'loginId' => '',
            'password' => '',
        ]);


        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'loginId',
                'password',
            ]);
    }


    /** @test FL-006 異常: レートリミット超過 */
    public function test_login_fails_when_rate_limit_exceeded(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '田中太郎',
            'password_hash' => Hash::make('password123'),
        ]);


        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::ENDPOINT, [
                'loginId' => 'taro123',
                'password' => 'wrong',
            ])
            ->assertStatus(401);
        }


        $response = $this->postJson(self::ENDPOINT, [
            'loginId' => 'taro123',
            'password' => 'wrong',
        ]);


        $response->assertStatus(429)
            ->assertJsonStructure([
                'message',
            ]);
    }


    /** @test FL-007 正常: レートリミットリセット */
public function test_login_rate_limit_reset_after_success(): void
{
    User::create([
        'login_id' => 'taro123',
        'email' => 'taro@example.com',
        'name' => '田中太郎',
        'password_hash' => Hash::make('password123'),
    ]);

    for ($i = 0; $i < 4; $i++) {
        $this->postJson(self::ENDPOINT, [
            'loginId' => 'taro123',
            'password' => 'wrong',
        ])
        ->assertStatus(401);
    }

    // 正しい認証
    $this->postJson(self::ENDPOINT, [
        'loginId' => 'taro123',
        'password' => 'password123',
    ])
    ->assertStatus(200)
    ->assertJsonPath('user.loginId', 'taro123');


    // リセットされていることを確認
    for ($i = 0; $i < 5; $i++) {
        $this->postJson(self::ENDPOINT, [
            'loginId' => 'taro123',
            'password' => 'wrong',
        ])
        ->assertStatus(401);
    }

    $this->postJson(self::ENDPOINT, [
        'loginId' => 'taro123',
        'password' => 'wrong',
    ])
    ->assertStatus(429);
}

}
