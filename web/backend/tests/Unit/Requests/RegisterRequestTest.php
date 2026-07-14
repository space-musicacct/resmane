<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\RegisterRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 単体テスト仕様書 1.1 RegisterRequest 対応テスト
 *
 * DB 依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class RegisterRequestTest extends TestCase
{
    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return (new RegisterRequest())->rules();
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
    public function UR_001_全フィールド有効な場合はバリデーションを通過する(): void
    {
        $validator = $this->validator($this->validData());

        $this->assertFalse($validator->fails());
    }

    /**
     * UR-002: 異常: loginId 未入力
     */
    #[Test]
    public function UR_002_loginId未入力の場合はrequiredエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'loginId' => ''
            ])
        );

        $this->assertValidationError(
            $validator,
            'loginId',
            'required'
        );
    }

    /**
     * UR-003: 異常: loginId 16文字
     */
    #[Test]
    public function UR_003_loginIdが16文字の場合はmaxエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'loginId' => str_repeat('1', 16),
            ])
        );

        $this->assertValidationError(
            $validator,
            'loginId',
            'max'
        );
    }

    /**
     * UR-004: 異常: email 未入力
     */
    #[Test]
    public function UR_004_email未入力の場合はrequiredエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'email' => '',
            ])
        );

        $this->assertValidationError(
            $validator,
            'email',
            'required'
        );
    }

    /**
     * UR-005: 異常: email 形式不正
     */
    #[Test]
    public function UR_005_email形式が不正な場合はemailエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'email' => 'not-email',
            ])
        );

        $this->assertValidationError(
            $validator,
            'email',
            'email'
        );
    }

    /**
     * UR-006: 異常: email 256文字
     */
    #[Test]
    public function UR_006_emailが256文字の場合はmaxエラーになる(): void
    {
        // "@example.com" (12文字) を除いた244文字のローカル部を付与し、
        // 合計256文字（255文字超）のメールアドレスを作る
        $localPart = str_repeat('a', 244);
        $email = $localPart . '@example.com';
        $this->assertSame(256, strlen($email));

        $validator = $this->validator(
            $this->validData([
                'email' => $email,
            ])
        );

        $this->assertValidationError(
            $validator,
            'email',
            'max'
        );
    }

    /**
     * UR-007: 異常: name 未入力
     */
    #[Test]
    public function UR_007_name未入力の場合はrequiredエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'name' => '',
            ])
        );

        $this->assertValidationError(
            $validator,
            'name',
            'required'
        );
    }

    /**
     * UR-008: 異常: name 51文字
     */
    #[Test]
    public function UR_008_nameが51文字の場合はmaxエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'name' => str_repeat('あ', 51),
            ])
        );

        $this->assertValidationError(
            $validator,
            'name',
            'max'
        );
    }

    /**
     * UR-009: 異常: password 未入力
     */
    #[Test]
    public function UR_009_password未入力の場合はrequiredエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'password' => '',
                'passwordConfirmation' => '',
            ])
        );

        $this->assertValidationError(
            $validator,
            'password',
            'required'
        );
    }

    /**
     * UR-010: 異常: password 7文字
     */
    #[Test]
    public function UR_010_passwordが7文字の場合はminエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'password' => '1234567',
                'passwordConfirmation' => '1234567',
            ])
        );

        $this->assertValidationError(
            $validator,
            'password',
            'min'
        );
    }

    /**
     * UR-011: 異常: passwordConfirmation 不一致
     */
    #[Test]
    public function UR_011_passwordConfirmationが不一致の場合はsameエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'password' => 'password123',
                'passwordConfirmation' => 'different',
            ])
        );

        $this->assertValidationError(
            $validator,
            'passwordConfirmation',
            'same'
        );
    }

    /**
     * UR-012: 異常: passwordConfirmation 未入力
     */
    #[Test]
    public function UR_012_passwordConfirmation未入力の場合はrequiredエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'passwordConfirmation' => '',
            ])
        );

        $this->assertValidationError(
            $validator,
            'passwordConfirmation',
            'required'
        );
    }

    /**
     * UR-013: 境界値: loginId 15文字
     */
    #[Test]
    public function UR_013_loginIdが15文字の場合はバリデーションを通過する(): void
    {
        $validator = $this->validator(
            $this->validData([
                'loginId' => str_repeat('1', 15),
            ])
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * UR-014: 境界値: password 8文字
     */
    #[Test]
    public function UR_014_passwordが8文字の場合はバリデーションを通過する(): void
    {
        $validator = $this->validator(
            $this->validData([
                'password' => '12345678',
                'passwordConfirmation' => '12345678',
            ])
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * UR-015: 境界値: name 50文字
     */
    #[Test]
    public function UR_015_nameが50文字の場合はバリデーションを通過する(): void
    {
        $validator = $this->validator(
            $this->validData([
                'name' => str_repeat('あ', 50),
            ])
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * バリデーションエラー共通アサーション
     */
    private function assertValidationError(
        Validator $validator,
        string $field,
        string $rule
    ): void {
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has($field));
        $this->assertSame($rule, $this->firstRule($validator, $field));
    }

    /**
     * 指定フィールドで最初に検出されたルール名を取得する。
     */
    private function firstRule(
        Validator $validator,
        string $field
    ): ?string {
        $failed = $validator->failed();

        if (! isset($failed[$field])) {
            return null;
        }

        return strtolower(array_key_first($failed[$field]));
    }
}
