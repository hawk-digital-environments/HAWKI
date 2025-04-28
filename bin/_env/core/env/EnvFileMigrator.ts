import type {EnvFile} from './EnvFile.ts';
import type {Paths} from '../Paths.ts';
import type {EventBus} from '../EventBus.ts';
import type {EnvFileLine} from './EnvFileLine.ts';
import {type EnvFileState, loadEnvFileState} from './EnvFileState.ts';
import fs from 'node:fs';
import chalk from 'chalk';
import {confirm, input, select} from '@inquirer/prompts';
import {updateEnvFileHash} from './util.ts';

export interface EnvVariableOptions {
    /**
     * The message to display when asking for the value of the variable.
     */
    message?: string;

    /**
     * Optional help text to display when asking for the value of the variable.
     * This is useful to provide additional context or information about the variable.
     */
    help?: string;

    /**
     * The default value to use if no value could be resolved.
     * If not set, the user will be prompted for a value if needed.
     */
    default?: string | ((templateValue: string | undefined) => Promise<string>)

    /**
     * Allows you to determine if the variable needs to be updated.
     * If omitted, empty lines, and those containing the same value as the ".env.template" file will be updated.
     * Also, if the migration is "forced", all lines will be updated, meaning this option will be ignored.
     * @param value
     * @param templateValue
     */
    needsUpdate?: (value: string | undefined, templateValue: string | undefined) => Promise<boolean>;

    /**
     * If you want more control over the editor of this variable you can provide this function.
     * I would suggest using one of the "@inquirer/prompts" functions, like "input", "select" or "confirm",
     * which you can freely use here.
     *
     * @param key
     * @param value
     * @param options
     */
    editor?: (key: string, value: string | undefined, options: EnvVariableOptions) => Promise<string | undefined>;

    /**
     * By default, variables are enforced to be non-empty.
     * If you want to allow empty values, set this to true.
     */
    allowEmpty?: boolean;

    /**
     * We will automatically uncomment existing lines that define the variable.
     * If you don't want this, set this to false.
     */
    uncomment?: boolean;

    /**
     * A function to validate the user input (forwarded to the inquirer prompt).
     * @param input
     */
    validate?: (input: string) => boolean | string | Promise<boolean | string>,
}

export class EnvFileDefinition {
    private _variables: Map<string, EnvVariableOptions>;

    public constructor(variables: Map<string, EnvVariableOptions>) {
        this._variables = variables;
    }

    public define(key: string, options: EnvVariableOptions): this {
        this._variables.set(key, options);
        return this;
    }
}

export class EnvFileMigrator {
    private readonly _events: EventBus;
    private readonly _paths: Paths;
    private _forced: boolean = false;
    private _options: Map<string, EnvVariableOptions>;
    private _resolvedValues: Map<string, string | undefined>;
    private _commentedLines: Set<EnvFileLine> | undefined;
    private _templateState: EnvFileState | undefined;

    public constructor(events: EventBus, paths: Paths) {
        this._events = events;
        this._paths = paths;
    }

    /**
     * If set to true, all variables will be updated, even if they are not empty or not the same as in the template file.
     * @param forced
     */
    public setForced(forced: boolean): this {
        this._forced = forced;
        return this;
    }

    public async migrate(envFile: EnvFile) {
        await this._loadOptions(envFile);
        await this._loadTemplateState();
        await this._collectValues(envFile);
        await this._askForMissing();

        while (true) {
            if (await this._showSummary(envFile)) {
                break;
            }

            const target = await this._askForEditTarget();
            await this._editValue(target);
        }

        this._applyValues(envFile);
    }

    private async _loadOptions(envFile: EnvFile) {
        this._options = new Map<string, EnvVariableOptions>();
        const definition = new EnvFileDefinition(this._options);

        await this._events.trigger('env:define', {definition, envFile});
    }

    private async _loadTemplateState() {
        this._templateState = undefined;
        if (!fs.existsSync(this._paths.envFileTemplatePath)) {
            return;
        }

        this._templateState = loadEnvFileState(this._paths.envFileTemplatePath);
    }

