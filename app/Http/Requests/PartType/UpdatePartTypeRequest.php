<?php

namespace App\Http\Requests\PartType;

use App\Http\Requests\ApiRequest;
use App\Models\PartType;
use Illuminate\Validation\Rule;

class UpdatePartTypeRequest extends ApiRequest
{
    public function rules(): array
    {
        $partType = $this->route('part_type');
        $partTypeId = $partType instanceof PartType ? $partType->id : $partType;

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
                'max:20',
                'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('product_types', 'code')->ignore($partTypeId),
            ],

            'description' => [
                'nullable',
                'string',
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
            'code.regex' => 'The product type code may only contain letters and numbers.',
        ];
    }
}
