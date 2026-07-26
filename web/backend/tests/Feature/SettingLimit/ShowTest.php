<?php

declare(strict_types=1);

namespace Tests\Feature\SettingLimit;

use App\Models\UpperLimitSetting;
use App\Models\UpperLimitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    // 上限設定取得APIのエンドポイント
    private const ENDPOINT = '/api/v1/settings/limit';

    // 認証済みテストユーザー
    protected User $user;

    /**
     * テスト実行前の共通初期化処理
     *
     * テスト用ユーザーを作成し、
     * 認証状態にする
     */
    protected function setUp(): void
    {
        parent::setUp();

        // テスト用ユーザー作成
        $this->user = User::create([
            'login_id' => 'testuser',
            'email' => 'test@example.com',
            'name' => 'テストユーザー',
            'password_hash' => Hash::make('password123'),
        ]);

        // 作成したユーザーで認証
        $this->actingAs($this->user);
    }

    /**
     * FSLS-001
     * 正常系:
     * 上限設定が登録済みの場合、設定情報を取得できることを確認
     */
    /** @test FSLS-001 正常: 設定が存在する */
    public function test_show_returns_setting_when_setting_exists(): void
    {
        // 上限種類データを作成
        $type = UpperLimitType::create([
            'type_name' => '金額',
        ]);

        // ユーザーの上限設定を作成
        UpperLimitSetting::create([
            'user_id' => $this->user->id,
            'upper_limit_type_id' => $type->id,
            'max_value' => 50000,
            'ave_monthly_income' => null,
        ]);

        // 上限設定取得APIを実行
        $response = $this->getJson(self::ENDPOINT);

        // レスポンス内容を検証
        $response
            ->assertStatus(200)
            ->assertJsonPath('data.userId', $this->user->id)
            ->assertJsonPath('data.upperLimitTypeId', $type->id)
            ->assertJsonPath('data.upperLimitTypeName', '金額')
            ->assertJsonPath('data.maxValue', 50000);
    }

    /**
     * FSLS-002
     * 正常系:
     * 上限設定が存在しない場合、dataがnullで返却されることを確認
     */
    /** @test FSLS-002 正常: 設定が未登録 */
    public function test_show_returns_null_when_setting_not_exists(): void
    {
        // 設定未登録状態でAPI実行
        $response = $this->getJson(self::ENDPOINT);

        // dataがnullであることを確認
        $response
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    /**
     * FSLS-003
     * 正常系:
     * 上限タイプが割合の場合、タイプ名が正しく返却されることを確認
     */
    /** @test FSLS-003 正常: upperLimitTypeName が正しい */
    public function test_show_returns_percentage_type_name(): void
    {
        // 割合タイプの上限種類を作成
        $type = new UpperLimitType();
        $type->id = UpperLimitType::PERCENTAGE_ID;
        $type->type_name = '割合';
        $type->save();

        // 割合タイプの上限設定を作成
        UpperLimitSetting::create([
            'user_id' => $this->user->id,
            'upper_limit_type_id' => UpperLimitType::PERCENTAGE_ID,
            'max_value' => 30,
            'ave_monthly_income' => 300000,
        ]);

        // 上限設定取得APIを実行
        $response = $this->getJson(self::ENDPOINT);

        // 割合タイプ情報が正しいことを確認
        $response
            ->assertStatus(200)
            ->assertJsonPath('data.upperLimitTypeId', UpperLimitType::PERCENTAGE_ID)
            ->assertJsonPath('data.upperLimitTypeName', '割合');
    }

    /**
     * FSLS-004
     * 異常系:
     * 未認証ユーザーはアクセスできないことを確認
     */
    /** @test FSLS-004 異常: 未認証 */
    public function test_show_requires_authentication(): void
    {
        // 認証状態を解除
        auth()->logout();

        // 未認証状態でAPI実行
        $response = $this->getJson(self::ENDPOINT);

        // 401 Unauthorizedが返却されることを確認
        $response->assertStatus(401);
    }
}
