<?php

namespace App\Http\Requests\Web;

use App\Services\CatalogProductImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewCatalogProductImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware checks catalogue and purchase permissions; the service checks site access.
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,xlsx,txt', 'max:5120'],
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')->where('is_active', true)],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'payment_account_id' => ['required', 'integer', Rule::exists('payment_accounts', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choose a CSV or Excel (.xlsx) file to upload.',
            'file.extensions' => 'Upload a .csv or .xlsx file. Older .xls files must be saved as .xlsx first.',
            'file.max' => 'The file may not be larger than 5 MB ('.number_format(CatalogProductImportService::MAX_ROWS).' rows at most).',
            'site_id.required' => 'Choose the site this stock is for.',
            'payment_account_id.required' => 'Choose the account the purchase was paid from.',
        ];
    }

    /**
     * @return array{site_id: int, contact_id: ?int, payment_account_id: int, transaction_reference: ?string}
     */
    public function importContext(): array
    {
        return [
            'site_id' => (int) $this->validated('site_id'),
            'contact_id' => filled($this->validated('contact_id')) ? (int) $this->validated('contact_id') : null,
            'payment_account_id' => (int) $this->validated('payment_account_id'),
            'transaction_reference' => $this->validated('transaction_reference'),
        ];
    }
}
