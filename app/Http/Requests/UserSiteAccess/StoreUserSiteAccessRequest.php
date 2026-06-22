<?php

namespace App\Http\Requests\UserSiteAccess;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class StoreUserSiteAccessRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('user_site_access', 'user_id')
                    ->where('site_id', $this->input('site_id')),
            ],

            'site_id' => [
                'required',
                'integer',
                'exists:sites,id',
            ],

            'access_level' => [
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
