<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostStoreRequest extends FormRequest
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
            'user_id' => ['required', 'exists:users,id'],
            'kakeibo_record_id' => ['required', 'exists:kakeibo_records,id'],
            'ai_status_id' => ['nullable', 'exists:ai_statuses,id'],
            'parent_id' => ['nullable', 'exists:posts,id'],
            'is_ai' => ['required', 'boolean'],
            'content' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.max' => '内容は3000文字以内で入力してください。',
        ];
    }    
}
