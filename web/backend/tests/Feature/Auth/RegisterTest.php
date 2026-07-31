<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;
use Tests\TestCase;

/**
 * 結合テスト仕様書 1.1 POST /api/v1/register（ユーザー登録）
 */
class RegisterTest extends TestCase
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

    /** FR-001 正常: 新規登録成功 */
    #[Test]
    public function test_register_success(): void
    {
        $response = $this->postJson($this->endpoint->register(), [
            'loginId' => 'taro123',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'name' => '田中太郎',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
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

        $this->assertDatabaseHas('users', [
            'login_id' => 'taro123',
            'email' => 'taro@example.com',
        ]);
    }

    /** FR-002 正常: 登録後に認証済みリクエストが通る */
    #[Test]
    public function test_authenticated_request_succeeds_after_register(): void
    {
        $this->postJson($this->endpoint->register(), [
            'loginId' => 'taro123',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'name' => '田中太郎',
        ])->assertStatus(Response::HTTP_CREATED);

        $this->getJson($this->endpoint->user())
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.loginId', 'taro123');
    }

    /** FR-003 異常: loginId 重複 */
    #[Test]
    public function test_register_fails_when_login_id_duplicated(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'test@example.com',
            'name' => 'テスト',
            'password_hash' => bcrypt('password123'),
        ]);

        $response = $this->postJson($this->endpoint->register(), [
            'loginId' => 'taro123',
            'email' => 'new@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'name' => '新規ユーザー',
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJson([
                'message' => 'このログインIDは既に使用されています',
            ]);
    }

    /** FR-004 異常: email 重複 */
    #[Test]
    public function test_register_fails_when_email_duplicated(): void
    {
        User::create([
            'login_id' => 'test123',
            'email' => 'taro@example.com',
            'name' => 'テスト',
            'password_hash' => bcrypt('password123'),
        ]);

        $response = $this->postJson($this->endpoint->register(), [
            'loginId' => 'newuser',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'name' => '新規ユーザー',
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJson([
                'message' => 'このメールアドレスは既に使用されています',
            ]);
    }

    /** FR-005 異常: loginId 重複（論理削除済み） */
    #[Test]
    public function test_register_fails_when_login_id_duplicated_with_soft_deleted_user(): void
    {
        $user = User::create([
            'login_id' => 'taro123',
            'email' => 'deleted@example.com',
            'name' => '削除ユーザー',
            'password_hash' => bcrypt('password123'),
        ]);

        $user->delete();

        $this->assertSoftDeleted('users', [
            'login_id' => 'taro123',
        ]);

        $response = $this->postJson($this->endpoint->register(), [
            'loginId' => 'taro123',
            'email' => 'new@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'name' => '新規ユーザー',
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT);
    }

    /** FR-006 異常: バリデーションエラー */
    #[Test]
    public function test_register_fails_with_validation_errors(): void
    {
        $response = $this->postJson($this->endpoint->register(), [
            'loginId' => '',
            'password' => '123',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors([
                'loginId',
                'email',
                'password',
                'name',
            ]);
    }

    /** FR-007 異常: 全フィールド未入力 */
    #[Test]
    public function test_register_fails_when_all_fields_empty(): void
    {
        $response = $this->postJson($this->endpoint->register());

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors([
                'loginId',
                'email',
                'password',
                'name',
            ]);
    }
}
