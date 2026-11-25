<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    public function rules(): array
    {
        $serviceRate = $this->route('serviceRate');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('service_rates', 'name')->ignore($serviceRate?->id),
            ],
            'amount' => ['required', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
