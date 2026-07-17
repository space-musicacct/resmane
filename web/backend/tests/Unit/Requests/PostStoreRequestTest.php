<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\V1\PostStoreRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Concerns\InteractsWithValidation;

/**
 * 単体テスト仕様書 1.8 PostStoreRequest 対応テスト
 *
 * DB依存なしで、リクエストの rules() を Validator に直接適用して検証する。
 */
class PostStoreRequestTest extends TestCase
{
    use InteractsWithValidation;

    /**
     * 検証対象のバリデーションルールを取得する。
     */
    private function rules(): array
    {
        return (new PostStoreRequest())->rules();
    }

    /**
     * デフォルトの正常系入力データ。
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'content' => 'アドバイスほしい',
            'parentId' => null,
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
     * UPS-001: 正常: content 有効
     */
    #[Test]
    public function UPS_001_contentが有効な場合はバリデーションを通過する(): void
    {
        $this->assertValid(
            $this->validData()
        );
    }

    /**
     * UPS-002: 正常: content 省略
     */
    #[Test]
    public function UPS_002_contentがnullの場合はバリデーションを通過する(): void
    {
        $this->assertValid(
            $this->validData([
                'content' => null,
            ])
        );
    }

    /**
     * UPS-003: 正常: parentId 省略
     */
    #[Test]
    public function UPS_003_parentIdがnullの場合はバリデーションを通過する(): void
    {
        $this->assertValid(
            $this->validData([
                'parentId' => null,
            ])
        );
    }

    /**
     * UPS-004: 異常: content 3001文字
     */
    #[Test]
    public function UPS_004_contentが3001文字の場合はmaxエラーになる(): void
    {
        $this->assertInvalid(
            $this->validData([
                'content' => str_repeat('あ', 3001),
            ]),
            'content',
            'max'
        );
    }

    /**
     * UPS-005: 境界値: content 3000文字
     */
    #[Test]
    public function UPS_005_contentが3000文字の場合は通過する(): void
    {
        $this->assertValid(
            $this->validData([
                'content' => str_repeat('あ', 3000),
            ])
        );
    }

}
