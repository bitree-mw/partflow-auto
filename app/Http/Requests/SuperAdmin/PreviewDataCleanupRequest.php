<?php

namespace App\Http\Requests\SuperAdmin;

use App\Services\DataCleanupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewDataCleanupRequest extends FormRequest
{
    // The preview and run endpoints are POST/DELETE only, so errors must return to the form page.
    protected $redirectRoute = 'suadmin.data-cleanup.index';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string', 'distinct', Rule::in(array_keys(DataCleanupService::CATEGORIES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function messages(): array
    {
        return [
            'categories.required' => 'Tick at least one type of data to delete.',
        ];
    }

    /**
     * @return array{categories: array<int, string>, from: string|null, to: string|null}
     */
    public function selection(): array
    {
        return [
            'categories' => array_values($this->validated('categories')),
            'from' => $this->validated('from'),
            'to' => $this->validated('to'),
        ];
    }
}
