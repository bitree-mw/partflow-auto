<?php

namespace App\Http\Requests\Contact;

use App\Http\Requests\ApiRequest;

class StoreContactRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'contact_type' => [
                'required',
                'string',
                'in:customer,supplier,both',
            ],

            'code' => [
                'nullable',
                'string',
                'max:50',
                'unique:contacts,code',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'tax_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'credit_limit' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'notes' => [
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
