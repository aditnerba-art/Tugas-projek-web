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
            'warranty_days' => $this->user()?->role === 'teknisi'
                ? ['prohibited']
                : ['sometimes', 'integer', 'min:0', 'max:3650'],
            'sparepart_id' => ['nullable', 'integer', 'exists:spareparts,id'],
            'jumlah_sparepart' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
