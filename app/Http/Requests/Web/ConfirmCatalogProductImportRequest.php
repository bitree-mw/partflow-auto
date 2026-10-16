<?php

namespace App\Http\Requests\Web;

use App\Services\CatalogProductImportService;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmCatalogProductImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware checks catalogue and purchase permissions; the service checks site access.
        return true;
    }

    public function rules(): array
    {
        return [
            'import_token' => ['required', 'string', 'uuid'],
            // Row number => "new" or the id of an offered similar part; the service checks it was offered.
            'merge' => ['nullable', 'array', 'max:'.CatalogProductImportService::MAX_ROWS],
            'merge.*' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function decisions(): array
    {
        return collect($this->validated('merge') ?? [])
            ->mapWithKeys(fn (?string $choice, int|string $row): array => [(int) $row => (string) $choice])
            ->all();
    }
}
