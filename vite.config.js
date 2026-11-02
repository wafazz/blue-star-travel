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
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5 still uses Sass @import and a few old built-ins. These are deprecation
                // warnings only (the build passes), so hide them until Bootstrap is moved to @use.
                quietDeps: true,
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
