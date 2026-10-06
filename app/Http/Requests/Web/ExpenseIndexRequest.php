<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'expense_category_id' => ['nullable', 'integer'],
            'payment_account_id' => ['nullable', 'integer'],
            'site_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Validated filters, defaulting to the current month.
     */
    public function filters(): array
    {
        return array_filter(
            [
                'date_from' => today()->startOfMonth()->toDateString(),
                'date_to' => today()->toDateString(),
                ...$this->validated(),
            ],
            fn ($value) => $value !== null && $value !== ''
        );
    }
}
