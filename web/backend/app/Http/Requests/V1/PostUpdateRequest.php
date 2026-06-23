<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class PostUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aiStatusId' => ['sometimes', 'exists:ai_statuses,id'],
            'content' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'aiStatusId.exists' => '指定されたAIステータスが存在しません',
            'content.max' => '投稿内容は3000文字以内で入力してください',
        ];
    }
}
