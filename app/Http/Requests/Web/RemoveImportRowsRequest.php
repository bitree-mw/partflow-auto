<?php

namespace App\Http\Requests\Web;

use App\Services\CatalogProductImportService;
use Illuminate\Foundation\Http\FormRequest;

class RemoveImportRowsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware checks catalogue and purchase permissions; only the user's own held preview is changed.
        return true;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:'.CatalogProductImportService::MAX_ROWS],
            'rows.*' => ['required', 'integer', 'min:1', 'distinct'],
            // Set when the request came from the duplicates dialog, so it reopens while duplicates remain.
            'reopen' => ['nullable', 'in:duplicates'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function rowNumbers(): array
    {
        return array_map('intval', $this->validated('rows'));
    }

    public function reopensDuplicates(): bool
    {
        return $this->validated('reopen') === 'duplicates';
    }
}
