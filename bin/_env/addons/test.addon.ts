import type {AddonEntrypoint} from '@/loadAddons.ts';

export const addon: AddonEntrypoint = async (context) => ({
    commands: async (program) => {
        program
            .command('test:cli')
            .description('runs the test suite for the dev cli using docker compose')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action(async (_, command) => {
                await context.docker.executeComposeCommand(
                    ['-f', 'docker-compose.ci.yml', 'run', '--build', 'tests', ...command.args],
                    {cwd: context.paths.envDir, interactive: true}
                );
            });
    }
});
