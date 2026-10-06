<?php

namespace App\Services;

use App\Models\UserSiteAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserSiteAccessService
{
    private const AUDITED_FIELDS = [
        'access_level', 'can_view_stock', 'can_make_sales', 'can_receive_stock',
        'can_transfer_stock', 'can_adjust_stock', 'is_default', 'is_active',
    ];

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function list(array $filters = []): Collection
    {
        return UserSiteAccess::query()
            ->with(['user.role', 'site'])
            ->forUser(isset($filters['user_id']) ? (int) $filters['user_id'] : null)
            ->forSite(isset($filters['site_id']) ? (int) $filters['site_id'] : null)
            ->when(isset($filters['access_level']), function ($query) use ($filters) {
                $query->where('access_level', $filters['access_level']);
            })
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->latest()
            ->get();
    }

    public function create(array $data): UserSiteAccess
    {
        return DB::transaction(function () use ($data) {
            if (($data['is_default'] ?? false) === true) {
                UserSiteAccess::where('user_id', $data['user_id'])
                    ->update(['is_default' => false]);
            }

            $access = UserSiteAccess::create([
                'user_id' => $data['user_id'],
                'site_id' => $data['site_id'],
                'access_level' => $data['access_level'],
                'can_view_stock' => $data['can_view_stock'] ?? true,
                'can_make_sales' => $data['can_make_sales'] ?? false,
                'can_receive_stock' => $data['can_receive_stock'] ?? false,
                'can_transfer_stock' => $data['can_transfer_stock'] ?? false,
                'can_adjust_stock' => $data['can_adjust_stock'] ?? false,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $access->load(['user.role', 'site']);

            $this->auditLog->record('site_access.created', $this->describe('Granted', $access), $access, [
                'attributes' => $access->only(self::AUDITED_FIELDS),
            ]);

            return $access;
        });
    }

    public function update(UserSiteAccess $access, array $data): UserSiteAccess
    {
        return DB::transaction(function () use ($access, $data) {
            if (($data['is_default'] ?? false) === true) {
                UserSiteAccess::where('user_id', $access->user_id)
                    ->where('id', '!=', $access->id)
                    ->update(['is_default' => false]);
            }

            $before = $access->only(self::AUDITED_FIELDS);
            $access->update($data);
            $access->refresh()->load(['user.role', 'site']);

            $changes = $this->auditLog->diff($before, $access->only(self::AUDITED_FIELDS));

            if ($changes !== []) {
                $this->auditLog->record('site_access.updated', $this->describe('Changed', $access), $access, ['changes' => $changes]);
            }

            return $access;
        });
    }

    public function delete(UserSiteAccess $access): void
    {
        DB::transaction(function () use ($access): void {
            $access->loadMissing(['user', 'site']);
            $access->delete();
            $this->auditLog->record('site_access.deleted', $this->describe('Removed', $access), $access);
        });
    }

    private function describe(string $verb, UserSiteAccess $access): string
    {
        $user = $access->user?->name ?? "user #{$access->user_id}";
        $site = $access->site?->name ?? "site #{$access->site_id}";

        return "{$verb} site access for {$user} at {$site}.";
    }
}
