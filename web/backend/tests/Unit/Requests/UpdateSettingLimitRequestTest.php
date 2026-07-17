<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\V1\UpdateSettingLimitRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithValidation;

/**
 * 単体テスト仕様書 1.10 UpdateSettingLimitRequest 対応テスト
 *
 * upperLimitTypeId によって aveMonthlyIncome の要否が変わる（割合指定=1 の場合は必須）ため、
 * テストケースごとに必要なフィールドを個別に組み立てる。
 * DB依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class UpdateSettingLimitRequestTest extends TestCase
{
    use InteractsWithValidation;

    /**
     * リクエストデータを注入した状態で FormRequest インスタンスを生成する。
     *
     * rules() は $this->input('upperLimitTypeId') を参照して動的にルールを
     * 切り替えるため、new UpdateSettingLimitRequest() のような空インスタンスでは
     * input() が常に null となり判定できない。
     * FormRequest::create() でリクエストデータを持たせた状態で生成する。
     */
    private function makeRequest(array $data): UpdateSettingLimitRequest
    {
        /** @var UpdateSettingLimitRequest $request */
        $request = UpdateSettingLimitRequest::create(
            '/api/v1/settings/limit',
            'PUT',
            $data
        );

        return $request;
    }

    /**
     * Validator生成共通処理。
     *
     * $data から直接 rules() を組み立てるのではなく、$data を注入した
     * FormRequest インスタンス経由で rules() を取得することで、
     * upperLimitTypeId に応じた動的なルール切替を正しく検証する。
     */
    private function validator(array $data): Validator
    {
        return ValidatorFacade::make(
            $data,
            $this->makeRequest($data)->rules()
        );
    }

    /**
     * USL-001: 正常: 固定額指定
     */
    #[Test]
    public function USL_001_固定額指定の場合はバリデーションを通過する(): void
    {
        $this->assertValid([
            'upperLimitTypeId' => 2,
            'maxValue' => 50000,
            'aveMonthlyIncome' => null,
        ]);
    }

    /**
     * USL-002: 正常: 割合指定
     */
    #[Test]
    public function USL_002_割合指定の場合はバリデーションを通過する(): void
    {
        $this->assertValid([
            'upperLimitTypeId' => 1,
            'maxValue' => 30,
            'aveMonthlyIncome' => 200000,
        ]);
    }

    /**
     * USL-003: 異常: upperLimitTypeId 未入力
     */
    #[Test]
    public function USL_003_upperLimitTypeId未入力の場合はrequiredエラーになる(): void
    {
        $this->assertInvalid(
            [
                'upperLimitTypeId' => '',
                'maxValue' => 50000,
                'aveMonthlyIncome' => null,
            ],
            'upperLimitTypeId',
            'required'
        );
    }

    /**
     * USL-004: 異常: maxValue 未入力
     */
    #[Test]
    public function USL_004_maxValue未入力の場合はrequiredエラーになる(): void
    {
        $this->assertInvalid(
            [
                'upperLimitTypeId' => 2,
                'maxValue' => '',
                'aveMonthlyIncome' => null,
            ],
            'maxValue',
            'required'
        );
    }

    /**
     * USL-005: 異常: maxValue 0
     */
    #[Test]
    public function USL_005_maxValueが0の場合はminエラーになる(): void
    {
        $this->assertInvalid(
            [
                'upperLimitTypeId' => 2,
                'maxValue' => 0,
                'aveMonthlyIncome' => null,
            ],
            'maxValue',
            'min'
        );
    }

    /**
     * USL-006: 異常: 割合指定時に aveMonthlyIncome なし
     */
    #[Test]
    public function USL_006_割合指定時にaveMonthlyIncomeがない場合はrequiredエラーになる(): void
    {
        $this->assertInvalid(
            [
                'upperLimitTypeId' => 1,
                'maxValue' => 30,
                'aveMonthlyIncome' => null,
            ],
            'aveMonthlyIncome',
            'required'
        );
    }

    /**
     * USL-007: 異常: aveMonthlyIncome 0
     */
    #[Test]
    public function USL_007_aveMonthlyIncomeが0の場合はminエラーになる(): void
    {
        $this->assertInvalid(
            [
                'upperLimitTypeId' => 1,
                'maxValue' => 30,
                'aveMonthlyIncome' => 0,
            ],
            'aveMonthlyIncome',
            'min'
        );
    }

    /**
     * USL-008: 境界値: maxValue 1
     */
    #[Test]
    public function USL_008_maxValueが1の場合は通過する(): void
    {
        $this->assertValid([
            'upperLimitTypeId' => 2,
            'maxValue' => 1,
            'aveMonthlyIncome' => null,
        ]);
    }

}
