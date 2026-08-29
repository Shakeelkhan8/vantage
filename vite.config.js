import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // Vite runs inside a container; the browser reaches it on the host.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
        watch: {
            // inotify events do not cross a Windows bind mount, so without
            // polling the dev server never sees an edit and HMR looks broken.
            usePolling: true,
            interval: 400,
            ignored: ['**/storage/framework/views/**', '**/vendor/**', '**/node_modules/**'],
        },
    },
});
