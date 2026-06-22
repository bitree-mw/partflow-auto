<?php

namespace App\Http\Requests\Site;

use App\Http\Requests\ApiRequest;

class StoreSiteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'unique:sites,code',
            ],

            'type' => [
                'required',
                'string',
                'in:shop,warehouse,branch',
            ],

            'location' => [
                'nullable',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
