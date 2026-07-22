<?php

declare(strict_types=1);

namespace Tests\Feature\SettingLimit;

use App\Models\UpperLimitSetting;
use App\Models\UpperLimitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    // 上限設定更新APIのエンドポイント
    private const ENDPOINT = '/api/v1/settings/limit';

    // テスト用認証ユーザー
    protected User $user;

    /**
     * テスト実行前の共通初期化処理
     *
     * テスト用ユーザーを作成し、
     * 認証済み状態にする
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
     * 上限タイプデータを作成するヘルパーメソッド
     *
     * 指定したIDと名称で上限タイプを登録する
     */
    private function createUpperLimitType(int $id, string $name): UpperLimitType
    {
        // 上限タイプ作成
        $type = new UpperLimitType();
        $type->id = $id;
        $type->type_name = $name;
        $type->save();

        return $type;
    }

    /**
     * FSLU-001
     * 正常系:
     * 設定が存在しない場合、新規作成されることを確認
     */
    /** @test FSLU-001 正常: 新規作成（upsert） */
    public function test_update_creates_new_setting_when_not_exists(): void
    {
        // 金額タイプを作成
        $this->createUpperLimitType(
            UpperLimitType::FIXED_AMOUNT_ID,
            '金額'
        );

        // 上限設定登録API実行
        $response = $this->putJson(self::ENDPOINT, [
            'upperLimitTypeId' => UpperLimitType::FIXED_AMOUNT_ID,
            'maxValue' => 50000,
            'aveMonthlyIncome' => null,
        ]);

        // レスポンス内容確認
        $response
            ->assertStatus(200)
            ->assertJsonPath('data.userId', $this->user->id)
            ->assertJsonPath('data.upperLimitTypeId', UpperLimitType::FIXED_AMOUNT_ID)
            ->assertJsonPath('data.maxValue', 50000);

        // DB登録確認
        $this->assertDatabaseHas('upper_limit_settings', [
            'user_id' => $this->user->id,
            'upper_limit_type_id' => UpperLimitType::FIXED_AMOUNT_ID,
            'max_value' => 50000,
        ]);
    }

    /**
     * FSLU-002
     * 正常系:
     * 既存設定が更新されることを確認
     */
    /** @test FSLU-002 正常: 更新（upsert） */
    public function test_update_updates_existing_setting(): void
    {
        // 金額タイプを作成
        $this->createUpperLimitType(
            UpperLimitType::FIXED_AMOUNT_ID,
            '金額'
        );

        // 更新対象となる既存設定作成
        UpperLimitSetting::create([
            'user_id' => $this->user->id,
            'upper_limit_type_id' => UpperLimitType::FIXED_AMOUNT_ID,
            'max_value' => 50000,
            'ave_monthly_income' => null,
        ]);

        // 更新API実行
        $response = $this->putJson(self::ENDPOINT, [
            'upperLimitTypeId' => UpperLimitType::FIXED_AMOUNT_ID,
            'maxValue' => 60000,
            'aveMonthlyIncome' => null,
        ]);

        // 更新結果確認
        $response
            ->assertStatus(200)
            ->assertJsonPath('data.maxValue', 60000);

        // DB更新確認
        $this->assertDatabaseHas('upper_limit_settings', [
            'user_id' => $this->user->id,
            'max_value' => 60000,
        ]);
    }

    /**
     * FSLU-003
     * 正常系:
     * 割合タイプの場合、平均月収を指定できることを確認
     */
    /** @test FSLU-003 正常: 割合指定時に aveMonthlyIncome 必須 */
    public function test_update_accepts_percentage_with_average_monthly_income(): void
    {
        // 割合タイプ作成
        $this->createUpperLimitType(
            UpperLimitType::PERCENTAGE_ID,
            '割合'
        );

        // 更新API実行
        $response = $this->putJson(self::ENDPOINT, [
            'upperLimitTypeId' => UpperLimitType::PERCENTAGE_ID,
            'maxValue' => 30,
            'aveMonthlyIncome' => 200000,
        ]);

        // 割合設定情報確認
        $response
            ->assertStatus(200)
            ->assertJsonPath('data.upperLimitTypeName', '割合')
            ->assertJsonPath('data.aveMonthlyIncome', 200000);
    }

    /**
     * FSLU-004
     * 正常系:
     * 固定額の場合、平均月収を省略できることを確認
     */
    /** @test FSLU-004 正常: 固定額指定時に aveMonthlyIncome 省略可 */
    public function test_update_accepts_fixed_amount_without_average_monthly_income(): void
    {
        // 金額タイプ作成
        $this->createUpperLimitType(
            UpperLimitType::FIXED_AMOUNT_ID,
            '金額'
        );

        // 更新API実行
        $response = $this->putJson(self::ENDPOINT, [
            'upperLimitTypeId' => UpperLimitType::FIXED_AMOUNT_ID,
            'maxValue' => 50000,
            'aveMonthlyIncome' => null,
        ]);

        // 平均月収がnullであることを確認
        $response
            ->assertStatus(200)
            ->assertJsonPath('data.aveMonthlyIncome', null);
    }

    /**
     * FSLU-005
     * 異常系:
     * 割合タイプで平均月収未入力の場合、エラーになることを確認
     */
    /** @test FSLU-005 異常: 割合指定時に aveMonthlyIncome なし */
    public function test_update_fails_when_percentage_without_average_monthly_income(): void
    {
        // 割合タイプ作成
        $this->createUpperLimitType(
            UpperLimitType::PERCENTAGE_ID,
            '割合'
        );

        // 不正な値で更新API実行
        $response = $this->putJson(self::ENDPOINT, [
            'upperLimitTypeId' => UpperLimitType::PERCENTAGE_ID,
            'maxValue' => 30,
            'aveMonthlyIncome' => null,
        ]);

        // バリデーションエラー確認
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aveMonthlyIncome']);
    }

    /**
     * FSLU-006
     * 異常系:
     * 存在しない上限タイプIDは登録できないことを確認
     */
    /** @test FSLU-006 異常: 存在しない upperLimitTypeId */
    public function test_update_fails_when_upper_limit_type_not_exists(): void
    {
        // 存在しないIDで更新API実行
        $response = $this->putJson(self::ENDPOINT, [
            'upperLimitTypeId' => 999,
            'maxValue' => 50000,
            'aveMonthlyIncome' => null,
        ]);

        // バリデーションエラー確認
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['upperLimitTypeId']);
    }

    /**
     * FSLU-007
     * 異常系:
     * 上限金額が0の場合エラーになることを確認
     */
    /** @test FSLU-007 異常: maxValue 0 */
    public function test_update_fails_when_max_value_is_zero(): void
    {
        // 金額タイプ作成
        $this->createUpperLimitType(
            UpperLimitType::FIXED_AMOUNT_ID,
            '金額'
        );

        // 0円で更新API実行
        $response = $this->putJson(self::ENDPOINT, [
            'upperLimitTypeId' => UpperLimitType::FIXED_AMOUNT_ID,
            'maxValue' => 0,
            'aveMonthlyIncome' => null,
        ]);

        // バリデーションエラー確認
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['maxValue']);
    }

    /**
     * FSLU-008
     * 異常系:
     * 未認証ユーザーは更新できないことを確認
     */
    /** @test FSLU-008 異常: 未認証 */
    public function test_update_requires_authentication(): void
    {
        // 認証解除
        auth()->logout();

        // 未認証状態で更新API実行
        $response = $this->putJson(self::ENDPOINT, [
            'upperLimitTypeId' => UpperLimitType::FIXED_AMOUNT_ID,
            'maxValue' => 50000,
            'aveMonthlyIncome' => null,
        ]);

        // 認証エラー確認
        $response->assertStatus(401);
    }
}
