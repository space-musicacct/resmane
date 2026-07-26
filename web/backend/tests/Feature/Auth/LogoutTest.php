<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;
use Tests\TestCase;

class LogoutTest extends TestCase
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

    /** FLO-001 正常: ログアウト成功 */
    #[Test]
    public function test_logout_success(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '田中太郎',
            'password_hash' => Hash::make('password123'),
        ]);

        $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro123',
            'password' => 'password123',
        ])
            ->assertStatus(Response::HTTP_OK);

        $this->postJson($this->endpoint->logout())
            ->assertStatus(Response::HTTP_NO_CONTENT);

        // guard状態を破棄
        Auth::forgetGuards();

        $this->getJson($this->endpoint->user())
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /** FLO-002 正常: ログアウト後に認証済みリクエストが通らない */
    #[Test]
    public function test_authenticated_request_fails_after_logout(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '田中太郎',
            'password_hash' => Hash::make('password123'),
        ]);

        $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro123',
            'password' => 'password123',
        ])
            ->assertStatus(Response::HTTP_OK);

        $this->postJson($this->endpoint->logout())
            ->assertStatus(Response::HTTP_NO_CONTENT);

        Auth::forgetGuards();

        $this->getJson($this->endpoint->user())
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /** FLO-003 異常: 未認証 */
    #[Test]
    public function test_logout_fails_when_guest(): void
    {
        $this->postJson($this->endpoint->logout())
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}
