<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'min:2', 'max:100'],
            'username' => ['required', 'regex:/^[a-zA-Z0-9_.-]{3,32}$/', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'max:1024'],
            'role' => ['required', Rule::in(['admin', 'teknisi'])],
        ];
    }
}
