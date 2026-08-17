<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'revenue_period' => ['nullable', 'integer', Rule::in([7, 14, 30])],
        ];
    }
}
