<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Tests\TestCase;

/**
 * 結合テスト仕様書 1.1 POST /api/v1/register（ユーザー登録）
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/register';

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

    /** @test FR-001 正常: 新規登録成功 */
public function test_register_success(): void
{
    $response = $this->postJson(self::ENDPOINT, [
        'loginId' => 'taro123',
        'email' => 'taro@example.com',
        'password' => 'password123',
        'passwordConfirmation' => 'password123',
        'name' => '田中太郎',
    ]);

    $response->assertStatus(201)
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


    /** @test FR-002 正常: 登録後に認証済みリクエストが通る */
    public function test_authenticated_request_succeeds_after_register(): void
    {
        $this->postJson(self::ENDPOINT, [
            'loginId' => 'taro123',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'name' => '田中太郎',
        ])->assertStatus(201);


        $this->getJson('/api/v1/user')
            ->assertStatus(200)
            ->assertJsonPath('data.loginId', 'taro123');
    }


    /** @test FR-003 異常: loginId 重複 */
    public function test_register_fails_when_login_id_duplicated(): void
    {
        User::create([
            'login_id' => 'taro123',
            'email' => 'test@example.com',
            'name' => 'テスト',
            'password_hash' => bcrypt('password123'),
        ]);


        $response = $this->postJson(self::ENDPOINT, [
            'loginId' => 'taro123',
            'email' => 'new@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'name' => '新規ユーザー',
        ]);


        $response->assertStatus(409)
            ->assertJson([
                'message' => 'このログインIDは既に使用されています',
            ]);
    }


    /** @test FR-004 異常: email 重複 */
    public function test_register_fails_when_email_duplicated(): void
    {
        User::create([
            'login_id' => 'test123',
            'email' => 'taro@example.com',
            'name' => 'テスト',
            'password_hash' => bcrypt('password123'),
        ]);


        $response = $this->postJson(self::ENDPOINT, [
            'loginId' => 'newuser',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'name' => '新規ユーザー',
        ]);


        $response->assertStatus(409)
            ->assertJson([
                'message' => 'このメールアドレスは既に使用されています',
            ]);
    }


    /** @test FR-005 異常: loginId 重複（論理削除済み） */
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


        $response = $this->postJson(self::ENDPOINT, [
            'loginId' => 'taro123',
            'email' => 'new@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'name' => '新規ユーザー',
        ]);


        $response->assertStatus(409);
    }


    /** @test FR-006 異常: バリデーションエラー */
    public function test_register_fails_with_validation_errors(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'loginId' => '',
            'password' => '123',
        ]);


        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'loginId',
                'email',
                'password',
                'name',
            ]);
    }


    /** @test FR-007 異常: 全フィールド未入力 */
    public function test_register_fails_when_all_fields_empty(): void
    {
        $response = $this->postJson(self::ENDPOINT, []);


        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'loginId',
                'email',
                'password',
                'name',
            ]);
    }
}
