<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpperLimitSettingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'userId' => ['required', 'exists:users,id'],
            'upperLimitTypeId' => ['required', 'exists:upper_limit_types,id'],
            'maxValue' => ['required', 'integer', 'min:1'],
            'aveMonthlyIncome' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'userId.required' => 'ユーザーの指定は必須です',
            'userId.exists' => '指定されたユーザーが存在しません',
            'upperLimitTypeId.required' => '上限区分は必須です',
            'upperLimitTypeId.exists' => '指定された上限区分が存在しません',
            'maxValue.required' => '上限値は必須です',
            'maxValue.integer' => '上限値は1以上の整数で入力してください',
            'maxValue.min' => '上限値は1以上の整数で入力してください',
            'aveMonthlyIncome.required' => '平均月収は必須です',
            'aveMonthlyIncome.integer' => '平均月収は1以上の整数で入力してください',
            'aveMonthlyIncome.min' => '平均月収は1以上の整数で入力してください',
        ];
    }
}
