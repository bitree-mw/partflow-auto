<?php

namespace App\Http\Requests\TaxProfile;

use App\Http\Requests\ApiRequest;
use App\Models\TaxProfile;
use Illuminate\Validation\Rule;

class UpdateTaxProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        $taxProfile = $this->route('tax_profile');
        $taxProfileId = $taxProfile instanceof TaxProfile ? $taxProfile->id : $taxProfile;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('tax_profiles', 'name')->ignore($taxProfileId),
            ],

            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9\-_]+$/',
                Rule::unique('tax_profiles', 'code')->ignore($taxProfileId),
            ],

            'tax_type' => [
                'sometimes',
                'required',
                'string',
                'in:vat,none',
            ],

            'tax_rate' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'price_mode' => [
                'sometimes',
                'required',
                'string',
                'in:inclusive,exclusive,exempt,none',
            ],

            'is_exempt' => [
                'nullable',
                'boolean',
            ],

            'exemption_reason' => [
                'nullable',
                'string',
            ],

            'is_default' => [
                'nullable',
                'boolean',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The tax profile code may only contain letters, numbers, hyphens, and underscores.',
        ];
    }
}
