<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $defaults = [
            'type' => $this->input('type', 'payment'),
            'method' => $this->input('method', $this->input('metode_pembayaran', 'tunai')),
            'note' => $this->input('note', $this->input('catatan')),
        ];
        if ($this->is('api/admin/service/pelunasan')) {
            $defaults['ambil_unit'] = true;
            if (! $this->filled('amount') && ! $this->filled('nominal_dibayar') && $this->integer('service_id') > 0) {
                $service = Service::find($this->integer('service_id'));
                $defaults['amount'] = $service?->balanceDue() ?? 0;
            }
        }

        if (is_string($defaults['method'])) {
            $defaults['method'] = match (strtolower(trim($defaults['method']))) {
                'cash', 'tunai' => 'tunai',
                'transfer', 'transfer bank' => 'transfer',
                'qris', 'qr' => 'qris',
                'lainnya', 'other' => 'lainnya',
                default => $defaults['method'],
            };
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
            'type' => ['required', Rule::in(['payment', 'refund'])],
            'amount' => ['required_if:type,refund', 'nullable', 'integer', 'min:0'],
            'nominal_dibayar' => ['nullable', 'integer', 'min:0'],
            'method' => ['required', Rule::in(['tunai', 'transfer', 'qris', 'lainnya'])],
            'note' => ['nullable', 'string', 'max:10000'],
            'metode_pembayaran' => ['nullable', 'string', 'max:30'],
            'catatan' => ['nullable', 'string', 'max:10000'],
            'ambil_unit' => ['nullable', 'boolean'],
        ];
    }
}
