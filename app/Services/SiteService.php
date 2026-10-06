<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\InventoryDocument;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SiteService
{
    private const AUDITED_FIELDS = ['name', 'code', 'type', 'location', 'phone', 'address', 'is_active'];

    private const OPEN_DOCUMENT_STATUSES = ['draft', 'pending'];

    public function __construct(
        private readonly SiteAccessService $siteAccessService,
        private readonly AuditLogService $auditLog
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

    /**
     * All sites, including inactive ones, for the super admin console.
     */
    public function paginateForAdministration(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Site::query()
            ->withCount([
                'siteStocks as stock_lines_count' => fn ($query) => $query->where(fn ($inner) => $inner->where('quantity_on_hand', '!=', 0)->orWhere('reserved_quantity', '!=', 0)),
            ])
            ->withSum('siteStocks as stock_on_hand', 'quantity_on_hand')
            ->search($filters['search'] ?? null)
            ->type($filters['type'] ?? null)
            ->when(($filters['status'] ?? '') === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? '') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Site
    {
        return DB::transaction(function () use ($data): Site {
            $site = Site::create([
                'name' => $data['name'],
                'code' => filled($data['code'] ?? null) ? strtoupper($data['code']) : $this->generateCode($data['name']),
                'type' => $data['type'],
                'location' => $data['location'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->auditLog->record('site.created', "Created site {$site->name} ({$site->code}).", $site, [
                'attributes' => $site->only(self::AUDITED_FIELDS),
            ]);

            return $site;
        });
    }

    /**
     * Update site details. Activation changes go through setActive() so their stock rules are enforced.
     */
    public function update(Site $site, array $data): Site
    {
        unset($data['is_active']);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        return DB::transaction(function () use ($site, $data): Site {
            $before = $site->only(self::AUDITED_FIELDS);
            $site->update($data);
            $site->refresh();

            $changes = $this->auditLog->diff($before, $site->only(self::AUDITED_FIELDS));

            if ($changes !== []) {
                $this->auditLog->record('site.updated', "Updated site {$site->name}.", $site, ['changes' => $changes]);
            }

            return $site;
        });
    }

    public function setActive(Site $site, bool $active): Site
    {
        return DB::transaction(function () use ($site, $active): Site {
            $site = Site::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();

            if ((bool) $site->is_active === $active) {
                return $site;
            }

            if (! $active) {
                $this->ensureSiteIsEmpty($site, 'deactivated');
            }

            $site->update(['is_active' => $active]);

            $this->auditLog->record(
                $active ? 'site.activated' : 'site.deactivated',
                ($active ? 'Activated' : 'Deactivated')." site {$site->name}.",
                $site
            );

            return $site;
        });
    }

    /**
     * Soft delete only: completed stock, sales and purchase history keep their site reference.
     */
    public function delete(Site $site): void
    {
        DB::transaction(function () use ($site): void {
            $site = Site::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();

            $this->ensureSiteIsEmpty($site, 'deleted');

            $site->delete();

            $this->auditLog->record('site.deleted', "Deleted site {$site->name} ({$site->code}).", $site, [
                'attributes' => $site->only(self::AUDITED_FIELDS),
            ]);
        });
    }

    public function generateCode(string $name): string
    {
        $base = Str::of($name)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->substr(0, 6)
            ->toString() ?: 'SITE';
        $code = $base;
        $counter = 1;

        // Soft-deleted sites still hold their code in the unique index.
        while (Site::withTrashed()->where('code', $code)->exists()) {
            $code = $base.str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $code;
    }

    private function ensureSiteIsEmpty(Site $site, string $action): void
    {
        $stockLines = SiteStock::query()
            ->where('site_id', $site->id)
            ->where(fn ($query) => $query->where('quantity_on_hand', '!=', 0)->orWhere('reserved_quantity', '!=', 0))
            ->lockForUpdate()
            ->count();

        if ($stockLines > 0) {
            throw new BusinessRuleException(
                "{$site->name} cannot be {$action}: {$stockLines} ".Str::plural('part', $stockLines).' still '.($stockLines === 1 ? 'has' : 'have').' stock on hand or reserved. Transfer, sell or adjust all stock to zero first.'
            );
        }

        $openDocuments = InventoryDocument::query()
            ->whereIn('status', self::OPEN_DOCUMENT_STATUSES)
            ->where(fn ($query) => $query->where('source_site_id', $site->id)->orWhere('destination_site_id', $site->id))
            ->count();

        if ($openDocuments > 0) {
            throw new BusinessRuleException(
                "{$site->name} cannot be {$action}: {$openDocuments} draft or pending ".Str::plural('document', $openDocuments).' still use this site. Complete or cancel them first.'
            );
        }
    }
}
