<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'nama' => ['sometimes', 'string', 'min:2', 'max:100'],
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'username' => ['sometimes', 'string', 'regex:/^[a-zA-Z0-9_.-]{3,32}$/', Rule::unique('users', 'username')->ignore($staff?->id)],
            'newUsername' => ['sometimes', 'string', 'regex:/^[a-zA-Z0-9_.-]{3,32}$/', Rule::unique('users', 'username')->ignore($staff?->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:1024'],
            'newPassword' => ['sometimes', 'nullable', 'string', 'min:8', 'max:1024'],
        ];
    }
}
