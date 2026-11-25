<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier' => ['required', 'string', 'max:100'],
            'tanggal_pembelian' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.jumlah' => ['required', 'integer', 'min:1'],
            'items.*.harga_beli_satuan' => ['required', 'integer', 'min:0'],
            'items.*.sparepart_id' => ['nullable', 'integer', 'exists:spareparts,id'],
            'items.*.is_new' => ['nullable', 'boolean'],
            'items.*.kode_barang' => ['required_if:items.*.is_new,true', 'nullable', 'string', 'max:50'],
            'items.*.nama_barang' => ['required_if:items.*.is_new,true', 'nullable', 'string', 'max:150'],
            'items.*.harga_jual' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
