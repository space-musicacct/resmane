<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KakeiboRecordUpdateRequest extends FormRequest
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
            'purchase_date' => ['sometimes', 'date'],
            'amount_type_id' => ['sometimes', 'exists:amount_types,id'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'details' => ['nullable', 'string', 'max:250'],
            'kakeibo_default_category_id' => ['sometimes', 'exists:kakeibo_default_categories,id'],
        ];
    }
}
