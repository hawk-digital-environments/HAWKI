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
            // Disable the APP_URL comment in the env file, as it is now handled by the DOCKER_PROJECT_HOST AND DOCKER_PROJECT_PROTOCOL variables.
            envFile.comment('APP_URL');
        });
    },
    commands: async (program) => {
        program
            .command('artisan')
            .description('runs a certain artisan command for the project')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .helpOption(false)
            .action(async (options, command) => {
                await context.docker.executeCommandInService('app', ['gosu', 'www-data', 'php', 'artisan', ...command.args], {interactive: true});
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
                await context.docker.executeCommandInService('app', ['/usr/bin/dev.command.sh'], {interactive: true});
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

        program
            .command('start-docker-production-test')
            .option('--no-pull', 'do not pull the latest docker images, use the ones available locally')
            .description('Creates a running system of the `_docker_production` directory for testing purposes')
            .action(async (options) => startDockerProductionTest(context, options.pull));

        program
            .command('php')
            .description('runs a custom php command inside the app container')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .helpOption(false)
            .action(async (options, command) => {
                await context.docker.executeCommandInService('app', ['php', ...command.args], {interactive: true});
            });

        program
            .command('node')
            .description('runs a custom node command inside the node container')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .helpOption(false)
            .action(async (options, command) => {
                await context.docker.executeCommandInService('node', ['node', ...command.args], {interactive: true});
            });

        program
            .command('helper-code')
            .description('runs the helper code generator to generate helper code for your IDE')
            .action(async () => {
                await context.docker.executeCommandInService('app', ['gosu', 'www-data', 'php', 'artisan', 'dev:helper:repository'], {interactive: true});
            });

        // =============================================================================
        // Test commands
        // =============================================================================

        const tests = program
            .command('test')
            .description('a list of commands to help you with testing tasks');

        const phpTests = tests
            .command('php')
            .description('a list of commands to help you with php testing tasks');

        phpTests
            .command('stan')
            .description('runs phpstan static analysis inside the app container')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .helpOption(false)
            .action(async (options, command) => {
                await context.composer.exec(['run', 'test:stan', ...command.args]);
            });

        phpTests
            .command('unit')
            .description('runs phpunit tests inside the app container')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .option('--coverage, -c', 'generate a code coverage report')
            .helpOption(false)
            .action(async (options, command) => {
                const script = options.coverage ? 'test:unit:coverage' : 'test:unit';
                await context.composer.exec(['run', script, ...command.args]);
            });

        phpTests
            .command('feature')
            .description('runs php feature tests inside the app container')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .option('--coverage, -c', 'generate a code coverage report')
            .helpOption(false)
            .action(async (options, command) => {
                const script = options.coverage ? 'test:feature:coverage' : 'test:feature';
                await context.composer.exec(['run', script, ...command.args]);
            });

        phpTests
            .command('all')
            .description('runs all tests (phpstan and phpunit) inside the app container')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .helpOption(false)
            .action(async (options, command) => {
                await context.composer.exec(['run', 'test:all']);
            });

        tests
            .command('all')
            .description('runs all tests (php and js) inside the app container')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .helpOption(false)
            .action(async (options, command) => {
                await context.composer.exec(['run', 'test:all']);
                await context.docker.executeCommandInService('node', ['npm', 'test'], {interactive: true});
            });

        // =============================================================================
        // Code Style commands
        // =============================================================================

        const style = program
            .command('style')
            .description('a list of commands to help you with code style tasks');

        style
            .command('php')
            .description('runs php-cs-fixer to automatically fix code style issues')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .helpOption(false)
            .action(async (options, command) => {
                await context.composer.exec(['run', 'php-cs-fixer', ...command.args]);
            });

        style
            .command('js')
            .description('runs prettier to automatically fix code style issues in js/ts/css files')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .helpOption(false)
            .action(async (options, command) => {
                await context.docker.executeCommandInService('node', ['npm', 'run', 'prettier', '--', ...command.args], {interactive: true});
            });

        // =============================================================================
        // Documentation commands
        // =============================================================================
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
    }
});
