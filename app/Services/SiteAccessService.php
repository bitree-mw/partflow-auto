<?php

namespace App\Services;

use App\Models\InventoryDocument;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Auth\Access\AuthorizationException;

class SiteAccessService
{
    public const MAKE_SALES = 'can_make_sales';

    public const RECEIVE_STOCK = 'can_receive_stock';

    public const TRANSFER_STOCK = 'can_transfer_stock';

    public const ADJUST_STOCK = 'can_adjust_stock';

    private const OPERATION_FLAGS = [
        self::MAKE_SALES,
        self::RECEIVE_STOCK,
        self::TRANSFER_STOCK,
        self::ADJUST_STOCK,
    ];

    public function allowedSiteIds(User $user, ?string $operation = null): array
    {
        $this->validateOperation($operation);

        // Packages without branch access control (single branch) let every active user work at the active site.
        if ($this->isSystemAdministrator($user) || ($user->is_active && ! $this->packages->has('branch_access'))) {
            return Site::query()
                ->active()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return UserSiteAccess::query()
            ->active()
            ->where('user_id', $user->id)
            ->when($operation, fn ($query) => $query->where($operation, true))
            ->whereHas('site', fn ($query) => $query->active())
            ->orderBy('site_id')
            ->pluck('site_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function __construct(private readonly PackageService $packages) {}

    public function scopeFilters(User $user, array $filters = [], ?string $operation = null): array
    {
        $allowedSiteIds = $this->allowedSiteIds($user, $operation);
        $filters['include_unassigned_site'] = $this->isSystemAdministrator($user);

        if (filled($filters['site_id'] ?? null)) {
            $siteId = (int) $filters['site_id'];
            $this->authorizeSite($user, $siteId, $operation);
            $filters['site_ids'] = $allowedSiteIds;

            return $filters;
        }

        $filters['site_ids'] = $allowedSiteIds;

        return $filters;
    }

    public function authorizeSite(User $user, int $siteId, ?string $operation = null): void
    {
        if (! in_array($siteId, $this->allowedSiteIds($user, $operation), true)) {
            throw new AuthorizationException('You are not allowed to access this site.');
        }
    }

    public function authorizeOptionalSite(User $user, ?int $siteId): void
    {
        if ($siteId === null) {
            if (! $this->isSystemAdministrator($user)) {
                throw new AuthorizationException('Only a system administrator may access records without a site.');
            }

            return;
        }

        $this->authorizeSite($user, $siteId);
    }

    public function authorizeSites(User $user, array $siteIds, ?string $operation = null): void
    {
        foreach (array_unique(array_map('intval', array_filter($siteIds))) as $siteId) {
            $this->authorizeSite($user, $siteId, $operation);
        }
    }

    public function authorizeInventoryDocument(User $user, InventoryDocument $document): void
    {
        $siteIds = array_filter([
            $document->source_site_id,
            $document->destination_site_id,
        ]);

        if ($siteIds === []) {
            throw new AuthorizationException('This inventory document is not assigned to a site.');
        }

        $this->authorizeSites($user, $siteIds);
    }

    public function authorizeInventoryDocumentOperation(User $user, InventoryDocument $document): void
    {
        $operation = match ($document->document_type) {
            'sale', 'sale_return' => self::MAKE_SALES,
            'purchase', 'purchase_return' => self::RECEIVE_STOCK,
            'transfer' => self::TRANSFER_STOCK,
            'adjustment', 'stock_take' => self::ADJUST_STOCK,
            default => null,
        };

        $siteIds = array_filter([
            $document->source_site_id,
            $document->destination_site_id,
        ]);

        if ($siteIds === []) {
            throw new AuthorizationException('This inventory document is not assigned to a site.');
        }

        $this->authorizeSites($user, $siteIds, $operation);
    }

    public function isSystemAdministrator(User $user): bool
    {
        return $user->hasPermission('*');
    }

    private function validateOperation(?string $operation): void
    {
        if ($operation !== null && ! in_array($operation, self::OPERATION_FLAGS, true)) {
            throw new \InvalidArgumentException("Unsupported site operation flag [{$operation}].");
        }
    }
}
