<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerEstimateApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estimate_change_id' => ['required', 'integer', 'exists:service_estimate_changes,id'],
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
        ];
    }
}
