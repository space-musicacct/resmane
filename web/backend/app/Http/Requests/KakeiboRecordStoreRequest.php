<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KakeiboRecordStoreRequest extends FormRequest
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
            'user_id' => ['required', 'exists:users,id'],
            'purchase_date' => ['required', 'date'],
            'amount_type_id' => ['required', 'exists:amount_types,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'details' => ['nullable', 'string', 'max:250'],
            'kakeibo_default_category_id' => ['required', 'exists:kakeibo_default_categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_date.required' => '購入日は必須です。',
            'amount.required' => '金額は必須です。',
        ];
    }
}
