<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpperLimitSettingUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'upperLimitTypeId' => ['sometimes', 'exists:upper_limit_types,id'],
            'maxValue' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'aveMonthlyIncome' => ['sometimes','nullable', 'integer', 'min:1'],
        ];
    }
}
