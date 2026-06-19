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
            'purchaseDate' => ['nullable', 'date'],
            'amountTypeId' => ['required', 'exists:amount_types,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'details' => ['nullable', 'string', 'max:250'],
            'kakeiboDefaultCategoryId' => ['required', 'exists:kakeibo_default_categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchaseDate.required' => '購入日は必須です。',
            'amount.required' => '金額は必須です。',
        ];
    }
}
