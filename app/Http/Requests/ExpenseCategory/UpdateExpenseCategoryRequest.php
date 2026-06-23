<?php

namespace App\Http\Requests\ExpenseCategory;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('expense_categories', 'name')->ignore($this->route('expense_category')),
            ],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('expense_categories', 'code')->ignore($this->route('expense_category')),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
