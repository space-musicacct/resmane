<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\V1\KakeiboRecordIndexRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
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
    public function test_UKI_001_no_params_passes(): void
    {
        $this->assertValid([]);
    }

    /**
     * UKI-002: 正常: from, to 指定
     */
    #[Test]
    public function test_UKI_002_from_and_to_passes(): void
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
    public function test_UKI_003_perPage_passes(): void
    {
        $this->assertValid([
            'perPage' => 50,
        ]);
    }

    /**
     * UKI-004: 異常: from 形式不正
     */
    #[Test]
    public function test_UKI_004_from_invalid_format_fails_date(): void
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
    public function test_UKI_005_to_invalid_format_fails_date(): void
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
    public function test_UKI_006_perPage_zero_fails_min(): void
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
    public function test_UKI_007_perPage_101_fails_max(): void
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
    public function test_UKI_008_perPage_1_passes(): void
    {
        $this->assertValid([
            'perPage' => 1,
        ]);
    }

    /**
     * UKI-009: 境界値: perPage 100
     */
    #[Test]
    public function test_UKI_009_perPage_100_passes(): void
    {
        $this->assertValid([
            'perPage' => 100,
        ]);
    }

}
