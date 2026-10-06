<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class SendTestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'test_email_recipient' => ['required', 'email:rfc', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'test_email_recipient' => 'test email address',
        ];
    }
}
