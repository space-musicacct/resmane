<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    private ApiEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = new V1ApiEndpoint;
    }

    /**
     * FUS-001
     * 正常: ユーザー情報取得
     *
     * 認証済みユーザーが自身のユーザー情報を取得できることを確認する
     */
    #[Test]
    public function test_can_get_authenticated_user_information(): void
    {
        // テスト用ユーザーを作成
        $user = User::create([
            'login_id' => 'test_user',
            'email' => 'test@example.com',
            'name' => 'テストユーザー',
            'password_hash' => Hash::make('password123'),
        ]);

        // 作成したユーザーで認証
        Sanctum::actingAs($user);

        // ユーザー情報取得APIを実行
        $response = $this->getJson($this->endpoint->user());

        // レスポンスステータスと返却データを確認
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                // ユーザー情報のレスポンス構造を確認
                'data' => [
                    'id',
                    'loginId',
                    'email',
                    'name',
                    'createdAt',
                    'updatedAt',
                ],
            ])
            // 取得したユーザー情報が正しいことを確認
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'loginId' => 'test_user',
                    'email' => 'test@example.com',
                    'name' => 'テストユーザー',
                ],
            ]);
    }

    /**
     * FUS-002
     * 異常: 未認証
     *
     * 認証されていないユーザーはユーザー情報を取得できないことを確認する
     */
    #[Test]
    public function test_cannot_get_user_information_without_authentication(): void
    {
        // 未認証状態でユーザー情報取得APIを実行
        $response = $this->getJson($this->endpoint->user());

        // 認証エラーが返却されることを確認
        $response->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJsonFragment([
                'message' => '認証が必要です',
            ]);
    }
}
