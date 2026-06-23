<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpperLimitSettingStoreRequest extends FormRequest
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
            'userId' => ['required', 'exists:users,id'],
            'upperLimitTypeId' => ['required', 'exists:upper_limit_types,id'],
            'maxValue' => ['required', 'integer', 'min:1'],
            'aveMonthlyIncome' => ['required', 'integer', 'min:1'],
        ];
    }
}
