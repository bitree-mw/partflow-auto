<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultSystemUserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = $this->seedRoles();
        $users = $this->seedUsers($roles);
        $this->seedSiteAccess($users);
    }

    private function seedRoles(): array
    {
        $roles = [
            'System Administrator' => ['*'],
            'Branch Manager' => ['sales.*', 'purchases.*', 'stock.*', 'catalogue.*', 'reports.view', 'settings.view'],
            'Cashier' => ['pos.use', 'sales.create', 'sales.view', 'customers.view'],
            'Stock Controller' => ['purchases.*', 'stock.*', 'catalogue.view', 'suppliers.view'],
            'Reports Viewer' => ['reports.view', 'sales.view', 'purchases.view', 'stock.view'],
        ];

        return collect($roles)
            ->mapWithKeys(fn (array $permissions, string $name) => [
                $name => Role::updateOrCreate(
                    ['name' => $name],
                    ['permissions' => $permissions, 'is_active' => true]
                ),
            ])
            ->all();
    }

    private function seedUsers(array $roles): array
    {
        $password = Hash::make(env('DEFAULT_SYSTEM_USER_PASSWORD', 'password'));
        $users = [
            [
                'name' => 'System Admin',
                'username' => 'admin',
                'email' => 'admin@partflow.test',
                'phone' => '+265 991 000 001',
                'role' => 'System Administrator',
                'access_level' => 'admin',
            ],
            [
                'name' => 'Branch Manager',
                'username' => 'manager',
                'email' => 'manager@partflow.test',
                'phone' => '+265 991 000 002',
                'role' => 'Branch Manager',
                'access_level' => 'manager',
            ],
            [
                'name' => 'Default Cashier',
                'username' => 'cashier',
                'email' => 'cashier@partflow.test',
                'phone' => '+265 991 000 003',
                'role' => 'Cashier',
                'access_level' => 'sales',
            ],
            [
                'name' => 'Stock Controller',
                'username' => 'stock',
                'email' => 'stock@partflow.test',
                'phone' => '+265 991 000 004',
                'role' => 'Stock Controller',
                'access_level' => 'stock',
            ],
            [
                'name' => 'Reports User',
                'username' => 'reports',
                'email' => 'reports@partflow.test',
                'phone' => '+265 991 000 005',
                'role' => 'Reports Viewer',
                'access_level' => 'view_only',
            ],
        ];

        return collect($users)
            ->mapWithKeys(fn (array $record) => [
                $record['email'] => User::updateOrCreate(
                    ['email' => $record['email']],
                    [
                        'role_id' => $roles[$record['role']]->id,
                        'name' => $record['name'],
                        'username' => $record['username'],
                        'phone' => $record['phone'],
                        'password' => $password,
                        'is_active' => true,
                    ]
                )->setAttribute('seed_access_level', $record['access_level']),
            ])
            ->all();
    }

    private function seedSiteAccess(array $users): void
    {
        $sites = Site::query()->active()->orderBy('id')->get();

        if ($sites->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            foreach ($sites as $index => $site) {
                $access = $user->getAttribute('seed_access_level');

                UserSiteAccess::updateOrCreate(
                    ['user_id' => $user->id, 'site_id' => $site->id],
                    [
                        'access_level' => $access,
                        'can_view_stock' => true,
                        'can_make_sales' => in_array($access, ['admin', 'manager', 'sales'], true),
                        'can_receive_stock' => in_array($access, ['admin', 'manager', 'stock'], true),
                        'can_transfer_stock' => in_array($access, ['admin', 'manager', 'stock'], true),
                        'can_adjust_stock' => in_array($access, ['admin', 'manager', 'stock'], true),
                        'is_default' => $index === 0,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
