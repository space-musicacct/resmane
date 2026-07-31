<?php

namespace Tests\Unit\Concerns;

use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * FormRequest の rules() を Validator に直接適用して検証するテストで使う共通アサーション。
 *
 * 利用側は validator(array $data): Validator を実装すること。
 */
trait InteractsWithValidation
{
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
     *
     * Validator::failed() のキーは 'RequiredWith' のような StudlyCase で
     * 返るため、'required_with' のような snake_case に変換して比較できるようにする。
     */
    private function firstRule(
        Validator $validator,
        string $field
    ): ?string {
        $failed = $validator->failed();

        if (! isset($failed[$field])) {
            return null;
        }

        $ruleName = array_key_first($failed[$field]);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $ruleName));
    }

    /**
     * rules() から exists 制約（'exists:...' 文字列ルール、および
     * Rule::exists(...) が返す Illuminate\Validation\Rules\Exists インスタンス）を除外する。
     *
     * exists は DB 参照が必要なため、DB 接続のない本テストでは常に fail してしまう。
     * 単体テスト仕様書の方針（exists の検証は結合テストで行う）に従い、
     * 単体テストでは exists 以外のルールのみを対象に検証する。
     */
    private function withoutExistsRules(array $rules): array
    {
        foreach ($rules as $field => $fieldRules) {
            $rules[$field] = array_values(array_filter(
                (array) $fieldRules,
                function ($rule) {
                    if (is_string($rule)) {
                        return ! str_starts_with($rule, 'exists:');
                    }

                    return ! ($rule instanceof Exists);
                }
            ));
        }

        return $rules;
    }
}
