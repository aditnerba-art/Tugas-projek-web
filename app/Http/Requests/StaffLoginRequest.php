<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:1024'],
            'role' => ['required', Rule::in(['owner', 'admin', 'teknisi'])],
        ];
    }
}
