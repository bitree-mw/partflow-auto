<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One login per non-admin role for testing, all with the password "password".
 * Safe to re-run: accounts are created if missing, restored if deleted, and switched back on.
 * The admin account is never touched.
 *
 *     php artisan db:seed --class=RoleTestAccountsSeeder
 */
class RoleTestAccountsSeeder extends Seeder
{
    private const ACCOUNTS = [
        ['username' => 'manager', 'name' => 'Branch Manager', 'email' => 'manager@partflow.test', 'phone' => '+265 991 000 002', 'role' => 'Branch Manager', 'access' => 'manager'],
        ['username' => 'cashier', 'name' => 'Default Cashier', 'email' => 'cashier@partflow.test', 'phone' => '+265 991 000 003', 'role' => 'Cashier', 'access' => 'sales'],
        ['username' => 'stock', 'name' => 'Stock Controller', 'email' => 'stock@partflow.test', 'phone' => '+265 991 000 004', 'role' => 'Stock Controller', 'access' => 'stock'],
        ['username' => 'reports', 'name' => 'Reports User', 'email' => 'reports@partflow.test', 'phone' => '+265 991 000 005', 'role' => 'Reports Viewer', 'access' => 'view_only'],
    ];

    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'name')->all();

        if (count(array_intersect_key($roles, array_flip(array_column(self::ACCOUNTS, 'role')))) < count(self::ACCOUNTS)) {
            $roles = collect(DefaultRolePermissionSeeder::upsert())->map->id->all();
        }

        $sites = Site::query()->active()->orderBy('id')->get();
        $password = Hash::make('password');

        foreach (self::ACCOUNTS as $account) {
            // Earlier demo accounts may have been soft-deleted; bring those back rather than duplicate them.
            $user = User::withTrashed()
                ->where('username', $account['username'])
                ->orWhere('email', $account['email'])
                ->first() ?? new User;

            if ($user->trashed()) {
                $user->restore();
            }

            $user->forceFill([
                'name' => $user->exists ? $user->name : $account['name'],
                'username' => $account['username'],
                'email' => $user->exists ? $user->email : $account['email'],
                'phone' => $user->phone ?? $account['phone'],
                'role_id' => $roles[$account['role']],
                'password' => $password,
                'is_active' => true,
            ])->save();

            foreach ($sites as $index => $site) {
                UserSiteAccess::updateOrCreate(
                    ['user_id' => $user->id, 'site_id' => $site->id],
                    [
                        'access_level' => $account['access'],
                        'can_view_stock' => true,
                        'can_make_sales' => in_array($account['access'], ['manager', 'sales'], true),
                        'can_receive_stock' => in_array($account['access'], ['manager', 'stock'], true),
                        'can_transfer_stock' => in_array($account['access'], ['manager', 'stock'], true),
                        'can_adjust_stock' => in_array($account['access'], ['manager', 'stock'], true),
                        'is_default' => $index === 0,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
