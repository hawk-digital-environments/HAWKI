import type { AddonEntrypoint } from '@/loadAddons.ts';
export const addon: AddonEntrypoint = async (context) => ({
    commands: async (program) => {
        program
            .command('ci:phpunit')
            .description('runs the phpunit test suite for laravel as service')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action(async (_, command) => {
                await context.docker.executeComposeCommand(
                    ['-f', 'docker-compose.ci.yml','run', '--build', '--rm', 'test', ...command.args],
                    { cwd: context.paths.projectDir, interactive: true }
                );
            });
    }
});
