<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'teknisi_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'teknisi')],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ];
    }
}
