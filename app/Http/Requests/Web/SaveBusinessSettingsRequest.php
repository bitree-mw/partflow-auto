<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class SaveBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::settingRules();
    }

    public static function settingRules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'base_country' => ['nullable', 'string', 'max:100'],
            'base_currency' => ['required', 'string', 'max:10'],
            'low_stock_notification_email' => ['nullable', 'email:rfc', 'max:255'],
            'default_branch' => ['nullable', 'string', 'max:255'],
            'stock_costing_method' => ['nullable', 'string', 'max:100'],
            'low_stock_policy' => ['nullable', 'string', 'max:255'],
            'maximum_discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'settings_panel' => ['nullable', 'string', 'max:80'],
        ];
    }
}
