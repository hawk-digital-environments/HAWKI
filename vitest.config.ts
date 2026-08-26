import path from 'node:path';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [svelte()],
    resolve: {
        // Compile Svelte components for the client instead of SSR, so `mount()`
        // is available inside the happy-dom test environment.
        conditions: ['browser'],
        alias: [
            {
                find: '$lib',
                replacement: path.resolve('./resources/js')
            },
            {
                find: '$plugins',
                replacement: path.resolve('./resources/js/plugins')
            }
        ]
    },
    test: {
        environment: 'happy-dom',
        include: ['resources/js/**/__tests__/**/*.spec.ts', 'resources/js/**/*.spec.ts'],
        coverage: {
            include: ['resources/js/**'],
            exclude: ['resources/js/**/__tests__/**']
        }
    }
});
