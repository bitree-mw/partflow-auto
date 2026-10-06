<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupportContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'support_phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\s-]+$/'],
            'support_whatsapp' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\s-]+$/'],
            'support_email' => ['nullable', 'email:rfc', 'max:255'],
            'support_hours' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'support_phone.regex' => 'The support phone may only contain digits, spaces, +, -, and brackets.',
            'support_whatsapp.regex' => 'The WhatsApp number may only contain digits, spaces, +, -, and brackets.',
        ];
    }
}
