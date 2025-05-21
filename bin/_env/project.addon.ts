import {defineEnv} from './project/defineEnv.ts';
import {defineUi} from './project/defineUi.ts';
import type {AddonEntrypoint} from '@/loadAddons.ts';
import {confirm} from '@inquirer/prompts';

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
                if (await confirm({
                    message: 'This will open everything to run a dev environment. You can exit it by pressing Ctrl+B and then D. Do you want to continue?',
                    default: true
                })) {
                    await context.docker.executeCommandInService('app', ['/usr/bin/app/dev.command.sh'], {interactive: true});
                }
            });

        program
            .command('clear-cache')
            .description('clears the laravel caches and rebuilds the cache')
            .action(async () => {
                await context.docker.executeCommandInService('app', ['php', 'hawki', 'clear-cache'], {foreground: true});
            });

        program
            .command('setup-models')
            .description('starts a wizard to setup the AI models for the project')
            .action(async () => {
                await context.docker.executeCommandInService('app', ['php', 'hawki', 'setup-models'], {interactive: true});
            });

        program
            .command('hawki')
            .description('executes the hawki cli tool inside the app container')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action(async (options, command) => {
                await context.docker.executeCommandInService('app', ['php', 'hawki', ...command.args], {interactive: true});
            });
    }
});
