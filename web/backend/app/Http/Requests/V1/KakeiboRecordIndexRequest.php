<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class KakeiboRecordIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'amountTypeId' => ['nullable', 'integer', 'exists:amount_types,id'],
            'categoryId' => ['nullable', 'integer', 'exists:kakeibo_default_categories,id'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.date' => '取得開始日は日付形式（YYYY-MM-DD）で入力してください',
            'to.date' => '取得終了日は日付形式（YYYY-MM-DD）で入力してください',
            'amountTypeId.exists' => '指定された収支区分が存在しません',
            'categoryId.exists' => '指定されたカテゴリが存在しません',
            'perPage.min' => '1ページあたりの件数は1以上で入力してください',
            'perPage.max' => '1ページあたりの件数は100以下で入力してください',
        ];
    }
}
