<?php

namespace App\Http\Requests\ExpenseCategory;

use App\Http\Requests\ApiRequest;

class StoreExpenseCategoryRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name'],
            'code' => ['nullable', 'string', 'max:100', 'unique:expense_categories,code'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
