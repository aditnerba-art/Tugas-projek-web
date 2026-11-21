<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadServicePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && in_array($this->user()->role, ['admin', 'teknisi'], true);
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'kind' => ['required', Rule::in(['intake', 'completion'])],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => [
                'required', 'image', 'mimes:jpg,jpeg,png', 'max:5120',
                'dimensions:min_width=100,min_height=100,max_width=3000,max_height=3000',
            ],
        ];
    }
}
