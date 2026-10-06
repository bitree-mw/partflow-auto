<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => filled($this->input('username')) ? strtolower(trim((string) $this->input('username'))) : null,
            'site' => $this->input('site') === 'All sites' ? null : $this->input('site'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'site' => ['nullable', 'string', 'max:255', Rule::exists('sites', 'name')->where('is_active', true)->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }
}
