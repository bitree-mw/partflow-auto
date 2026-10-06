<?php

/*
| Subscription packages chosen from the super admin console (/suadmin).
| Only features that differ between packages are listed under "features"; point of sale, purchases,
| stock takes, payments, dashboard and reports, roles and permissions, and the company name are in every package.
*/

$drive = [
    'multi_branch',
    'stock_transfers',
    'branch_access',
    'customer_balances',
    'expenses',
    'low_stock_emails',
    'custom_branding',
];

$overdrive = [...$drive, 'vehicle_fitment_search', 'csv_exports'];

return [
    // Existing installations keep every feature until a package is chosen.
    'default' => 'autopilot',

    'feature_labels' => [
        'multi_branch' => 'Stock tracked separately at every branch',
        'stock_transfers' => 'Stock transfers between branches',
        'branch_access' => 'Branch access control',
        'customer_balances' => 'Customer balances (credit sales)',
        'expenses' => 'Expenses',
        'low_stock_emails' => 'Low-stock email alerts',
        'custom_branding' => 'Company logo and colours',
        'vehicle_fitment_search' => 'Point of sale vehicle fitment search',
        'csv_exports' => 'CSV exports',
        'support_247' => '24/7 support',
    ],

    'packages' => [
        'ignition' => [
            'name' => 'Ignition',
            'monthly_price' => 15000,
            'features' => [],
            'highlights' => [
                'Point of sale',
                'Purchases and stock takes',
                'Payments',
                'Dashboard and reports',
                'Roles and permissions',
                'Your company name',
            ],
        ],
        'drive' => [
            'name' => 'Drive',
            'monthly_price' => 20000,
            'features' => $drive,
            'highlights' => [
                'Point of sale',
                'Stock tracked separately at every branch',
                'Purchases, transfers, and stock takes',
                'Payments, customer balances, and expenses',
                'Dashboard and reports',
                'Low-stock email alerts',
                'Roles, permissions, and branch access',
                'Your company name, logo, and colours',
            ],
        ],
        'overdrive' => [
            'name' => 'Overdrive',
            'monthly_price' => 45000,
            'features' => $overdrive,
            'highlights' => [
                'Point of sale with vehicle fitment search',
                'Stock tracked separately at every branch',
                'Purchases, transfers, and stock takes',
                'Payments, customer balances, and expenses',
                'Dashboard, reports, and CSV exports',
                'Low-stock email alerts',
                'Roles, permissions, and branch access',
                'Your company name, logo, and colours',
            ],
        ],
        'autopilot' => [
            'name' => 'Autopilot',
            'monthly_price' => 60000,
            'features' => [...$overdrive, 'support_247'],
            'highlights' => [
                'Point of sale with vehicle fitment search',
                'Stock tracked separately at every branch',
                'Purchases, transfers, and stock takes',
                'Payments, customer balances, and expenses',
                'Dashboard, reports, and CSV exports',
                'Low-stock email alerts',
                'Roles, permissions, and branch access',
                'Your company name, logo, and colours',
                '24/7 support',
            ],
        ],
    ],
];
