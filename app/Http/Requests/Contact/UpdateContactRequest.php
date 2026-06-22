<?php

namespace App\Http\Requests\Contact;

use App\Http\Requests\ApiRequest;
use App\Models\Contact;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends ApiRequest
{
    public function rules(): array
    {
        $contact = $this->route('contact');
        $contactId = $contact instanceof Contact ? $contact->id : $contact;

        return [
            'contact_type' => [
                'sometimes',
                'required',
                'string',
                'in:customer,supplier,both',
            ],

            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('contacts', 'code')->ignore($contactId),
            ],

            'name' => [
                'sometimes',
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
