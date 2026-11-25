<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SparepartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sparepart = $this->route('sparepart');
        $priceRules = $this->user()?->role === 'owner'
            ? ['nullable', 'integer', 'min:0']
            : ['prohibited'];

        return [
            'kode_barang' => [
                'required', 'string', 'max:50',
                Rule::unique('spareparts', 'kode_barang')->ignore($sparepart?->id),
            ],
            'nama_barang' => ['required', 'string', 'max:150'],
            'stok' => ['nullable', 'integer', 'min:0'],
            'stok_minimum' => ['nullable', 'integer', 'min:0'],
            'harga_beli' => $priceRules,
            'harga_jual' => $priceRules,
        ];
    }
}
