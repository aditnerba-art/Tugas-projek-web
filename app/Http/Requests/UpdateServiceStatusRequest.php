<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'status_baru' => ['required', Rule::in(['masuk', 'diperiksa', 'menunggu_persetujuan', 'menunggu_sparepart', 'dikerjakan', 'selesai', 'batal'])],
            'catatan_teknis' => ['nullable', 'string', 'max:10000'],
            'biaya_jasa' => ['prohibited'],
            'total_biaya' => ['prohibited'],
            'service_rate_id' => ['nullable', 'integer', 'exists:service_rates,id'],
            'estimated_completion_at' => ['prohibited'],
            'warranty_days' => ['prohibited'],
            'warranty_preset_id' => ['nullable', 'integer', 'exists:warranty_presets,id'],
            'sparepart_items' => ['sometimes', 'array', 'max:20'],
            'sparepart_items.*.sparepart_id' => ['required', 'integer', 'distinct', 'exists:spareparts,id'],
            'sparepart_items.*.jumlah' => ['required', 'integer', 'min:1'],
            'sparepart_id' => ['nullable', 'prohibited_with:sparepart_items', 'integer', 'exists:spareparts,id'],
            'jumlah_sparepart' => ['nullable', 'prohibited_with:sparepart_items', 'required_with:sparepart_id', 'integer', 'min:1'],
        ];
    }
}
