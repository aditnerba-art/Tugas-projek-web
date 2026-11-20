<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerLoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['token' => $this->input('kode_akses', $this->input('token'))]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'regex:/^\d{5}$/'],
            'customer_name' => ['required', 'string', 'max:100'],
        ];
    }
}
