import type {AddonEntrypoint} from '@/loadAddons.ts';

export const addon: AddonEntrypoint = async (context) => ({
    commands: async (program) => {
        program
            .command('npm')
            .description('runs a certain npm command for the project')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action(async (options, command) => {
                await context.docker.executeCommandInService('node', ['npm', ...command.args], {interactive: true});
            });
    }
});
