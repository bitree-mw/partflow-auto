<?php

namespace App\Http\Requests;

use App\Services\SiteAccessService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function canAccessSiteInput(string $field, ?string $operation = null): bool
    {
        $user = $this->user();
        $siteId = $this->input($field);

        if (! $user) {
            return false;
        }

        if (! is_numeric($siteId)) {
            return true;
        }

        return in_array(
            (int) $siteId,
            app(SiteAccessService::class)->allowedSiteIds($user, $operation),
            true
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
