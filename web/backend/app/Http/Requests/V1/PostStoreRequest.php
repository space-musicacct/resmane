<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class PostStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:3000'],
            'parentId' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.max' => '投稿内容は3000文字以内で入力してください',
        ];
    }
}
