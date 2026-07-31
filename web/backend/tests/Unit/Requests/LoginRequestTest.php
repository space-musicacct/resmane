<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\V1\LoginRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithValidation;

/**
 * 単体テスト仕様書 1.2 LoginRequest 対応テスト
 *
 * DB 依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class LoginRequestTest extends TestCase
{
    use InteractsWithValidation;

    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return new LoginRequest()->rules();
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
    public function test_u_l_001_all_fields_valid_passes_validation(): void
    {
        $this->assertValid(
            $this->validData()
        );
    }

    /**
     * UL-002: 異常: loginId 未入力
     */
    #[Test]
    public function test_u_l_002_login_id_empty_fails_required(): void
    {
        $this->assertInvalid(
            $this->validData(['loginId' => '']),
            'loginId',
            'required'
        );
    }

    /**
     * UL-003: 異常: password 未入力
     */
    #[Test]
    public function test_u_l_003_password_empty_fails_required(): void
    {
        $this->assertInvalid(
            $this->validData(['password' => '']),
            'password',
            'required'
        );
    }
}
