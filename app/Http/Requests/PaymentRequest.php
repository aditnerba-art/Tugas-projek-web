<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'status_pembayaran' => ['nullable', Rule::in(['belum_bayar', 'dp', 'lunas'])],
            'metode_pembayaran' => ['nullable', 'string', 'max:30'],
            'nominal_dibayar' => ['nullable', 'numeric', 'min:0'],
            'dp_dibayar' => ['nullable', 'numeric', 'min:0'],
            'kembalian' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:10000'],
            'ambil_unit' => ['nullable', 'boolean'],
        ];
    }
}
