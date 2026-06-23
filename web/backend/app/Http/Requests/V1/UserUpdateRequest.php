<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
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
            'loginId' => ['sometimes', 'string', 'max:15'],
            'email' => ['sometimes', 'email', 'max:255'],
            'name' => ['sometimes', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
