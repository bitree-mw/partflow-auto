<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\BusinessSetting;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * The subscription package of this installation and the features it unlocks.
 */
class PackageService
{
    public const SETTING_KEY = 'subscription_package';

    public const SUPPORT_FIELDS = ['support_phone', 'support_whatsapp', 'support_email', 'support_hours'];

    // Report types that belong to a package feature; every other report is in all packages.
    private const REPORT_FEATURES = [
        'debtors' => 'customer_balances',
        'debtor-report' => 'customer_balances',
        'debtor-balances' => 'customer_balances',
        'customer-balances' => 'customer_balances',
        'expenses' => 'expenses',
        'stock-transfers' => 'stock_transfers',
        'stock-transfer' => 'stock_transfers',
        'stock-transfer-history' => 'stock_transfers',
        'transfers' => 'stock_transfers',
        'transfer' => 'stock_transfers',
    ];

    private const REQUEST_CACHE_KEY = 'partflow.package';

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @return array<string, array{key: string, name: string, monthly_price: int, features: list<string>, highlights: list<string>}>
     */
    public function packages(): array
    {
        return collect(config('packages.packages'))
            ->map(fn (array $package, string $key): array => ['key' => $key, ...$package])
            ->all();
    }

    public function current(): array
    {
        return $this->packages()[$this->currentKey()];
    }

    public function currentKey(): string
    {
        $request = app()->bound('request') ? app('request') : null;
        // Long-running console workers must see package changes, so only HTTP requests are memoised.
        $cacheable = $request && (! app()->runningInConsole() || app()->runningUnitTests());

        if ($cacheable && $request->attributes->has(self::REQUEST_CACHE_KEY)) {
            return $request->attributes->get(self::REQUEST_CACHE_KEY);
        }

        $key = $this->storedKey();

        if ($cacheable) {
            $request->attributes->set(self::REQUEST_CACHE_KEY, $key);
        }

        return $key;
    }

    public function has(string $feature): bool
    {
        return in_array($feature, $this->current()['features'], true);
    }

    /**
     * Abort with a 403 that explains which package unlocks the feature.
     */
    public function ensure(string $feature): void
    {
        if (! $this->has($feature)) {
            throw new AccessDeniedHttpException($this->missingFeatureMessage($feature));
        }
    }

    public function missingFeatureMessage(string $feature): string
    {
        $label = config("packages.feature_labels.{$feature}", $feature);
        $upgrade = collect($this->packages())->first(fn (array $package) => in_array($feature, $package['features'], true));
        $suffix = $upgrade ? " Upgrade to {$upgrade['name']} or higher to use it." : '';

        return "{$label} is not included in your {$this->current()['name']} package.{$suffix}";
    }

    public function reportAllowed(?string $reportType): bool
    {
        $feature = self::REPORT_FEATURES[$reportType ?? ''] ?? null;

        return $feature === null || $this->has($feature);
    }

    public function reportFeature(?string $reportType): ?string
    {
        return self::REPORT_FEATURES[$reportType ?? ''] ?? null;
    }

    public function change(string $key): void
    {
        $package = $this->packages()[$key] ?? throw new InvalidArgumentException("Unknown package [{$key}].");
        $previousKey = $this->currentKey();

        if ($previousKey === $key) {
            return;
        }

        DB::transaction(function () use ($key, $package, $previousKey): void {
            if (! in_array('multi_branch', $package['features'], true)) {
                $activeSites = Site::query()->active()->lockForUpdate()->count();

                if ($activeSites > 1) {
                    throw new BusinessRuleException(
                        "{$package['name']} supports one active branch, but {$activeSites} branches are active. Deactivate or delete the extra branches first."
                    );
                }
            }

            BusinessSetting::query()->updateOrCreate(['key' => self::SETTING_KEY], ['value' => $key]);

            $this->auditLog->record('package.changed', "Changed package from {$this->packages()[$previousKey]['name']} to {$package['name']}.", null, [
                'from' => $previousKey,
                'to' => $key,
            ]);
        });

        if (app()->bound('request')) {
            app('request')->attributes->set(self::REQUEST_CACHE_KEY, $key);
        }
    }

    /**
     * @return array{support_phone: ?string, support_whatsapp: ?string, support_email: ?string, support_hours: ?string}
     */
    public function supportContact(): array
    {
        $stored = $this->settingsTableReady()
            ? BusinessSetting::query()->whereIn('key', self::SUPPORT_FIELDS)->pluck('value', 'key')->all()
            : [];

        return collect(self::SUPPORT_FIELDS)->mapWithKeys(fn (string $field) => [$field => $stored[$field] ?? null])->all();
    }

    public function updateSupportContact(array $contact): void
    {
        DB::transaction(function () use ($contact): void {
            $before = $this->supportContact();
            $after = collect(self::SUPPORT_FIELDS)->mapWithKeys(fn (string $field) => [$field => $contact[$field] ?? null])->all();

            foreach ($after as $field => $value) {
                BusinessSetting::query()->updateOrCreate(['key' => $field], ['value' => $value]);
            }

            $changes = $this->auditLog->diff($before, $after);

            if ($changes !== []) {
                $this->auditLog->record('package.support_updated', 'Updated the 24/7 support contact details.', null, ['changes' => $changes]);
            }
        });
    }

    private function storedKey(): string
    {
        $default = (string) config('packages.default', 'autopilot');

        try {
            $stored = $this->settingsTableReady()
                ? BusinessSetting::query()->where('key', self::SETTING_KEY)->value('value')
                : null;
        } catch (Throwable) {
            $stored = null;
        }

        return is_string($stored) && array_key_exists($stored, config('packages.packages')) ? $stored : $default;
    }

    private function settingsTableReady(): bool
    {
        return Schema::hasTable('business_settings');
    }
}
