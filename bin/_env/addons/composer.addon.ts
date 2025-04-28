import type {AddonEntrypoint} from '@/loadAddons.ts';
import {ComposerContext} from './composer/ComposerContext.ts';

export const addon: AddonEntrypoint = async (context) => ({
    context: async () => ({
        composer: new ComposerContext(context)
    }),
    commands: async (program) => {
        program
            .command('composer')
            .description('runs a certain composer command for the project')
            .allowExcessArguments(true)
            .allowUnknownOption(true)
            .action((_, command) => context.composer.exec(command.args));
    }
});