    private async _collectValues(envFile: EnvFile): Promise<void> {
        this._resolvedValues = new Map<string, string | undefined>();
        this._commentedLines = new Set<EnvFileLine>();
        const state = envFile.state;
        const templateState = this._templateState;

        for (const [key, options] of this._options.entries()) {
            const line = state.getFirstLineForKey(key);
            const templateLine = templateState?.getFirstLineForKey(key);

            const resolveDefault = async (fallback?: string | undefined): Promise<string | undefined> => {
                if (options.default) {
                    if (typeof options.default === 'function') {
                        return await options.default(templateLine?.value);
                    }
                    return options.default + '';
                }

                return fallback;
            };

            if (!line) {
                if (options.uncomment !== false) {
                    const commentedLine = state.getFirstLineForCommentedKey(key);
                    if (commentedLine) {
                        commentedLine.uncomment();
                        const commentedValue = commentedLine.value;
                        commentedLine.comment();
                        this._commentedLines.add(commentedLine);

                        this._resolvedValues.set(key, await resolveDefault(commentedValue));
                        continue;
                    }
                }

                this._resolvedValues.set(key, await resolveDefault());
                continue;
            }

            let needsUpdate: boolean = options.allowEmpty ? false : line.value === '';
            if (this._forced) {
                needsUpdate = true;
            } else if (typeof options.needsUpdate === 'function') {
                needsUpdate = await options.needsUpdate(line?.value, templateLine?.value);
            } else if (templateLine?.value === line?.value) {
                needsUpdate = true;
            }

            if (needsUpdate) {
                this._resolvedValues.set(key, await resolveDefault());
            } else {
                this._resolvedValues.set(key, line.value);
            }
        }
    }

    private async _askForMissing(): Promise<void> {
        for (const [key, options] of this._options.entries()) {
            if (this._resolvedValues.get(key) === undefined && options.default === undefined) {
                if (options.help) {
                    console.log(chalk.yellow('❓ ' + options.help));
                }
                const value = await input({
                    message: options.message ?? 'Value of "' + key + '"',
                    required: options.allowEmpty !== true,
                    validate: options.validate
                });

                this._resolvedValues.set(key, value);
            }
        }
    }

    private async _showSummary(envFile: EnvFile): Promise<boolean> {
        let foundChange = false;

        const entries = Array.from(this._resolvedValues.entries()).sort((a, b) => {
            const aKey = a[0].toLowerCase();
            const bKey = b[0].toLowerCase();
            if (aKey < bKey) {
                return -1;
            }
            if (aKey > bKey) {
                return 1;
            }
            return 0;
        });

        const summaryValues = entries.map(([key, value]) => {
            const changed = envFile.get(key) !== value;
            foundChange ||= changed;
            const oldValue = envFile.get(key) ?? 'missing or commented';
            return `- ${chalk.bold(key)}: ${chalk.green(value)}` + (changed ? ' ' + chalk.bold('🔧') + ' was ' + chalk.yellow(oldValue) : '');
        }).join('\n');

        // If no changes were found, we can skip the confirmation
        // If we are forced, we need to show the confirmation anyway
        if (!foundChange) {
            if (this._forced) {
                console.log(chalk.green('Your .env file is awesome as it is; just like you 🤗'));
            }
            return true;
        }

        console.log(`
🔍 ${chalk.bold(' Summary of environment variables')}

I've analyzed your .env file and adopted it to match the variable schema.
Below is a list of all detected variables and their values. Any values that I've
edited or added are marked with a 🔧.

If everything looks correct, press ${chalk.bold('Enter')} to continue.
To modify any values, press ${chalk.bold('n')} and ${chalk.bold('Enter')}, then select which variable you'd like to edit.

Collected environment variables:
----------------------------------
${summaryValues}
`);


        return await confirm({
            message: 'Does this look good to you 👀? Answer "no" to edit the values.',
            default: true
        });
    }

    private async _askForEditTarget(): Promise<string> {
        return select({
            message: 'Which variable do you want to edit?',
            choices: Array.from(this._resolvedValues.keys()).sort()
        });
    }

    private async _editValue(key: string): Promise<void> {
        const currentValue = this._resolvedValues.get(key);
        const options = this._options.get(key);
        if (options.help) {
            console.log(chalk.yellow('❓ ' + options.help));
        }
        let value = '';
        if (typeof options.editor === 'function') {
            value = await options.editor(key, currentValue, options);
        } else {
            value = await input({
                message: options.message ?? 'Value of "' + key + '"',
                default: currentValue,
                required: options.allowEmpty !== true,
                validate: options.validate
            });
        }

        this._resolvedValues.set(key, value);
    }

    private _applyValues(envFile: EnvFile): void {
        for (const line of this._commentedLines) {
            line.uncomment();
        }

        for (const [key, value] of this._resolvedValues.entries()) {
            envFile.set(key, value);
        }
        envFile.write();
        updateEnvFileHash(this._paths);
    }
}
