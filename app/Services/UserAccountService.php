<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserAccountService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return User::query()
            ->with(['role', 'siteAccesses' => fn ($query) => $query->active()->where('is_default', true)->with('site')])
            ->search($filters['search'] ?? null)
            ->when(filled($filters['role_id'] ?? null), fn ($query) => $query->where('role_id', $filters['role_id']))
            ->when(($filters['status'] ?? '') === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? '') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{name: string, email: string, username?: ?string, phone?: ?string, role?: ?Role, site?: ?string, password?: ?string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'role_id' => ($data['role'] ?? null)?->id,
                'name' => $data['name'],
                'username' => filled($data['username'] ?? null) ? $data['username'] : $this->generateUsername($data['email']),
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password'] ?? Str::random(12)),
                'is_active' => true,
            ]);

            $this->syncSite($user, $data['site'] ?? null);

            $this->auditLog->record('user.created', "Created user {$user->name}.", $user, [
                'username' => $user->username,
                'email' => $user->email,
                'role' => ($data['role'] ?? null)?->name,
                'default_site' => $data['site'] ?? 'All sites',
            ]);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, username?: ?string, phone?: ?string, role?: ?Role, site?: ?string, password?: ?string, is_active: bool}  $data
     */
    public function update(User $user, array $data, ?User $actingUser = null): User
    {
        $role = $data['role'] ?? null;
        $isActive = (bool) $data['is_active'];

        if ($actingUser && (int) $user->id === (int) $actingUser->id && ! $isActive) {
            throw new BusinessRuleException('You cannot deactivate your own account.');
        }

        if ($this->wouldRemoveLastAdministrator($user, $role, $isActive)) {
            throw new BusinessRuleException('At least one active system administrator must remain.');
        }

        $passwordChanged = filled($data['password'] ?? null);

        return DB::transaction(function () use ($user, $data, $role, $isActive, $passwordChanged): User {
            $user->loadMissing('role');
            $before = $this->auditSnapshot($user);

            $attributes = [
                'role_id' => $role?->id,
                'name' => $data['name'],
                'username' => filled($data['username'] ?? null) ? $data['username'] : $user->username,
                'email' => strtolower($data['email']),
                'is_active' => $isActive,
            ];

            if (array_key_exists('phone', $data)) {
                $attributes['phone'] = $data['phone'];
            }

            if ($passwordChanged) {
                $attributes['password'] = Hash::make($data['password']);
            }

            $user->update($attributes);
            $this->syncSite($user, $data['site'] ?? null);

            if (! $isActive || $passwordChanged) {
                $this->revokeAccess($user);
            }

            $user->load('role');
            $changes = $this->auditLog->diff($before, $this->auditSnapshot($user, $data['site'] ?? 'All sites'));

            if ($passwordChanged) {
                $changes['password'] = 'changed';
            }

            $this->auditLog->record('user.updated', "Updated user {$user->name}.", $user, ['changes' => $changes]);

            return $user;
        });
    }

    public function deactivate(User $user, ?User $actingUser = null): void
    {
        $user->loadMissing('role');

        if ($actingUser && (int) $user->id === (int) $actingUser->id) {
            throw new BusinessRuleException('You cannot deactivate your own account.');
        }

        if ($this->wouldRemoveLastAdministrator($user, $user->role, false)) {
            throw new BusinessRuleException('At least one active system administrator must remain.');
        }

        DB::transaction(function () use ($user): void {
            $user->update(['is_active' => false]);
            $this->revokeAccess($user);
            $this->auditLog->record('user.deactivated', "Deactivated user {$user->name}.", $user);
        });
    }

    public function roleOptions(): Collection
    {
        return Role::query()->active()->orderBy('name')->get(['id', 'name', 'permissions']);
    }

    public function siteOptions(): Collection
    {
        return Site::query()->active()->orderBy('name')->pluck('name');
    }

    public function isAdministrator(User $user): bool
    {
        return in_array('*', $user->role?->permissions ?? [], true);
    }

    public function defaultSiteName(User $user): ?string
    {
        return $user->siteAccesses()
            ->active()
            ->where('is_default', true)
            ->with('site')
            ->first()
            ?->site
            ?->name;
    }

    private function auditSnapshot(User $user, ?string $siteName = null): array
    {
        return [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role?->name,
            'is_active' => (bool) $user->is_active,
            'default_site' => $siteName ?? ($this->defaultSiteName($user) ?? 'All sites'),
        ];
    }

    private function syncSite(User $user, ?string $siteName): void
    {
        $user->siteAccesses()->update([
            'is_default' => false,
            'is_active' => false,
        ]);

        if (! filled($siteName) || $siteName === 'All sites') {
            return;
        }

        $site = Site::query()->active()->where('name', $siteName)->firstOrFail();
        $access = UserSiteAccess::withTrashed()->firstOrNew([
            'user_id' => $user->id,
            'site_id' => $site->id,
        ]);

        if ($access->trashed()) {
            $access->restore();
        }

        $access->fill([
            'access_level' => $access->access_level ?: 'manager',
            'can_view_stock' => $access->exists ? $access->can_view_stock : true,
            'can_make_sales' => $access->exists ? $access->can_make_sales : true,
            'can_receive_stock' => $access->exists ? $access->can_receive_stock : true,
            'can_transfer_stock' => $access->exists ? $access->can_transfer_stock : false,
            'can_adjust_stock' => $access->exists ? $access->can_adjust_stock : false,
            'is_default' => true,
            'is_active' => true,
        ])->save();
    }

    private function wouldRemoveLastAdministrator(User $user, ?Role $newRole, bool $willRemainActive): bool
    {
        $user->loadMissing('role');
        $isAdministrator = $this->isAdministrator($user);
        $willBeAdministrator = $willRemainActive && in_array('*', $newRole?->permissions ?? [], true);

        if (! $isAdministrator || $willBeAdministrator) {
            return false;
        }

        return User::query()
            ->active()
            ->whereKeyNot($user->id)
            ->with('role')
            ->get()
            ->doesntContain(fn (User $candidate): bool => $this->isAdministrator($candidate));
    }

    private function revokeAccess(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') !== 'database') {
            return;
        }

        $sessionTable = config('session.table', 'sessions');

        if (Schema::hasTable($sessionTable)) {
            DB::table($sessionTable)->where('user_id', $user->id)->delete();
        }
    }

    private function generateUsername(string $email): string
    {
        $baseUsername = Str::slug(Str::before($email, '@')) ?: 'user';
        $username = $baseUsername;
        $suffix = 2;

        while (User::query()->where('username', $username)->exists()) {
            $username = "{$baseUsername}{$suffix}";
            $suffix++;
        }

        return $username;
    }
}
