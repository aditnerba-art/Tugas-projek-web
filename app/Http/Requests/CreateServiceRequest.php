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
            'no_wa' => ['required', 'string', 'max:20', 'regex:/^(?:\+?62|0062|0|8)[0-9\s().-]{7,16}$/'],
            'alamat' => ['nullable', 'string', 'max:10000'],
            'jenis_unit' => ['nullable', 'string', 'max:100'],
            'merek_tipe' => ['required', 'string', 'max:150'],
            'no_seri' => ['nullable', 'string', 'max:150'],
            'keluhan' => ['required', 'string', 'max:10000'],
            'kelengkapan' => ['nullable', 'string', 'max:10000'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['string', 'max:80'],
            'physical_condition' => ['nullable', 'string', 'max:5000'],
            'estimated_completion_at' => ['nullable', 'date', 'after_or_equal:today'],
            'warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'teknisi_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'teknisi')],
        ];
    }
}
