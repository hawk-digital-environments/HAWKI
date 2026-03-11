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
        origin: `${process.env.DOCKER_PROJECT_PROTOCOL}://${process.env.DOCKER_PROJECT_HOST}:3000`,
        host: true,
        strictPort: false,
        https: process.env.DOCKER_PROJECT_PROTOCOL === 'https' ? {
            key: '/etc/ssl/certs/custom/key.pem',
            cert: '/etc/ssl/certs/custom/cert.pem'
        } : false
    }

});
