import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';

/**
 * @type {import('vite').ServerOptions}
 */
let serverConfig;

// When we are running in a docker container, we need to adjust the server config
// (the DOCKER_PROJECT_DOMAIN is set in the docker-compose.yml file, and acts as a marker in this case)
if (process.env.DOCKER_PROJECT_DOMAIN) {
    serverConfig = {
        cors: true,
        port: 8000,
        origin: 'http://localhost:8000',
        host: true,
        strictPort: false
    };

    // If the project was installed `bin/env install` we need to set the server config to use https
    if (process.env.DOCKER_PROJECT_INSTALLED === 'true') {
        serverConfig.origin = 'https://' + process.env.DOCKER_PROJECT_DOMAIN + ':8000';
        serverConfig.https = {
            key: '/var/www/certs/key.pem',
            cert: '/var/www/certs/cert.pem'
        };
    }
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: serverConfig
});
