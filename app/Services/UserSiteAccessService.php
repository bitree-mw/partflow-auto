<?php

namespace App\Services;

use App\Models\UserSiteAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserSiteAccessService
{
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

            return $access->load(['user.role', 'site']);
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

            $access->update($data);

            return $access->refresh()->load(['user.role', 'site']);
        });
    }

    public function delete(UserSiteAccess $access): void
    {
        $access->delete();
    }
}
