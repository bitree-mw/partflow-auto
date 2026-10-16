<?php

namespace App\Http\Requests\Web;

use App\Services\CatalogProductImportService;
use Illuminate\Foundation\Http\FormRequest;

class RemoveImportDuplicatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware checks catalogue and purchase permissions; only the user's own held preview is changed.
        return true;
    }

    public function rules(): array
    {
        return [
            // First row numbers of the duplicate groups to clean up; omitted means every group.
            'rows' => ['nullable', 'array', 'max:'.CatalogProductImportService::MAX_ROWS],
            'rows.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return array<int, int>|null
     */
    public function firstRows(): ?array
    {
        $rows = $this->validated('rows');

        return $rows ? array_map('intval', $rows) : null;
    }
}
