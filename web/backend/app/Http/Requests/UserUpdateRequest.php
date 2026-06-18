<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
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
            return [
                'login_id' => ['sometimes', 'string', 'max:50'],
                'email' => ['sometimes', 'email', 'max:255'],
                'name' => ['sometimes', 'string', 'max:100'],
                'password' => ['nullable', 'string', 'min:8'],
        ]   ;
        ];
    }
}
