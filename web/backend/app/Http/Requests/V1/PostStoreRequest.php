<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $recordId = $this->route('recordId');

        return [
            'content' => ['nullable', 'string', 'max:3000'],
            'parentId' => [
                'nullable',
                'integer',
                Rule::exists('posts', 'id')
                    ->where('kakeibo_record_id', $recordId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'content.max' => '投稿内容は3000文字以内で入力してください',
            'parentId.exists' => 'リプライ先の投稿が見つかりません',
        ];
    }
}
