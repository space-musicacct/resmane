<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\V1\SelfReviewStoreRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithValidation;

/**
 * 単体テスト仕様書 1.6 SelfReviewStoreRequest 対応テスト
 *
 * DB依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class SelfReviewStoreRequestTest extends TestCase
{
    use InteractsWithValidation;

    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return new SelfReviewStoreRequest()->rules();
    }

    /**
     * デフォルトの正常系入力データ。
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'reviewComment' => '良い買い物だった',
            'evaluation' => 3,
        ], $overrides);
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
     * USR-001: 正常: 全フィールド有効
     */
    #[Test]
    public function test_USR_001_all_fields_valid_passes_validation(): void
    {
        $this->assertValid(
            $this->validData()
        );
    }

    /**
     * USR-002: 異常: reviewComment 未入力
     */
    #[Test]
    public function test_USR_002_reviewComment_empty_fails_required(): void
    {
        $this->assertInvalid(
            $this->validData([
                'reviewComment' => '',
            ]),
            'reviewComment',
            'required'
        );
    }

    /**
     * USR-003: 異常: reviewComment 251文字
     */
    #[Test]
    public function test_USR_003_reviewComment_251_chars_fails_max(): void
    {
        $this->assertInvalid(
            $this->validData([
                'reviewComment' => str_repeat('あ', 251),
            ]),
            'reviewComment',
            'max'
        );
    }

    /**
     * USR-004: 異常: evaluation 未入力
     */
    #[Test]
    public function test_USR_004_evaluation_empty_fails_required(): void
    {
        $this->assertInvalid(
            $this->validData([
                'evaluation' => '',
            ]),
            'evaluation',
            'required'
        );
    }

    /**
     * USR-005: 異常: evaluation 0
     */
    #[Test]
    public function test_USR_005_evaluation_zero_fails_min(): void
    {
        $this->assertInvalid(
            $this->validData([
                'evaluation' => 0,
            ]),
            'evaluation',
            'min'
        );
    }

    /**
     * USR-006: 異常: evaluation 6
     */
    #[Test]
    public function test_USR_006_evaluation_six_fails_max(): void
    {
        $this->assertInvalid(
            $this->validData([
                'evaluation' => 6,
            ]),
            'evaluation',
            'max'
        );
    }

    /**
     * USR-007: 異常: evaluation 小数
     */
    #[Test]
    public function test_USR_007_evaluation_decimal_fails_integer(): void
    {
        $this->assertInvalid(
            $this->validData([
                'evaluation' => 2.5,
            ]),
            'evaluation',
            'integer'
        );
    }

    /**
     * USR-008: 境界値: reviewComment 250文字
     */
    #[Test]
    public function test_USR_008_reviewComment_250_chars_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'reviewComment' => str_repeat('あ', 250),
            ])
        );
    }

    /**
     * USR-009: 境界値: reviewComment 1文字
     */
    #[Test]
    public function test_USR_009_reviewComment_one_char_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'reviewComment' => 'あ',
            ])
        );
    }

    /**
     * USR-010: 境界値: evaluation 1
     */
    #[Test]
    public function test_USR_010_evaluation_one_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'evaluation' => 1,
            ])
        );
    }

    /**
     * USR-011: 境界値: evaluation 5
     */
    #[Test]
    public function test_USR_011_evaluation_five_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'evaluation' => 5,
            ])
        );
    }

}
