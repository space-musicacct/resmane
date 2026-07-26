<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    private ApiEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = new V1ApiEndpoint;
    }

    // テスト用ユーザーを作成する共通処理
    private function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'login_id' => 'taro',
            'email' => 'taro@example.com',
            'name' => '太郎',
            'password_hash' => Hash::make('oldpassword'),
        ], $attributes));
    }

    /**
     * FUU-001
     * 正常: loginId 変更
     */
    #[Test]
    public function test_can_update_login_id(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // loginId変更リクエストを送信する
        $response = $this->putJson($this->endpoint->user(), [
            'loginId' => 'newtaro',
        ]);

        // レスポンスに変更後のloginIdが反映されていることを確認する
        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'data' => [
                    'loginId' => 'newtaro',
                ],
            ]);
    }

    /**
     * FUU-002
     * 正常: name 変更
     */
    #[Test]
    public function test_can_update_name(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // name変更リクエストを送信する
        $response = $this->putJson($this->endpoint->user(), [
            'name' => '新太郎',
        ]);

        // レスポンスに変更後の名前が反映されていることを確認する
        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'data' => [
                    'name' => '新太郎',
                ],
            ]);
    }

    /**
     * FUU-003
     * 正常: email 変更
     */
    #[Test]
    public function test_can_update_email(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // email変更リクエストを送信する
        $response = $this->putJson($this->endpoint->user(), [
            'email' => 'new@example.com',
        ]);

        // レスポンスに変更後のメールアドレスが反映されていることを確認する
        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'data' => [
                    'email' => 'new@example.com',
                ],
            ]);
    }

    /**
     * FUU-004
     * 正常: パスワード変更
     */
    #[Test]
    public function test_can_update_password(): void
    {
        // セッションミドルウェアを有効化（ログイン検証のため）
        $this->withoutMiddleware([
            PreventRequestForgery::class,
        ]);
        $this->app['router']->pushMiddlewareToGroup('api', EncryptCookies::class);
        $this->app['router']->pushMiddlewareToGroup('api', AddQueuedCookiesToResponse::class);
        $this->app['router']->pushMiddlewareToGroup('api', StartSession::class);

        // 更新対象となるユーザーを作成する
        $this->createUser();

        // ログイン経由で認証する
        $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro',
            'password' => 'oldpassword',
        ])->assertStatus(Response::HTTP_OK);

        // 現在のパスワード確認を含めて新しいパスワードへ変更する
        $response = $this->putJson($this->endpoint->user(), [
            'currentPassword' => 'oldpassword',
            'password' => 'newpass123',
            'passwordConfirmation' => 'newpass123',
        ]);

        // 更新処理が成功したことを確認する
        $response->assertStatus(Response::HTTP_OK);

        // guard を解除してログアウト状態にする
        Auth::guard('web')->logout();
        Auth::forgetGuards();
        config(['auth.defaults.guard' => 'web']);

        // 変更後パスワードでログインできることを確認する
        $this->postJson($this->endpoint->login(), [
            'loginId' => 'taro',
            'password' => 'newpass123',
        ])->assertStatus(Response::HTTP_OK);
    }

    /**
     * FUU-005
     * 正常: 複数フィールド同時変更
     */
    #[Test]
    public function test_can_update_multiple_fields(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // 複数項目を同時に更新するリクエストを送信する
        $response = $this->putJson($this->endpoint->user(), [
            'loginId' => 'newtaro',
            'name' => '新太郎',
            'email' => 'new@example.com',
        ]);

        // すべての更新内容がレスポンスに反映されていることを確認する
        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'data' => [
                    'loginId' => 'newtaro',
                    'name' => '新太郎',
                    'email' => 'new@example.com',
                ],
            ]);
    }

    /**
     * FUU-006
     * 異常: loginId 重複
     */
    #[Test]
    public function test_fails_when_login_id_already_exists(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 既に同じloginIdを持つ別ユーザーを作成する
        $this->createUser([
            'login_id' => 'existing',
            'email' => 'existing@example.com',
        ]);

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // 重複しているloginIdへ変更を試みる
        $response = $this->putJson($this->endpoint->user(), [
            'loginId' => 'existing',
        ]);

        // 重複エラーが返却されることを確認する
        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJson([
                'message' => 'このログインIDは既に使用されています',
            ]);
    }

    /**
     * FUU-007
     * 異常: loginId 重複（論理削除済み）
     */
    #[Test]
    public function test_fails_when_login_id_exists_in_deleted_user(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 削除済みとなるユーザーを作成する
        $deletedUser = $this->createUser([
            'login_id' => 'deleted_login',
            'email' => 'deleted@example.com',
        ]);

        // ユーザーを論理削除する
        $deletedUser->delete();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // 論理削除済みユーザーと同じloginIdへ変更を試みる
        $response = $this->putJson($this->endpoint->user(), [
            'loginId' => 'deleted_login',
        ]);

        // 重複エラーが返却されることを確認する
        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJson([
                'message' => 'このログインIDは既に使用されています',
            ]);
    }

    /**
     * FUU-008
     * 異常: email 重複
     */
    #[Test]
    public function test_fails_when_email_already_exists(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 既に同じemailを持つ別ユーザーを作成する
        $this->createUser([
            'email' => 'existing@example.com',
            'login_id' => 'existing',
        ]);

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // 重複しているemailへ変更を試みる
        $response = $this->putJson($this->endpoint->user(), [
            'email' => 'existing@example.com',
        ]);

        // 重複エラーが返却されることを確認する
        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJson([
                'message' => 'このメールアドレスは既に使用されています',
            ]);
    }

    /**
     * FUU-009
     * 異常: パスワード変更で currentPassword 不一致
     */
    #[Test]
    public function test_fails_when_current_password_is_wrong(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // 誤った現在のパスワードで変更を試みる
        $response = $this->putJson($this->endpoint->user(), [
            'currentPassword' => 'wrong',
            'password' => 'newpass123',
            'passwordConfirmation' => 'newpass123',
        ]);

        // 現在パスワード不一致のエラーが返却されることを確認する
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson([
                'message' => '現在のパスワードが正しくありません',
            ]);
    }

    /**
     * FUU-010
     * 異常: バリデーションエラー
     */
    #[Test]
    public function test_fails_validation_error(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // 文字数制限を超えたloginIdで更新を試みる
        $response = $this->putJson($this->endpoint->user(), [
            'loginId' => str_repeat('a', 16),
        ]);

        // バリデーションエラーが返却されることを確認する
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors([
                'loginId',
            ]);
    }

    /**
     * FUU-011
     * 異常: 未認証
     */
    #[Test]
    public function test_cannot_update_user_without_authentication(): void
    {
        // 認証なしでユーザー更新APIへアクセスする
        $response = $this->putJson($this->endpoint->user(), [
            'name' => '新太郎',
        ]);

        // 認証エラーが返却されることを確認する
        $response->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJsonFragment([
                'message' => '認証が必要です',
            ]);
    }
}
