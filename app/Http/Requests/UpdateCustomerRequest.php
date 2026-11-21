<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'no_wa' => ['required', 'string', 'max:20', 'regex:/^(?:\+?62|0062|0|8)[0-9\s().-]{7,16}$/'],
            'alamat' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
