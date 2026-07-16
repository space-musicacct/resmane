<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\KakeiboRecordIndexRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 単体テスト仕様書 1.5 KakeiboRecordIndexRequest 対応テスト
 *
 * DB依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class KakeiboRecordIndexRequestTest extends TestCase
{
    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return (new KakeiboRecordIndexRequest())->rules();
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
     * UKI-001: 正常: パラメータなし
     */
    #[Test]
    public function UKI_001_全パラメータ省略の場合はバリデーションを通過する(): void
    {
        $this->assertValid([]);
    }

    /**
     * UKI-002: 正常: from, to 指定
     */
    #[Test]
    public function UKI_002_fromとtoを指定した場合はバリデーションを通過する(): void
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
    public function UKI_003_perPageを指定した場合はバリデーションを通過する(): void
    {
        $this->assertValid([
            'perPage' => 50,
        ]);
    }

    /**
     * UKI-004: 異常: from 形式不正
     */
    #[Test]
    public function UKI_004_from形式不正の場合はdateエラーになる(): void
    {
        $this->assertInvalid(
            ['from' => '2026/06/01'],
            'from',
            'date'
        );
    }

    /**
     * UKI-005: 異常: to 形式不正
     */
    #[Test]
    public function UKI_005_to形式不正の場合はdateエラーになる(): void
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
    public function UKI_006_perPageが0の場合はminエラーになる(): void
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
    public function UKI_007_perPageが101の場合はmaxエラーになる(): void
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
    public function UKI_008_perPageが1の場合は通過する(): void
    {
        $this->assertValid([
            'perPage' => 1,
        ]);
    }

    /**
     * UKI-009: 境界値: perPage 100
     */
    #[Test]
    public function UKI_009_perPageが100の場合は通過する(): void
    {
        $this->assertValid([
            'perPage' => 100,
        ]);
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
