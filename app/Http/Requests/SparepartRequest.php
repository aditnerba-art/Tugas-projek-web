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

        return [
            'kode_barang' => [
                'required', 'string', 'max:50',
                Rule::unique('spareparts', 'kode_barang')->ignore($sparepart?->id),
            ],
            'nama_barang' => ['required', 'string', 'max:150'],
            'stok' => ['nullable', 'integer', 'min:0'],
            'harga_beli' => ['nullable', 'numeric', 'min:0'],
            'harga_jual' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
