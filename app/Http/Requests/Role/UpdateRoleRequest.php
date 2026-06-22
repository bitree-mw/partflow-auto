<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\ApiRequest;
use App\Models\Role;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends ApiRequest
{
    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = $role instanceof Role ? $role->id : $role;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore($roleId),
            ],

            'permissions' => [
                'nullable',
                'array',
            ],

            'permissions.*' => [
                'string',
                'max:100',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
