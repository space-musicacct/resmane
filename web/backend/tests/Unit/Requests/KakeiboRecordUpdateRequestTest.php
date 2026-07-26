<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\V1\KakeiboRecordUpdateRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithValidation;

/**
 * 単体テスト仕様書 1.4 KakeiboRecordUpdateRequest 対応テスト
 *
 * KakeiboRecordStoreRequest と同一のルールのため、同等のテストケースを実施する。
 * DB依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 *
 * NOTE: amountTypeId / kakeiboDefaultCategoryId には DB 上の存在確認・整合性検証
 * （exists ルール等）が実装されている想定だが、DB に接続しない本テストでは
 * その部分は検証できない（値が存在すれば通過してしまう）。
 * exists 制約の検証は結合テスト（tests/Feature/）側でカバーする。
 */
class KakeiboRecordUpdateRequestTest extends TestCase
{
    use InteractsWithValidation;

    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return new KakeiboRecordUpdateRequest()->rules();
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
     * UKU-001: 正常: 全フィールド有効
     */
    #[Test]
    public function test_uk_u_001_all_fields_valid_passes_validation(): void
    {
        $this->assertValid(
            $this->validData()
        );
    }

    /**
     * UKU-002: 正常: purchaseDate省略
     */
    #[Test]
    public function test_uk_u_002_purchase_date_null_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'purchaseDate' => null,
            ])
        );
    }

    /**
     * UKU-003: 異常: amountTypeId未入力
     */
    #[Test]
    public function test_uk_u_003_amount_type_id_empty_fails_required(): void
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
     * UKU-004: 異常: amount未入力
     */
    #[Test]
    public function test_uk_u_004_amount_empty_fails_required(): void
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
     * UKU-005: 異常: amount 0
     */
    #[Test]
    public function test_uk_u_005_amount_zero_fails_min(): void
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
     * UKU-006: 異常: amount 負数
     */
    #[Test]
    public function test_uk_u_006_amount_negative_fails_min(): void
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
     * UKU-007: 異常: amount 小数
     */
    #[Test]
    public function test_uk_u_007_amount_decimal_fails_integer(): void
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
     * UKU-008: 異常: details未入力
     */
    #[Test]
    public function test_uk_u_008_details_empty_fails_required(): void
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
     * UKU-009: 異常: details 251文字
     */
    #[Test]
    public function test_uk_u_009_details_251_chars_fails_max(): void
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
     * UKU-010: 異常: categoryId未入力
     */
    #[Test]
    public function test_uk_u_010_kakeibo_default_category_id_empty_fails_required(): void
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
     * UKU-011: 異常: purchaseDate形式不正
     */
    #[Test]
    public function test_uk_u_011_purchase_date_invalid_format_fails_date(): void
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
     * UKU-012: 境界値: details 250文字
     */
    #[Test]
    public function test_uk_u_012_details_250_chars_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'details' => str_repeat('あ', 250),
            ])
        );
    }

    /**
     * UKU-013: 境界値: details 1文字
     */
    #[Test]
    public function test_uk_u_013_details_1_char_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'details' => 'あ',
            ])
        );
    }

    /**
     * UKU-014: 境界値: amount 1
     */
    #[Test]
    public function test_uk_u_014_amount_1_passes(): void
    {
        $this->assertValid(
            $this->validData([
                'amount' => 1,
            ])
        );
    }
}
