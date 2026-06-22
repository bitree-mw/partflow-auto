<?php

namespace App\Http\Requests\Site;

use App\Http\Requests\ApiRequest;
use App\Models\Site;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends ApiRequest
{
    public function rules(): array
    {
        $site = $this->route('site');
        $siteId = $site instanceof Site ? $site->id : $site;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('sites', 'code')->ignore($siteId),
            ],

            'type' => [
                'sometimes',
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
