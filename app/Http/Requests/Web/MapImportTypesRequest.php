<?php

namespace App\Http\Requests\Web;

use App\Services\CatalogProductImportService;
use Illuminate\Foundation\Http\FormRequest;

class MapImportTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware checks catalogue and purchase permissions; only the user's own held preview is changed.
        return true;
    }

    public function rules(): array
    {
        return [
            // First row number of each new type => existing part type name (blank keeps it as a new type).
            'types' => ['nullable', 'array', 'max:'.CatalogProductImportService::MAX_ROWS],
            'types.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function choices(): array
    {
        return collect($this->validated('types') ?? [])
            ->mapWithKeys(fn (?string $name, int|string $row): array => [(int) $row => (string) $name])
            ->all();
    }
}
