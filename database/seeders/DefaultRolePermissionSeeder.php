<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class DefaultRolePermissionSeeder extends Seeder
{
    public const DEFINITIONS = [
        'System Administrator' => ['*'],
        'Branch Manager' => ['sales.*', 'purchases.*', 'customers.*', 'suppliers.*', 'stock.*', 'catalogue.*', 'reports.view', 'payment-accounts.*'],
        'Cashier' => ['pos.use', 'sales.create', 'sales.view', 'customers.view', 'payment-accounts.view'],
        'Stock Controller' => ['purchases.*', 'stock.*', 'catalogue.view', 'suppliers.view', 'payment-accounts.view'],
        'Reports Viewer' => ['reports.view', 'sales.view', 'purchases.view', 'stock.view'],
    ];

    public function run(): void
    {
        self::upsert();
    }

    /**
     * @return array<string, Role>
     */
    public static function upsert(): array
    {
        return collect(self::DEFINITIONS)
            ->mapWithKeys(fn (array $permissions, string $name) => [
                $name => Role::updateOrCreate(
                    ['name' => $name],
                    ['permissions' => $permissions, 'is_active' => true]
                ),
            ])
            ->all();
    }
}
