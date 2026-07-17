<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\V1\KakeiboRecordStoreRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithValidation;

/**
 * 単体テスト仕様書 1.3 KakeiboRecordStoreRequest 対応テスト
 *
 * DB依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 *
 * NOTE: amountTypeId / kakeiboDefaultCategoryId には DB 上の存在確認・整合性検証
 * （exists ルール等）が実装されている想定だが、DB に接続しない本テストでは
 * その部分は検証できない（値が存在すれば通過してしまう）。
 * exists 制約の検証は結合テスト（tests/Feature/）側でカバーする。
 */
class KakeiboRecordStoreRequestTest extends TestCase
{
    use InteractsWithValidation;

    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return (new KakeiboRecordStoreRequest())->rules();
    }

    /**
     * デフォルトの正常系入力データ。
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'purchaseDate' => '2026-07-01',
            'amountTypeId' => 1,
            'amount' => 1000,
            'details' => 'テスト',
            'kakeiboDefaultCategoryId' => 1,
        ], $overrides);
    }

    /**
     * Validator生成共通処理。
     *
     * amountTypeId / kakeiboDefaultCategoryId の exists 制約は DB 参照が必要なため、
     * DB 接続のない本テストでは除外して検証する（exists 自体の検証は結合テストで行う）。
     */
    private function validator(array $data): Validator
    {
        return ValidatorFacade::make(
            $data,
            $this->withoutExistsRules($this->rules())
        );
    }

    /**
     * UKS-001: 正常: 全フィールド有効
     */
    #[Test]
    public function UKS_001_全フィールド有効な場合はバリデーションを通過する(): void
    {
        $this->assertValid(
            $this->validData()
        );
    }

    /**
     * UKS-002: 正常: purchaseDate省略
     */
    #[Test]
    public function UKS_002_purchaseDateがnullの場合はバリデーションを通過する(): void
    {
        $this->assertValid(
            $this->validData([
                'purchaseDate' => null,
            ])
        );
    }

    /**
     * UKS-003: 異常: amountTypeId未入力
     */
    #[Test]
    public function UKS_003_amountTypeId未入力の場合はrequiredエラーになる(): void
    {
        $this->assertInvalid(
            $this->validData([
                'amountTypeId' => '',
            ]),
            'amountTypeId',
            'required'
        );
    }

    /**
     * UKS-004: 異常: amount未入力
     */
    #[Test]
    public function UKS_004_amount未入力の場合はrequiredエラーになる(): void
    {
        $this->assertInvalid(
            $this->validData([
                'amount' => '',
            ]),
            'amount',
            'required'
        );
    }

    /**
     * UKS-005: 異常: amount 0
     */
    #[Test]
    public function UKS_005_amountが0の場合はminエラーになる(): void
    {
        $this->assertInvalid(
            $this->validData([
                'amount' => 0,
            ]),
            'amount',
            'min'
        );
    }

    /**
     * UKS-006: 異常: amount 負数
     */
    #[Test]
    public function UKS_006_amountが負数の場合はminエラーになる(): void
    {
        $this->assertInvalid(
            $this->validData([
                'amount' => -1,
            ]),
            'amount',
            'min'
        );
    }

    /**
     * UKS-007: 異常: amount 小数
     */
    #[Test]
    public function UKS_007_amountが小数の場合はintegerエラーになる(): void
    {
        $this->assertInvalid(
            $this->validData([
                'amount' => 1.5,
            ]),
            'amount',
            'integer'
        );
    }

    /**
     * UKS-008: 異常: details未入力
     */
    #[Test]
    public function UKS_008_details未入力の場合はrequiredエラーになる(): void
    {
        $this->assertInvalid(
            $this->validData([
                'details' => '',
            ]),
            'details',
            'required'
        );
    }

    /**
     * UKS-009: 異常: details 251文字
     */
    #[Test]
    public function UKS_009_detailsが251文字の場合はmaxエラーになる(): void
    {
        $this->assertInvalid(
            $this->validData([
                'details' => str_repeat('あ', 251),
            ]),
            'details',
            'max'
        );
    }

    /**
     * UKS-010: 異常: categoryId未入力
     */
    #[Test]
    public function UKS_010_kakeiboDefaultCategoryId未入力の場合はrequiredエラーになる(): void
    {
        $this->assertInvalid(
            $this->validData([
                'kakeiboDefaultCategoryId' => '',
            ]),
            'kakeiboDefaultCategoryId',
            'required'
        );
    }

    /**
     * UKS-011: 異常: purchaseDate形式不正
     */
    #[Test]
    public function UKS_011_purchaseDate形式不正の場合はdateエラーになる(): void
    {
        $this->assertInvalid(
            $this->validData([
                'purchaseDate' => 'invalid-date',
            ]),
            'purchaseDate',
            'date'
        );
    }

    /**
     * UKS-012: 境界値: details 250文字
     */
    #[Test]
    public function UKS_012_details250文字の場合は通過する(): void
    {
        $this->assertValid(
            $this->validData([
                'details' => str_repeat('あ', 250),
            ])
        );
    }

    /**
     * UKS-013: 境界値: details 1文字
     */
    #[Test]
    public function UKS_013_details1文字の場合は通過する(): void
    {
        $this->assertValid(
            $this->validData([
                'details' => 'あ',
            ])
        );
    }

    /**
     * UKS-014: 境界値: amount 1
     */
    #[Test]
    public function UKS_014_amount1の場合は通過する(): void
    {
        $this->assertValid(
            $this->validData([
                'amount' => 1,
            ])
        );
    }
}
