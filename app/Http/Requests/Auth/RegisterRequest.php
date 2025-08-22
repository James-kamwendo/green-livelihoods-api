<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required_without:phone_number',
                'nullable',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required_with:email',
                'nullable',
                'confirmed',
                Rules\Password::defaults(),
            ],
            'phone_number' => [
                'required_without:email',
                'nullable',
                'string',
                'unique:users,phone_number',
            ],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'age' => ['nullable', 'integer', 'min:18'],
            'location' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'in:artisan,buyer,marketer'],
        ];
    }
}
