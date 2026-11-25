<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEstimateChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'teknisi'], true);
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'additional_days' => ['required', 'integer', 'min:1', 'max:180'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
