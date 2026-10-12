<?php

namespace App\Http\Requests\Web;

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
        ];
    }
}
