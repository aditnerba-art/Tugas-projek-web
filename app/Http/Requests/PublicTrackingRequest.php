<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_akses' => ['nullable', 'string', 'max:32'],
        ];
    }
}
