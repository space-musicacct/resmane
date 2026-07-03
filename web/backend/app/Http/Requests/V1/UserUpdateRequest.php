<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loginId' => ['sometimes', 'string', Rule::unique('users', 'login_id')->ignore(auth()->id())],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore(auth()->id())],
            'name' => ['sometimes', 'string', 'max:50'],
            'currentPassword' => ['required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:8'],
            'passwordConfirmation' => ['required_with:password', 'same:password'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => '名前は50文字以内で入力してください',
            'email.email' => 'メールアドレスの形式が正しくありません',
            'email.max' => 'メールアドレスは255文字以内で入力してください',
            'currentPassword.required_with' => 'パスワード変更時は現在のパスワードが必須です',
            'password.min' => 'パスワードは8文字以上で入力してください',
            'passwordConfirmation.required_with' => 'パスワード変更時は確認用パスワードが必須です',
            'passwordConfirmation.same' => 'パスワード（確認用）が一致しません',
        ];
    }
}
