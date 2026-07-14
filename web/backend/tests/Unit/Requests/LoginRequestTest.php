<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\LoginRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 単体テスト仕様書 1.2 LoginRequest 対応テスト
 *
 * DB 依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class LoginRequestTest extends TestCase
{
    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return (new LoginRequest())->rules();
    }

    /**
     * デフォルトの正常系入力データ。
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'loginId' => 'taro123',
            'password' => 'password123',
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
     * UL-001: 正常: 全フィールド有効
     */
    #[Test]
    public function UL_001_全フィールド有効な場合はバリデーションを通過する(): void
    {
        $validator = $this->validator($this->validData());

        $this->assertFalse($validator->fails());
    }

    /**
     * UL-002: 異常: loginId 未入力
     */
    #[Test]
    public function UL_002_loginId未入力の場合はrequiredエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'loginId' => '',
            ])
        );

        $this->assertValidationError(
            $validator,
            'loginId',
            'required'
        );
    }

    /**
     * UL-003: 異常: password 未入力
     */
    #[Test]
    public function UL_003_password未入力の場合はrequiredエラーになる(): void
    {
        $validator = $this->validator(
            $this->validData([
                'password' => '',
            ])
        );

        $this->assertValidationError(
            $validator,
            'password',
            'required'
        );
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
