import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/company-settings.css',
                'resources/css/calendar-appointments.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        hmr: {
            host: 'localhost',
        },
        host: '127.0.0.1',
        port: 5173,
        cors: {
            origin: [
                'http://bron',
                'https://bron',
                'http://localhost',
                'https://localhost',
                'http://127.0.0.1',
                'https://127.0.0.1'
            ],
            credentials: true
        }
    },
});
