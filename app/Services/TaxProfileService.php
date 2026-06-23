<?php

namespace App\Services;

use App\Models\TaxProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaxProfileService
{
    public function list(array $filters = []): Collection
    {
        return TaxProfile::query()
            ->search($filters['search'] ?? null)
            ->when(isset($filters['tax_type']), function ($query) use ($filters) {
                $query->where('tax_type', $filters['tax_type']);
            })
            ->when(isset($filters['price_mode']), function ($query) use ($filters) {
                $query->where('price_mode', $filters['price_mode']);
            })
            ->when(isset($filters['is_default']), function ($query) use ($filters) {
                $query->where('is_default', filter_var($filters['is_default'], FILTER_VALIDATE_BOOLEAN));
            })
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): TaxProfile
    {
        return DB::transaction(function () use ($data) {
            if (($data['is_default'] ?? false) === true) {
                TaxProfile::query()->update(['is_default' => false]);
            }

            $data = $this->prepareData($data);

            return TaxProfile::create($data);
        });
    }

    public function update(TaxProfile $taxProfile, array $data): TaxProfile
    {
        return DB::transaction(function () use ($taxProfile, $data) {
            if (($data['is_default'] ?? false) === true) {
                TaxProfile::query()
                    ->where('id', '!=', $taxProfile->id)
                    ->update(['is_default' => false]);
            }

            $taxProfile->update($this->prepareData($data, $taxProfile));

            return $taxProfile->refresh();
        });
    }

    public function delete(TaxProfile $taxProfile): void
    {
        $taxProfile->delete();
    }

    private function prepareData(array $data, ?TaxProfile $existingProfile = null): array
    {
        if (array_key_exists('code', $data)) {
            $data['code'] = Str::of($data['code'])
                ->upper()
                ->replaceMatches('/[^A-Z0-9\-_]/', '')
                ->toString();
        }

        $priceMode = $data['price_mode'] ?? $existingProfile?->price_mode;

        if (in_array($priceMode, ['exempt', 'none'], true)) {
            $data['tax_rate'] = 0;
            $data['is_exempt'] = $priceMode === 'exempt';
        }

        if (($data['tax_type'] ?? $existingProfile?->tax_type) === 'none') {
            $data['tax_rate'] = 0;
            $data['price_mode'] = 'none';
            $data['is_exempt'] = false;
            $data['exemption_reason'] = null;
        }

        $data['is_active'] = $data['is_active'] ?? $existingProfile?->is_active ?? true;
        $data['is_default'] = $data['is_default'] ?? $existingProfile?->is_default ?? false;

        return $data;
    }
}
