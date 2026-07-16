<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\SelfReviewUpdateRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 単体テスト仕様書 1.7 SelfReviewUpdateRequest 対応テスト
 *
 * SelfReviewStoreRequest と同一のルールのため、同等のテストケースを実施する。
 * DB依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class SelfReviewUpdateRequestTest extends TestCase
{
    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return (new SelfReviewUpdateRequest())->rules();
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
    public function USRU_001_全フィールド有効な場合はバリデーションを通過する(): void
    {
        $this->assertValid(
            $this->validData()
        );
    }

    /**
     * USRU-002: 異常: reviewComment 未入力
     */
    #[Test]
    public function USRU_002_reviewComment未入力の場合はrequiredエラーになる(): void
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
    public function USRU_003_reviewCommentが251文字の場合はmaxエラーになる(): void
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
    public function USRU_004_evaluation未入力の場合はrequiredエラーになる(): void
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
    public function USRU_005_evaluationが0の場合はminエラーになる(): void
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
    public function USRU_006_evaluationが6の場合はmaxエラーになる(): void
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
    public function USRU_007_evaluationが小数の場合はintegerエラーになる(): void
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
    public function USRU_008_reviewCommentが250文字の場合は通過する(): void
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
    public function USRU_009_reviewCommentが1文字の場合は通過する(): void
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
    public function USRU_010_evaluationが1の場合は通過する(): void
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
    public function USRU_011_evaluationが5の場合は通過する(): void
    {
        $this->assertValid(
            $this->validData([
                'evaluation' => 5,
            ])
        );
    }

    /**
     * 正常系共通アサーション
     */
    private function assertValid(array $data): void
    {
        $validator = $this->validator($data);

        $this->assertFalse($validator->fails());
    }

    /**
     * 異常系共通アサーション
     */
    private function assertInvalid(
        array $data,
        string $field,
        string $rule
    ): void {
        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has($field));

        $this->assertSame(
            $rule,
            $this->firstRule($validator, $field)
        );
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
