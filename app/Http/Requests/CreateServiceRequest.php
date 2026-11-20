<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'nama' => ['required', 'string', 'min:1', 'max:100'],
            'no_wa' => ['required', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:10000'],
            'jenis_unit' => ['nullable', 'string', 'max:100'],
            'merek_tipe' => ['required', 'string', 'max:150'],
            'no_seri' => ['nullable', 'string', 'max:150'],
            'keluhan' => ['required', 'string', 'max:10000'],
            'kelengkapan' => ['nullable', 'string', 'max:10000'],
            'teknisi_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'teknisi')],
        ];
    }
}
