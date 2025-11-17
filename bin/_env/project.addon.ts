import {defineEnv} from './project/defineEnv.ts';
import {defineUi} from './project/defineUi.ts';
import type {AddonEntrypoint} from '@/loadAddons.ts';
import {execSync} from 'node:child_process';
import path from 'path';
import fs from 'fs';
import {startDockerProductionTest} from './project/startDockerProductionTest.js';

export const addon: AddonEntrypoint = async (context) => ({
    ui: defineUi,
    env: defineEnv,
    events: async (events) => {
        events.on('installer:envFile:filter', async ({envFile}) => {
            // Automatically rewrite the APP_URL
            envFile.set('APP_URL', 'https://' + envFile.get('DOCKER_PROJECT_DOMAIN'));
            // Reconfigure reverb for ssl
            envFile
                .set('VITE_REVERB_HOST', envFile.get('DOCKER_PROJECT_DOMAIN'))
                .set('VITE_REVERB_PORT', '443')
                .set('VITE_REVERB_SCHEME', 'https');
        });
    },
    commands: async (program) => {
        program
            .command('artisan')
            .description('runs a certain artisan command for the project')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action(async (options, command) => {
                await context.docker.executeCommandInService('app', ['php', 'artisan', ...command.args], {interactive: true});
            });

        program
            .command('queue')
            .description('starts the laravel queue and runs it in the current shell')
            .action(async () => {
                await context.composer.exec(['run', 'queue']);
            });

        program
            .command('websocket')
            .alias('reverb')
            .description('starts the laravel websocket server (through reverb) and runs it in the current shell')
            .action(async () => {
                await context.composer.exec(['run', 'websocket']);
            });

        program
            .command('dev')
            .description('starts both the queue and the websocket server in the current shell')
            .action(async () => {
                await context.docker.executeCommandInService('app', ['/usr/bin/app/dev.command.sh'], {interactive: true});
            });

        program
            .command('clear-cache')
            .description('clears the laravel caches and rebuilds the cache')
            .action(async () => {
                await context.docker.executeCommandInService('app', ['php', 'hawki', 'clear-cache'], {foreground: true});
            });

        program
            .command('hawki')
            .description('executes the hawki cli tool inside the app container')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action(async (options, command) => {
                await context.docker.executeCommandInService('app', ['php', 'hawki', ...command.args], {interactive: true});
            });

        const docsDir = path.join(context.paths.projectDir, '_documentation.build');

        const docs = program
            .command('docs')
            .description('a list of commands to help you with documentation tasks');

        const installDocsIfNeeded = () => {
            // Automatically run npm install if node_modules does not exist
            if (!fs.existsSync(path.join(docsDir, 'node_modules'))) {
                console.log('node_modules not found, running npm install...');
                execSync('npm install', {
                    cwd: docsDir,
                    stdio: 'inherit'
                });
            }
        };

        docs
            .command('npm')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .description('runs an npm command inside the documentation directory')
            .action(async (options, command) => {
                execSync(`npm ${command.args.join(' ')}`, {
                    cwd: docsDir,
                    stdio: 'inherit'
                });
            });

        docs
            .command('build')
            .description('builds the documentation')
            .action(async () => {
                installDocsIfNeeded();
                execSync('npm run build', {
                    cwd: docsDir,
                    stdio: 'inherit'
                });
            });

        docs
            .command('watch')
            .description('watches and serves the documentation with live reload')
            .action(async () => {
                installDocsIfNeeded();
                execSync('npm run start', {
                    cwd: docsDir,
                    stdio: 'inherit'
                });
            });

        program
            .command('start-docker-production-test')
            .description('Creates a running system of the `_docker_production` directory for testing purposes')
            .action(async () => startDockerProductionTest(context));
    }
});
