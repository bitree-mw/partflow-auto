<?php

namespace App\Http\Requests\Site;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
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
        $site = $this->route('site');
        $siteId = $site instanceof Site ? $site->id : $site;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('sites', 'name')->ignore($siteId)->whereNull('deleted_at')],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('sites', 'code')->ignore($siteId)],
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
