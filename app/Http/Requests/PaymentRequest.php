<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->is('api/admin/service/pelunasan')) {
            return;
        }

        $defaults = ['status_pembayaran' => 'lunas', 'ambil_unit' => true, 'kembalian' => 0];
        if (! $this->filled('nominal_dibayar') && $this->integer('service_id') > 0) {
            $defaults['nominal_dibayar'] = Service::whereKey($this->integer('service_id'))->value('total_biaya') ?? 0;
        }
        $this->merge($defaults);
    }

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
