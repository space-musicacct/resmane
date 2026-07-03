<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SelfReviewUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewComment' => ['required', 'string', 'max:250'],
        ];
    }

    public function messages(): array
    {
        return [
            'reviewComment.max' => '自己レビューは250文字以内で入力してください',
        ];
    }
}
