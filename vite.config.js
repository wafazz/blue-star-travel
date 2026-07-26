import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // agent.js is its own entry: the agent portal is a standalone phone shell
            // and must not pull in the Bootstrap admin bundle.
            input: ['resources/scss/app.scss', 'resources/js/app.js', 'resources/js/agent.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
