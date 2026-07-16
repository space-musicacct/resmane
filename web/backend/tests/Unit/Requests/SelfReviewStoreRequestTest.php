<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\SelfReviewStoreRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 単体テスト仕様書 1.6 SelfReviewStoreRequest 対応テスト
 *
 * DB依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class SelfReviewStoreRequestTest extends TestCase
{
    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return (new SelfReviewStoreRequest())->rules();
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
    public function USR_001_全フィールド有効な場合はバリデーションを通過する(): void
    {
        $this->assertValid(
            $this->validData()
        );
    }

    /**
     * USR-002: 異常: reviewComment 未入力
     */
    #[Test]
    public function USR_002_reviewComment未入力の場合はrequiredエラーになる(): void
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
    public function USR_003_reviewCommentが251文字の場合はmaxエラーになる(): void
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
    public function USR_004_evaluation未入力の場合はrequiredエラーになる(): void
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
    public function USR_005_evaluationが0の場合はminエラーになる(): void
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
    public function USR_006_evaluationが6の場合はmaxエラーになる(): void
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
    public function USR_007_evaluationが小数の場合はintegerエラーになる(): void
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
    public function USR_008_reviewCommentが250文字の場合は通過する(): void
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
    public function USR_009_reviewCommentが1文字の場合は通過する(): void
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
    public function USR_010_evaluationが1の場合は通過する(): void
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
    public function USR_011_evaluationが5の場合は通過する(): void
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
