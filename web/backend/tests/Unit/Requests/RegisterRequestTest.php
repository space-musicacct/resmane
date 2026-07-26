<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\V1\RegisterRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithValidation;

/**
 * 単体テスト仕様書 1.1 RegisterRequest 対応テスト
 *
 * DB 依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class RegisterRequestTest extends TestCase
{
    use InteractsWithValidation;

    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return new RegisterRequest()->rules();
    }

    /**
     * デフォルトの正常系入力データ。
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'loginId' => 'taro123',
            'email' => 'taro@example.com',
            'name' => '太郎',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ], $overrides);
    }

    /**
     * Validatorを生成する共通処理。
     */
    private function validator(array $data): Validator
    {
        return ValidatorFacade::make(
            $data,
            $this->rules()
        );
    }

    /**
     * UR-001: 正常: 全フィールド有効
     */
    #[Test]
    public function test_u_r_001_all_fields_valid_passes_validation(): void
    {
        $this->assertValid(
            $this->validData()
        );
    }

    /**
     * UR-002: 異常: loginId 未入力
     */
    #[Test]
    public function test_u_r_002_login_id_empty_fails_required(): void
    {
        $this->assertInvalid(
            $this->validData(['loginId' => '']),
            'loginId',
            'required'
        );
    }

    /**
     * UR-003: 異常: loginId 16文字
     */
    #[Test]
    public function test_u_r_003_login_id_16_chars_fails_max(): void
    {
        $this->assertInvalid(
            $this->validData(['loginId' => str_repeat('1', 16)]),
            'loginId',
            'max'
        );
    }

    /**
     * UR-004: 異常: email 未入力
     */
    #[Test]
    public function test_u_r_004_email_empty_fails_required(): void
    {
        $this->assertInvalid(
            $this->validData(['email' => '']),
            'email',
            'required'
        );
    }

    /**
     * UR-005: 異常: email 形式不正
     */
    #[Test]
    public function test_u_r_005_email_invalid_format_fails_email(): void
    {
        $this->assertInvalid(
            $this->validData(['email' => 'not-email']),
            'email',
            'email'
        );
    }

    /**
     * UR-006: 異常: email 256文字
     */
    #[Test]
    public function test_u_r_006_email_256_chars_fails_max(): void
    {
        // "@example.com" (12文字) を除いた244文字のローカル部を付与し、
        // 合計256文字（255文字超）のメールアドレスを作る
        $localPart = str_repeat('a', 244);
        $email = $localPart.'@example.com';
        $this->assertSame(256, strlen($email));

        $this->assertInvalid(
            $this->validData(['email' => $email]),
            'email',
            'max'
        );
    }

    /**
     * UR-007: 異常: name 未入力
     */
    #[Test]
    public function test_u_r_007_name_empty_fails_required(): void
    {
        $this->assertInvalid(
            $this->validData(['name' => '']),
            'name',
            'required'
        );
    }

    /**
     * UR-008: 異常: name 51文字
     */
    #[Test]
    public function test_u_r_008_name_51_chars_fails_max(): void
    {
        $this->assertInvalid(
            $this->validData(['name' => str_repeat('あ', 51)]),
            'name',
            'max'
        );
    }

    /**
     * UR-009: 異常: password 未入力
     */
    #[Test]
    public function test_u_r_009_password_empty_fails_required(): void
    {
        $this->assertInvalid(
            $this->validData([
                'password' => '',
                'passwordConfirmation' => '',
            ]),
            'password',
            'required'
        );
    }

    /**
     * UR-010: 異常: password 7文字
     */
    #[Test]
    public function test_u_r_010_password_7_chars_fails_min(): void
    {
        $this->assertInvalid(
            $this->validData([
                'password' => '1234567',
                'passwordConfirmation' => '1234567',
            ]),
            'password',
            'min'
        );
    }

    /**
     * UR-011: 異常: passwordConfirmation 不一致
     */
    #[Test]
    public function test_u_r_011_password_confirmation_mismatch_fails_same(): void
    {
        $this->assertInvalid(
            $this->validData([
                'password' => 'password123',
                'passwordConfirmation' => 'different',
            ]),
            'passwordConfirmation',
            'same'
        );
    }

    /**
     * UR-012: 異常: passwordConfirmation 未入力
     */
    #[Test]
    public function test_u_r_012_password_confirmation_empty_fails_required(): void
    {
        $this->assertInvalid(
            $this->validData(['passwordConfirmation' => '']),
            'passwordConfirmation',
            'required'
        );
    }

    /**
     * UR-013: 境界値: loginId 15文字
     */
    #[Test]
    public function test_u_r_013_login_id_15_chars_passes(): void
    {
        $this->assertValid(
            $this->validData(['loginId' => str_repeat('1', 15)])
        );
    }

    /**
     * UR-014: 境界値: password 8文字
     */
    #[Test]
    public function test_u_r_014_password_8_chars_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'password' => '12345678',
                'passwordConfirmation' => '12345678',
            ])
        );
    }

    /**
     * UR-015: 境界値: name 50文字
     */
    #[Test]
    public function test_u_r_015_name_50_chars_passes(): void
    {
        $this->assertValid(
            $this->validData(['name' => str_repeat('あ', 50)])
        );
    }
}
