<?php

namespace App\Http\Requests\SuperAdmin;

use App\Services\SuperAdminAuthService;
use Illuminate\Validation\Validator;

class RunDataCleanupRequest extends PreviewDataCleanupRequest
{
    public const CONFIRMATION_PHRASE = 'DELETE';

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'string', 'in:'.self::CONFIRMATION_PHRASE],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'confirmation.in' => 'Type '.self::CONFIRMATION_PHRASE.' in capital letters to confirm.',
        ];
    }

    public function after(SuperAdminAuthService $superAdminAuth): array
    {
        return [
            function (Validator $validator) use ($superAdminAuth): void {
                if (! $validator->errors()->has('password') && ! $superAdminAuth->passwordMatches((string) $this->input('password'))) {
                    $validator->errors()->add('password', 'The super admin password is incorrect.');
                }
            },
        ];
    }
}
