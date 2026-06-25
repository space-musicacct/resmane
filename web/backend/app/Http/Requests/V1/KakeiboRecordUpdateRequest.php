<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KakeiboRecordUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchaseDate' => ['sometimes', 'date'],
            'amountTypeId' => ['sometimes', 'exists:amount_types,id'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'details' => ['nullable', 'string', 'max:250'],
            'kakeiboDefaultCategoryId' => [
                'sometimes',
                Rule::exists('kakeibo_default_categories', 'id')
                    ->where('amount_type_id', $this->input('amountTypeId')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'purchaseDate.date' => '購入日は日付形式（YYYY-MM-DD）で入力してください',
            'amountTypeId.exists' => '指定された収支区分が存在しません',
            'amount.integer' => '金額は1以上の整数で入力してください',
            'amount.min' => '金額は1以上の整数で入力してください',
            'details.max' => '購入詳細は250文字以内で入力してください',
            'kakeiboDefaultCategoryId.exists' => '指定されたカテゴリは選択された収支区分に対応していません',
        ];
    }
}
