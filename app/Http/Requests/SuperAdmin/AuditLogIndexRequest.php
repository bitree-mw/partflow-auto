<?php

namespace App\Http\Requests\SuperAdmin;

use App\Services\AuditLogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group' => ['nullable', Rule::in(array_keys(AuditLogService::EVENT_GROUPS))],
            'actor' => ['nullable', Rule::in(['user', 'super_admin', 'guest', 'system'])],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
