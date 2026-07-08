import {defineConfig, type PluginOption} from 'vite';
import {svelte} from '@sveltejs/vite-plugin-svelte';
import path from 'node:path';
import {vitePluginBreakpoints} from './.vite/vitePluginBreakpoints.js';
import laravel from 'laravel-vite-plugin';
import {vitePluginHugeicons} from './.vite/vitePluginHugeicons.js';
import monacoEditorPlugin from 'vite-plugin-monaco-editor-esm';
import {vitePluginCssLayers} from './.vite/vitePluginCssLayers.js';

export default defineConfig({
    resolve: {
        alias: [
            {
                find: '$lib',
                replacement: path.resolve('./resources/js')
            },
            {
                find: /^@antv\/infographic$/,
                replacement: path.resolve('./node_modules/@antv/infographic/dist/infographic.min.js')
            }
        ]

    },
    optimizeDeps: {
        include: ['mermaid'],
        exclude: ['stream-monaco', 'stream-markdown-parser']
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
        vitePluginHugeicons(
            path.resolve('./resources/js/components/ui/icons/iconset')
        ),
        monacoEditorPlugin({
            languageWorkers: [
                'editorWorkerService',
                'typescript',
                'css',
                'html',
                'json'
            ],
            customDistPath(_root, buildOutDir) {
                return path.resolve(buildOutDir, 'monacoeditorwork');
            }
        }) as unknown as PluginOption,
        vitePluginCssLayers([
            {path: 'node_modules/katex/dist/katex.min.css', layer: 'components'},
            {path: 'node_modules/monaco-editor/min/vs/editor/editor.main.css', layer: 'components'},
            {path: 'node_modules/markstream-svelte/dist/index.css', layer: 'components'}
        ]),
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
