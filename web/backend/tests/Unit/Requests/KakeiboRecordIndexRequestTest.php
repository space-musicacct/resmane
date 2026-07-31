<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\V1\KakeiboRecordIndexRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithValidation;

/**
 * 単体テスト仕様書 1.5 KakeiboRecordIndexRequest 対応テスト
 *
 * DB依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class KakeiboRecordIndexRequestTest extends TestCase
{
    use InteractsWithValidation;

    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return new KakeiboRecordIndexRequest()->rules();
    }

    /**
     * Validator生成共通処理。
     *
     * amountTypeId / categoryId の exists 制約は DB 参照が必要なため、
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
     * UKI-001: 正常: パラメータなし
     */
    #[Test]
    public function test_uk_i_001_no_params_passes(): void
    {
        $this->assertValid([]);
    }

    /**
     * UKI-002: 正常: from, to 指定
     */
    #[Test]
    public function test_uk_i_002_from_and_to_passes(): void
    {
        $this->assertValid([
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ]);
    }

    /**
     * UKI-003: 正常: perPage 指定
     */
    #[Test]
    public function test_uk_i_003_per_page_passes(): void
    {
        $this->assertValid([
            'perPage' => 50,
        ]);
    }

    /**
     * UKI-004: 異常: from 形式不正
     */
    #[Test]
    public function test_uk_i_004_from_invalid_format_fails_date(): void
    {
        $this->assertInvalid(
            ['from' => 'invalid-date'],
            'from',
            'date'
        );
    }

    /**
     * UKI-005: 異常: to 形式不正
     */
    #[Test]
    public function test_uk_i_005_to_invalid_format_fails_date(): void
    {
        $this->assertInvalid(
            ['to' => 'invalid'],
            'to',
            'date'
        );
    }

    /**
     * UKI-006: 異常: perPage 0
     */
    #[Test]
    public function test_uk_i_006_per_page_zero_fails_min(): void
    {
        $this->assertInvalid(
            ['perPage' => 0],
            'perPage',
            'min'
        );
    }

    /**
     * UKI-007: 異常: perPage 101
     */
    #[Test]
    public function test_uk_i_007_per_page_101_fails_max(): void
    {
        $this->assertInvalid(
            ['perPage' => 101],
            'perPage',
            'max'
        );
    }

    /**
     * UKI-008: 境界値: perPage 1
     */
    #[Test]
    public function test_uk_i_008_per_page_1_passes(): void
    {
        $this->assertValid([
            'perPage' => 1,
        ]);
    }

    /**
     * UKI-009: 境界値: perPage 100
     */
    #[Test]
    public function test_uk_i_009_per_page_100_passes(): void
    {
        $this->assertValid([
            'perPage' => 100,
        ]);
    }
}
