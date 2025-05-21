import type {AddonEntrypoint} from '@/loadAddons.ts';
import {EnvFileMigrator} from '@/env/EnvFileMigrator.ts';

export const addon: AddonEntrypoint = async (context) => ({
    commands: async (program) => {
        program
            .command('env:reset')
            .description('Resets your current .env file back to the default definition')
            .action(async () => {
                await ((new EnvFileMigrator(context.events, context.paths)).setForced(true).migrate(context.env));
            });
    }
});
