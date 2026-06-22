<?php

namespace App\Http\Requests\UserSiteAccess;

use App\Http\Requests\ApiRequest;

class UpdateUserSiteAccessRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'access_level' => [
                'sometimes',
                'required',
                'string',
                'in:view_only,sales,stock,manager,admin',
            ],

            'can_view_stock' => ['nullable', 'boolean'],
            'can_make_sales' => ['nullable', 'boolean'],
            'can_receive_stock' => ['nullable', 'boolean'],
            'can_transfer_stock' => ['nullable', 'boolean'],
            'can_adjust_stock' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
