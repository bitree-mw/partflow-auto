<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BusinessSettingsService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function update(array $settings): void
    {
        $logo = $settings['company_logo'] ?? null;
        $oldLogoPath = BusinessSetting::query()->where('key', 'company_logo_path')->first()?->value;
        $newLogoPath = null;

        if ($logo instanceof UploadedFile) {
            $newLogoPath = $logo->store('branding', 'public');

            if (! is_string($newLogoPath)) {
                throw new RuntimeException('The company logo could not be stored.');
            }

            $settings['company_logo_path'] = $newLogoPath;
        }

        unset($settings['company_logo']);

        try {
            DB::transaction(function () use ($settings): void {
                $values = collect($settings)->except('settings_panel');
                $before = BusinessSetting::query()
                    ->whereIn('key', $values->keys())
                    ->pluck('value', 'key')
                    ->all();

                $values->each(fn (mixed $value, string $key) => BusinessSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                ));

                $changes = $this->auditLog->diff($before, $values->all());

                if ($changes !== []) {
                    $this->auditLog->record('settings.updated', 'Updated business settings: '.implode(', ', array_keys($changes)).'.', null, [
                        'changes' => $changes,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            if ($newLogoPath) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }

        if ($newLogoPath && is_string($oldLogoPath) && $oldLogoPath !== $newLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }
    }
}
