import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/back-office.css',
                'resources/css/dashboard.css',
                'resources/css/settings.css',
                'resources/css/catalog.css',
                'resources/css/sales.css',
                'resources/css/purchases.css',
                'resources/css/contacts.css',
                'resources/css/reports.css',
                'resources/css/alerts.css',
                'resources/css/payment-accounts.css',
                'resources/css/pos.css',
                'resources/js/app.js',
                'resources/js/pos.js',
                'resources/js/settings.js',
                'resources/js/catalog.js',
                'resources/js/sales.js',
                'resources/js/purchases.js',
                'resources/js/contacts.js',
                'resources/js/reports.js',
                'resources/js/alerts.js',
                'resources/js/payment-accounts.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
