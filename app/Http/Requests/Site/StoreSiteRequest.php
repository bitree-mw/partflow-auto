<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('sites', 'name')->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', 'unique:sites,code'],
            'type' => ['required', 'string', 'in:shop,warehouse,branch'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The site code may only contain letters, numbers, and hyphens.',
        ];
    }
}
