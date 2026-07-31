<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\V1\SelfReviewUpdateRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithValidation;

/**
 * 単体テスト仕様書 1.7 SelfReviewUpdateRequest 対応テスト
 *
 * SelfReviewStoreRequest と同一のルールのため、同等のテストケースを実施する。
 * DB依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class SelfReviewUpdateRequestTest extends TestCase
{
    use InteractsWithValidation;

    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return new SelfReviewUpdateRequest()->rules();
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
     * USRU-001: 正常: 全フィールド有効
     */
    #[Test]
    public function test_usr_u_001_all_fields_valid_passes_validation(): void
    {
        $this->assertValid(
            $this->validData()
        );
    }

    /**
     * USRU-002: 異常: reviewComment 未入力
     */
    #[Test]
    public function test_usr_u_002_review_comment_empty_fails_required(): void
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
     * USRU-003: 異常: reviewComment 251文字
     */
    #[Test]
    public function test_usr_u_003_review_comment_251_chars_fails_max(): void
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
     * USRU-004: 異常: evaluation 未入力
     */
    #[Test]
    public function test_usr_u_004_evaluation_empty_fails_required(): void
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
     * USRU-005: 異常: evaluation 0
     */
    #[Test]
    public function test_usr_u_005_evaluation_zero_fails_min(): void
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
     * USRU-006: 異常: evaluation 6
     */
    #[Test]
    public function test_usr_u_006_evaluation_6_fails_max(): void
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
     * USRU-007: 異常: evaluation 小数
     */
    #[Test]
    public function test_usr_u_007_evaluation_decimal_fails_integer(): void
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
     * USRU-008: 境界値: reviewComment 250文字
     */
    #[Test]
    public function test_usr_u_008_review_comment_250_chars_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'reviewComment' => str_repeat('あ', 250),
            ])
        );
    }

    /**
     * USRU-009: 境界値: reviewComment 1文字
     */
    #[Test]
    public function test_usr_u_009_review_comment_1_char_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'reviewComment' => 'あ',
            ])
        );
    }

    /**
     * USRU-010: 境界値: evaluation 1
     */
    #[Test]
    public function test_usr_u_010_evaluation_1_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'evaluation' => 1,
            ])
        );
    }

    /**
     * USRU-011: 境界値: evaluation 5
     */
    #[Test]
    public function test_usr_u_011_evaluation_5_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'evaluation' => 5,
            ])
        );
    }
}
