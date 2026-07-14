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
            'reviewComment' => ['required', 'string', 'max:250'],
            'evaluation' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'reviewComment.required' => '自己レビューは必須です',
            'reviewComment.max' => '自己レビューは250文字以内で入力してください',
            'evaluation.required' => '評価は必須です',
            'evaluation.integer' => '評価は1〜5の整数で入力してください',
            'evaluation.min' => '評価は1以上で入力してください',
            'evaluation.max' => '評価は5以下で入力してください',
        ];
    }
}
