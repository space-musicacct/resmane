<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'upperLimitTypeId' => [
                'required',
                'integer',
                Rule::exists('upper_limit_types', 'id')->whereNull('deleted_at'),
            ],
            'maxValue' => ['required', 'integer', 'min:1'],
            'aveMonthlyIncome' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'upperLimitTypeId.required' => '上限区分は必須です',
            'upperLimitTypeId.integer' => '上限区分は整数で入力してください',
            'upperLimitTypeId.exists' => '指定された上限区分が存在しません',
            'maxValue.required' => '上限値は必須です',
            'maxValue.integer' => '上限値は1以上の整数で入力してください',
            'maxValue.min' => '上限値は1以上の整数で入力してください',
            'aveMonthlyIncome.integer' => '平均月収は1以上の整数で入力してください',
            'aveMonthlyIncome.min' => '平均月収は1以上の整数で入力してください',
        ];
    }
}
