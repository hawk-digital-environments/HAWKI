import type {AddonEntrypoint} from '@/loadAddons.ts';
import {DockerContext} from './docker/DockerContext.ts';
import {Installer} from './docker/installer/Installer.ts';
import {defineDockerEnv} from './docker/defineDockerEnv.ts';
import type {Command} from 'commander';
import {exec} from 'node:child_process';

export const addon: AddonEntrypoint = async (context) => ({
    context: async () => {
        return {
            docker: () => new DockerContext(context),
            installer: () => new Installer(context)
        };
    },
    env: defineDockerEnv(context.paths),
    events: async events => {
        // Create hook to ensure loopback IP is registered before docker:up
        events.on('docker:up:before', async () => {
            if (context.docker.isInstalled) {
                await context.installer.ensureLoopbackIp();
            }
        });
    },
    commands: async (program) => {
        program.hook('preSubcommand', async (_, subcommand) => {
            // Stupid workaround to ensure context.docker.getComposeCommandHelp() works correctly.
            if (subcommand.name().startsWith('docker:')) {
                await context.docker.getComposeExecutable();
            }
        });

        program
            .command('docker:install')
            .alias('install')
            .description('Installs the project on your device; sets up a unique url, ip address, hosts entry and ssl certificate')
            .action(() => context.installer.install());

        program
            .command('docker:up')
            .alias('up')
            .description('Starts the docker containers (docker compose up)')
            .addHelpText('after', () => context.docker.getComposeCommandHelp('up'))
            .option('-f, --attach', 'follows the output of your app like docker compose does', false)
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action((options, command) => context.docker.up({
                follow: options.attach,
                args: command.args
            }).then());

        program
            .command('docker:stop')
            .alias('stop')
            .description('Stops the docker containers (docker compose stop)')
            .addHelpText('after', () => context.docker.getComposeCommandHelp('stop'))
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action((_, command) => context.docker.stop(command.args));

        program
            .command('docker:down')
            .alias('down')
            .description('Stops and removes the docker containers (docker compose down)')
            .addHelpText('after', () => context.docker.getComposeCommandHelp('down'))
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action((_, command) => context.docker.down(command.args));

        program
            .command('docker:restart')
            .alias('restart')
            .description('Restarts the docker containers (docker compose restart), all arguments and flags are passed to the "up" command')
            .option('--force', 'instead of stopping the containers, a "down" and "up" is performed', false)
            .option('-f, --attach', 'follows the output of your app (after the restart) like docker compose does', false)
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action((options, command) => context.docker.restart({
                follow: options.attach,
                args: command.args
            }).then());

        program
            .command('docker:clean')
            .alias('clean')
            .description('Stops the project and removes all containers, networks, volumes and images')
            .option('-y, --yes', 'skips the confirmation prompt', false)
            .action((options) => context.docker.clean(options.yes));

        program
            .command('docker:logs')
            .alias('logs')
            .description('Shows the logs of the docker containers (docker compose logs) - by default only the logs of the main container are shown, use "--all" to show all logs')
            .option('-a, --all', 'shows all logs, instead only the logs of the main container', false)
            .option('-f, --follow', 'follows the output of the logs', false)
            .addHelpText('after', () => context.docker.getComposeCommandHelp('logs'))
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action(async (options, command: Command) => {
                return context.docker.logs({all: options.all, args: [(options.follow ? '-f' : ''), ...command.args]});
            });

        program
            .command('docker:ssh')
            .alias('ssh')
            .description('Opens a shell in a docker container (docker compose exec)')
            .argument('[service]', 'the service to open the shell in')
            .option('-c, --cmd <cmd>', 'the command to execute in the container')
            .action((service, options) => context.docker.ssh(service, options.cmd));

        program
            .command('docker:ps')
            .alias('ps')
            .description('Shows the docker containers of the project (docker compose ps)')
            .addHelpText('after', () => context.docker.getComposeCommandHelp('ps'))
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action((_, command) => context.docker.ps(command.args));

        program
            .command('docker:open')
            .alias('open')
            .description('opens the current project in your browser.')
            .action(() => {
                exec(`open ${context.docker.projectHost}`);
            });

        program
            .command('docker:build:prod')
            .description('Builds the production image for the project')
            .option('--tag <tag>', 'Tag to use for the image', 'digitalenvironments/hawki:latest')
            .action(async (options) => {
                await context.docker.executeDockerCommand(
                    ['build', '--target', 'app_prod', '--pull', '-t', options.tag, context.paths.projectDir],
                    {foreground: true}
                );
            });
    }
});
