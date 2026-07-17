<?php

namespace Tests\Unit\Concerns;

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
