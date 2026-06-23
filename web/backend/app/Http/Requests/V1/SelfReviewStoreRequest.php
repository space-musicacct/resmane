<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SelfReviewStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kakeiboRecordId' => ['required', 'exists:kakeibo_records,id'],
            'reviewComment' => ['required', 'string', 'max:250'],
        ];
    }

    public function messages(): array
    {
        return [
            'kakeiboRecordId.required' => '家計簿レコードの指定は必須です',
            'kakeiboRecordId.exists' => '指定された家計簿レコードが存在しません',
            'reviewComment.required' => '自己レビューは必須です',
            'reviewComment.max' => '自己レビューは250文字以内で入力してください',
        ];
    }
}
