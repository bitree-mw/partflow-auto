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
                'resources/css/operations.css',
                'resources/css/pos.css',
                'resources/js/app.js',
                'resources/js/pos.js',
                'resources/js/settings.js',
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
