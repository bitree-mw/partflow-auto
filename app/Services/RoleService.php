<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleService
{
    public function list(array $filters = []): Collection
    {
        return Role::query()
            ->withCount('users')
            ->search($filters['search'] ?? null)
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Role
    {
        return Role::create([
            'name' => $data['name'],
            'permissions' => $data['permissions'] ?? [],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Role $role, array $data): Role
    {
        $role->update([
            'name' => $data['name'] ?? $role->name,
            'permissions' => array_key_exists('permissions', $data)
                ? ($data['permissions'] ?? [])
                : $role->permissions,
            'is_active' => $data['is_active'] ?? $role->is_active,
        ]);

        return $role->refresh()->loadCount('users');
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }
}
