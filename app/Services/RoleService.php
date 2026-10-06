<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoleService
{
    private const AUDITED_FIELDS = ['name', 'permissions', 'is_active'];

    public function __construct(private readonly AuditLogService $auditLog) {}

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
        return DB::transaction(function () use ($data): Role {
            $role = Role::create([
                'name' => $data['name'],
                'permissions' => $data['permissions'] ?? [],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->auditLog->record('role.created', "Created role {$role->name}.", $role, [
                'attributes' => $role->only(self::AUDITED_FIELDS),
            ]);

            return $role;
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $before = $role->only(self::AUDITED_FIELDS);

            $role->update([
                'name' => $data['name'] ?? $role->name,
                'permissions' => array_key_exists('permissions', $data)
                    ? ($data['permissions'] ?? [])
                    : $role->permissions,
                'is_active' => $data['is_active'] ?? $role->is_active,
            ]);

            $role->refresh()->loadCount('users');
            $changes = $this->auditLog->diff($before, $role->only(self::AUDITED_FIELDS));

            if ($changes !== []) {
                $this->auditLog->record('role.updated', "Updated role {$role->name}.", $role, ['changes' => $changes]);
            }

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            $role->delete();
            $this->auditLog->record('role.deleted', "Deleted role {$role->name}.", $role);
        });
    }
}
