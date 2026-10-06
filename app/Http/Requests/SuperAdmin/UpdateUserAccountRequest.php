<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccountRequest extends FormRequest
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
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : $user;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'site' => ['nullable', 'string', 'max:255', Rule::exists('sites', 'name')->where('is_active', true)->whereNull('deleted_at')],
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
