<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpperLimitSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'upperLimitTypeId' => ['sometimes', 'exists:upper_limit_types,id'],
            'maxValue' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'aveMonthlyIncome' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'upperLimitTypeId.exists' => '指定された上限区分が存在しません',
            'maxValue.integer' => '上限値は1以上の整数で入力してください',
            'maxValue.min' => '上限値は1以上の整数で入力してください',
            'aveMonthlyIncome.integer' => '平均月収は1以上の整数で入力してください',
            'aveMonthlyIncome.min' => '平均月収は1以上の整数で入力してください',
        ];
    }
}
