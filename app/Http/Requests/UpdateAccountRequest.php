<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'max:1024'],
            'newUsername' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_.-]{3,32}$/', Rule::unique('users', 'username')->ignore($this->user()?->id)],
            'newPassword' => ['nullable', 'string', 'min:10', 'max:1024', 'confirmed'],
        ];
    }
}
