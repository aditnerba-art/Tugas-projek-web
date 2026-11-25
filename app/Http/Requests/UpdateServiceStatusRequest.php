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
        $legacyPartFieldRule = function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value !== null && ! empty($this->input('sparepart_items'))) {
                $fail('Gunakan daftar sparepart saja atau format lama, jangan keduanya.');
            }
        };

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
            'sparepart_id' => ['nullable', $legacyPartFieldRule, 'integer', 'exists:spareparts,id'],
            'jumlah_sparepart' => ['nullable', $legacyPartFieldRule, 'required_with:sparepart_id', 'integer', 'min:1'],
        ];
    }
}
