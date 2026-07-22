<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

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
    public function test_can_update_login_id(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // loginId変更リクエストを送信する
        $response = $this->putJson('/api/v1/user', [
            'loginId' => 'newtaro',
        ]);

        // レスポンスに変更後のloginIdが反映されていることを確認する
        $response->assertStatus(200)
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
    public function test_can_update_name(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // name変更リクエストを送信する
        $response = $this->putJson('/api/v1/user', [
            'name' => '新太郎',
        ]);

        // レスポンスに変更後の名前が反映されていることを確認する
        $response->assertStatus(200)
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
    public function test_can_update_email(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // email変更リクエストを送信する
        $response = $this->putJson('/api/v1/user', [
            'email' => 'new@example.com',
        ]);

        // レスポンスに変更後のメールアドレスが反映されていることを確認する
        $response->assertStatus(200)
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
    public function test_can_update_password(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // 現在のパスワード確認を含めて新しいパスワードへ変更する
        $response = $this->putJson('/api/v1/user', [
            'currentPassword' => 'oldpassword',
            'password' => 'newpass123',
            'passwordConfirmation' => 'newpass123',
        ]);

        // 更新処理が成功したことを確認する
        $response->assertStatus(200);

        // 最新状態のユーザー情報を取得する
        $user->refresh();

        // 保存されたパスワードが新しい値になっていることを確認する
        $this->assertTrue(
            Hash::check('newpass123', $user->password_hash)
        );
    }

    /**
     * FUU-005
     * 正常: 複数フィールド同時変更
     */
    public function test_can_update_multiple_fields(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // 複数項目を同時に更新するリクエストを送信する
        $response = $this->putJson('/api/v1/user', [
            'loginId' => 'newtaro',
            'name' => '新太郎',
            'email' => 'new@example.com',
        ]);

        // すべての更新内容がレスポンスに反映されていることを確認する
        $response->assertStatus(200)
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
        $response = $this->putJson('/api/v1/user', [
            'loginId' => 'existing',
        ]);

        // 重複エラーが返却されることを確認する
        $response->assertStatus(409)
            ->assertJson([
                'message' => 'このログインIDは既に使用されています',
            ]);
    }

    /**
     * FUU-007
     * 異常: loginId 重複（論理削除済み）
     */
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
        $response = $this->putJson('/api/v1/user', [
            'loginId' => 'deleted_login',
        ]);

        // 重複エラーが返却されることを確認する
        $response->assertStatus(409)
            ->assertJson([
                'message' => 'このログインIDは既に使用されています',
            ]);
    }

    /**
     * FUU-008
     * 異常: email 重複
     */
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
        $response = $this->putJson('/api/v1/user', [
            'email' => 'existing@example.com',
        ]);

        // 重複エラーが返却されることを確認する
        $response->assertStatus(409)
            ->assertJson([
                'message' => 'このメールアドレスは既に使用されています',
            ]);
    }
    /**
     * FUU-009
     * 異常: パスワード変更で currentPassword 不一致
     */
    public function test_fails_when_current_password_is_wrong(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // 誤った現在のパスワードで変更を試みる
        $response = $this->putJson('/api/v1/user', [
            'currentPassword' => 'wrong',
            'password' => 'newpass123',
            'passwordConfirmation' => 'newpass123',
        ]);

        // 現在パスワード不一致のエラーが返却されることを確認する
        $response->assertStatus(422)
            ->assertJson([
                'message' => '現在のパスワードが正しくありません',
            ]);
    }

    /**
     * FUU-010
     * 異常: バリデーションエラー
     */
    public function test_fails_validation_error(): void
    {
        // 更新対象となるユーザーを作成する
        $user = $this->createUser();

        // 認証済みユーザーとしてAPIを実行する
        Sanctum::actingAs($user);

        // 文字数制限を超えたloginIdで更新を試みる
        $response = $this->putJson('/api/v1/user', [
            'loginId' => str_repeat('a', 16),
        ]);

        // バリデーションエラーが返却されることを確認する
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'loginId',
            ]);
    }

    /**
     * FUU-011
     * 異常: 未認証
     */
    public function test_cannot_update_user_without_authentication(): void
    {
        // 認証なしでユーザー更新APIへアクセスする
        $response = $this->putJson('/api/v1/user', [
            'name' => '新太郎',
        ]);

        // 認証エラーが返却されることを確認する
        $response->assertStatus(401)
            ->assertJsonFragment([
                'message' => '認証が必要です',
            ]);
    }
}
