<?php

namespace App\Services;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class SiteService
{
    public function __construct(
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function list(array $filters = [], ?User $user = null, ?string $operation = null): Collection
    {
        if ($user) {
            $filters = $this->siteAccessService->scopeFilters($user, $filters, $operation);
        }

        return Site::query()
            ->search($filters['search'] ?? null)
            ->type($filters['type'] ?? null)
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('id', $filters['site_ids']))
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Site
    {
        return Site::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'type' => $data['type'],
            'location' => $data['location'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Site $site, array $data): Site
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $site->update($data);

        return $site->refresh();
    }

    public function delete(Site $site): void
    {
        $site->delete();
    }
}
