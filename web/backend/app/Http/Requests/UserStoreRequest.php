<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
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
            'loginId' => ['required', 'string', 'max:15', 'unique:users,login_id'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
            'passwordConfirmation' =>['required', 'same:password']
        ];
    }

    public function messages(): array
    {
        return [
            'loginId.required' => 'ログインIDは必須です。',
            'loginId.unique' => 'このログインIDは既に使用されています。',
            'email.required' => 'メールアドレスは必須です。',
            'email.email' => 'メールアドレスの形式が正しくありません。',
            'email.unique' => 'このメールアドレスは既に使用されています。',
            'name.required' => '名前は必須です。',
            'password.required' => 'パスワードは必須です。',
            'password.min' => 'パスワードは8文字以上で入力してください。',
        ];
    }
}
