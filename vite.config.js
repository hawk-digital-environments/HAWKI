import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';


export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        cors: true,
        port: 3000,
        origin: `${process.env.DOCKER_PROJECT_PROTOCOL}://${process.env.DOCKER_PROJECT_HOST}`,
        host: true,
        strictPort: false
    }
});
