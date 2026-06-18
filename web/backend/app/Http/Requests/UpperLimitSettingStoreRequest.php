<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpperLimitSettingStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
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
            return [
                'user_id' => ['required', 'exists:users,id'],
                'upper_limit_type_id' => ['required', 'exists:upper_limit_types,id'],
                'max_value' => ['required', 'integer', 'min:1'],
                'ave_monthly_income' => ['required', 'integer', 'min:1'],
            ];
        ];
    }
}
