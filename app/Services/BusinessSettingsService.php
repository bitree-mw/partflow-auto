<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;

class BusinessSettingsService
{
    public function update(array $settings): void
    {
        DB::transaction(function () use ($settings): void {
            collect($settings)
                ->except('settings_panel')
                ->each(fn (mixed $value, string $key) => BusinessSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                ));
        });
    }
}
