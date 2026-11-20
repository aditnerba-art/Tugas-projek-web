<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_baru' => ['required', Rule::in(['setuju', 'batal', 'dikerjakan', 'batalkan'])],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'no_nota' => ['nullable', 'string', 'max:32'],
        ];
    }
}
