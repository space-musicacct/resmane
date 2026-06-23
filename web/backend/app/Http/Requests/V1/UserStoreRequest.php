<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loginId' => ['required', 'string', 'max:15', 'unique:users,login_id'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
            'passwordConfirmation' => ['required', 'same:password'],
        ];
    }

    public function messages(): array
    {
        return [
            'loginId.required' => 'ログインIDは必須です',
            'loginId.max' => 'ログインIDは15文字以内で入力してください',
            'loginId.unique' => 'このログインIDは既に使用されています',
            'email.required' => 'メールアドレスは必須です',
            'email.email' => 'メールアドレスの形式が正しくありません',
            'email.max' => 'メールアドレスは255文字以内で入力してください',
            'email.unique' => 'このメールアドレスは既に使用されています',
            'name.required' => '名前は必須です',
            'name.max' => '名前は50文字以内で入力してください',
            'password.required' => 'パスワードは必須です',
            'password.min' => 'パスワードは8文字以上で入力してください',
            'passwordConfirmation.required' => 'パスワード（確認用）は必須です',
            'passwordConfirmation.same' => 'パスワード（確認用）が一致しません',
        ];
    }
}
