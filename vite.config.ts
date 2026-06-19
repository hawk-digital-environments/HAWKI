import {defineConfig} from 'vite';
import {svelte} from '@sveltejs/vite-plugin-svelte';
import path from 'node:path';
import {vitePluginBreakpoints} from './.vite/vitePluginBreakpoints.js';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    resolve: {
        alias: {
            '$lib': path.resolve('./resources/js')
        }
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true
        }),
        vitePluginBreakpoints(
            path.resolve('./resources/css/tokens/breakpoints.css'),
            path.resolve('./resources/js/components/util/breakpoints/breakpoints.ts')
        ),
        svelte()
    ],
    server: {
        cors: true,
        port: 3000,
        origin: process.env.DOCKER_PROJECT_PROTOCOL && process.env.DOCKER_PROJECT_HOST ?
            `${process.env.DOCKER_PROJECT_PROTOCOL}://${process.env.DOCKER_PROJECT_HOST}:3000`
            : undefined,
        host: true,
        strictPort: false,
        https: process.env.DOCKER_PROJECT_PROTOCOL === 'https' ? {
            key: '/etc/ssl/certs/custom/key.pem',
            cert: '/etc/ssl/certs/custom/cert.pem'
        } : undefined
    }

});
