<?php

namespace App\Http\Requests;

use App\Models\WarrantyPreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarrantyPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    public function rules(): array
    {
        $name = ['required', 'string', 'min:2', 'max:100'];
        $preset = $this->route('warrantyPreset');
        $uniqueName = Rule::unique('warranty_presets', 'name');
        if ($preset instanceof WarrantyPreset) {
            $uniqueName->ignore($preset->id);
        }

        return [
            'name' => [...$name, $uniqueName],
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
