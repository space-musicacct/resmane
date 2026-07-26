<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\V1\UserUpdateRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithValidation;

/**
 * 単体テスト仕様書 1.9 UserUpdateRequest 対応テスト
 *
 * 各フィールドは基本的に独立して省略可能なため、テストケースごとに
 * 必要なフィールドのみを入力データとして組み立てる。
 * DB依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class UserUpdateRequestTest extends TestCase
{
    use InteractsWithValidation;

    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return new UserUpdateRequest()->rules();
    }

    /**
     * Validator生成共通処理。
     */
    private function validator(array $data): Validator
    {
        return ValidatorFacade::make(
            $data,
            $this->rules()
        );
    }

    /**
     * UUU-001: 正常: loginId のみ変更
     */
    #[Test]
    public function test_UUU_001_loginId_only_passes(): void
    {
        $this->assertValid([
            'loginId' => 'newtaro',
        ]);
    }

    /**
     * UUU-002: 正常: name のみ変更
     */
    #[Test]
    public function test_UUU_002_name_only_passes(): void
    {
        $this->assertValid([
            'name' => '新太郎',
        ]);
    }

    /**
     * UUU-003: 正常: email のみ変更
     */
    #[Test]
    public function test_UUU_003_email_only_passes(): void
    {
        $this->assertValid([
            'email' => 'new@example.com',
        ]);
    }

    /**
     * UUU-004: 正常: パスワード変更
     */
    #[Test]
    public function test_UUU_004_password_change_passes(): void
    {
        $this->assertValid([
            'currentPassword' => 'old',
            'password' => 'newpass123',
            'passwordConfirmation' => 'newpass123',
        ]);
    }

    /**
     * UUU-005: 異常: loginId 16文字
     */
    #[Test]
    public function test_UUU_005_loginId_16_chars_fails_max(): void
    {
        $this->assertInvalid(
            ['loginId' => '1234567890123456'],
            'loginId',
            'max'
        );
    }

    /**
     * UUU-006: 異常: name 51文字
     */
    #[Test]
    public function test_UUU_006_name_51_chars_fails_max(): void
    {
        $this->assertInvalid(
            ['name' => str_repeat('あ', 51)],
            'name',
            'max'
        );
    }

    /**
     * UUU-007: 異常: email 形式不正
     */
    #[Test]
    public function test_UUU_007_email_invalid_format_fails_email(): void
    {
        $this->assertInvalid(
            ['email' => 'invalid'],
            'email',
            'email'
        );
    }

    /**
     * UUU-008: 異常: password 指定時に currentPassword なし
     */
    #[Test]
    public function test_UUU_008_password_without_currentPassword_fails_required_with(): void
    {
        $this->assertInvalid(
            [
                'password' => 'newpass123',
                'passwordConfirmation' => 'newpass123',
                'currentPassword' => '',
            ],
            'currentPassword',
            'required_with'
        );
    }

    /**
     * UUU-009: 異常: password 7文字
     */
    #[Test]
    public function test_UUU_009_password_7_chars_fails_min(): void
    {
        $this->assertInvalid(
            [
                'currentPassword' => 'old',
                'password' => '1234567',
                'passwordConfirmation' => '1234567',
            ],
            'password',
            'min'
        );
    }

    /**
     * UUU-010: 異常: passwordConfirmation 不一致
     */
    #[Test]
    public function test_UUU_010_passwordConfirmation_mismatch_fails_same(): void
    {
        $this->assertInvalid(
            [
                'currentPassword' => 'old',
                'password' => 'newpass123',
                'passwordConfirmation' => 'different',
            ],
            'passwordConfirmation',
            'same'
        );
    }

}
