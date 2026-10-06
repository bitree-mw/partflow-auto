<?php

namespace App\Services;

use App\Models\Site;
use App\Models\User;

class SuperAdminOverviewService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly UserAccountService $userAccounts
    ) {}

    public function summary(): array
    {
        $activeUsers = User::query()->active()->with('role')->get();

        return [
            'cards' => [
                ['label' => 'Active sites', 'value' => Site::query()->active()->count(), 'detail' => Site::query()->where('is_active', false)->count().' inactive'],
                ['label' => 'Active users', 'value' => $activeUsers->count(), 'detail' => User::query()->where('is_active', false)->count().' inactive'],
                ['label' => 'Administrators', 'value' => $activeUsers->filter(fn (User $user) => $this->userAccounts->isAdministrator($user))->count(), 'detail' => 'Active accounts with full access'],
                ['label' => 'Failed sign-ins (24h)', 'value' => $this->auditLog->countSince('auth.login.failed', now()->subDay()) + $this->auditLog->countSince('suadmin.login.failed', now()->subDay()), 'detail' => 'Staff and super admin'],
            ],
            'auditReady' => $this->auditLog->tableReady(),
            'recentActivity' => $this->auditLog->recent(12),
        ];
    }
}
