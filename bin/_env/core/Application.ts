import {CommonUi} from './CommonUi.ts';
import {EventBus} from './EventBus.ts';
import {createPackageJson} from './PackageInfo.ts';
import {createPaths} from './Paths.ts';
import {createEnvFile} from './env/EnvFile.ts';
import {createPlatform} from './Platform.ts';
import {loadAddons} from './loadAddons.ts';
import {createContext, extendContext} from './Context.ts';
import {CommandFailedError} from './executeCommand.ts';

export class Application {
    public async run(args: string[]) {
        const events = new EventBus();
        const ui = new CommonUi(events);
        try {
            const context = createContext(events, ui);
            extendContext(context, 'paths', createPaths());
            extendContext(context, 'platform', createPlatform());
            extendContext(context, 'pkg', createPackageJson(context.paths));
            await loadAddons(context);
            extendContext(context, 'env', await createEnvFile(context));

            const {program, pkg} = context;

            program
                .name(pkg.name)
                .description(pkg.description)
                .version(pkg.version)
                .showSuggestionAfterError(true)
                .helpCommand(true)
                .addHelpText('beforeAll', () => ui.renderHelpIntro(pkg))
                .configureHelp({
                    sortSubcommands: true
                })
            ;

            await events.trigger('commands:define', {program});

            if (args.length < 3) {
                program.help();
                process.exit(0);
            }

            await program.parseAsync(args);
        } catch (error) {
            let castError: Error;
            if (!(error instanceof Error)) {
                castError = new Error(String(error));
            } else {
                castError = error;
            }
            await events.trigger('error:before', {error: castError});
            console.error(ui.renderError(castError));
            await events.trigger('error:after', {error: castError});

            // A failing command reports its own exit code as ours, so `bin/env test`
            // is readable by CI; anything else is a CLI failure and gets a plain 1.
            // `process.exitCode` (not `process.exit()`) so pending stdout writes
            // still flush before the process exits naturally.
            process.exitCode = castError instanceof CommandFailedError ? castError.code : 1;
        }
    }
}
