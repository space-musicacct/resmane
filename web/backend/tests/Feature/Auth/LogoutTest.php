<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Session\Middleware\StartSession;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN_ENDPOINT = '/api/v1/login';

    private const LOGOUT_ENDPOINT = '/api/v1/logout';


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
    }


    /** @test FLO-001 正常: ログアウト成功 */
    public function test_logout_success(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '田中太郎',
            'password_hash' => Hash::make('password123'),
        ]);


        $this->postJson(self::LOGIN_ENDPOINT, [
            'loginId' => 'taro123',
            'password' => 'password123',
        ])
        ->assertStatus(200);


        $this->postJson(self::LOGOUT_ENDPOINT)
            ->assertStatus(204);


        // guard状態を破棄
        Auth::forgetGuards();


        $this->getJson('/api/v1/user')
            ->assertStatus(401);
    }



    /** @test FLO-002 正常: ログアウト後に認証済みリクエストが通らない */
    public function test_authenticated_request_fails_after_logout(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '田中太郎',
            'password_hash' => Hash::make('password123'),
        ]);


        $this->postJson(self::LOGIN_ENDPOINT, [
            'loginId' => 'taro123',
            'password' => 'password123',
        ])
        ->assertStatus(200);


        $this->postJson(self::LOGOUT_ENDPOINT)
            ->assertStatus(204);


        Auth::forgetGuards();


        $this->getJson('/api/v1/user')
            ->assertStatus(401);
    }



    /** @test FLO-003 異常: 未認証 */
    public function test_logout_fails_when_guest(): void
    {
        $this->postJson(self::LOGOUT_ENDPOINT)
            ->assertStatus(401);
    }
}
