import type {EventBus} from './EventBus.ts';
import type {EnvFileDefinition} from './env/EnvFileMigrator.ts';
import type {Command} from 'commander';
import {type Context, extendContext} from './Context.ts';
import {glob} from 'glob';
import fs from 'node:fs';
import path from 'node:path';
import type {EnvFile} from './env/EnvFile.ts';

export interface AddonUiConfig {
    /**
     * Shown above the help text (if omitted bin/env in ascii art will be shown)
     */
    helpHeader?: string;
    /**
     * Additional help text shown below the help header.
     */
    helpDescription?: string;
    /**
     * Shown above the error text (if omitted bin/dead in ascii art will be shown)
     */
    errorHeader?: string;
}

export interface AddonConfig {
    /**
     * Can be used to hook into the event bus and modify the events
     * This is a fairly advanced feature and should only be used if you know what you are doing
     * @param events
     */
    events?: (events: EventBus) => Promise<void>;

    /**
     * Can be used to define a schema for your .env file. This can be used to automatically configure your
     * project based on what you need or allow a certain amount of freedom to the user.
     *
     * @param env
     */
    env?: (definition: EnvFileDefinition, envFile: EnvFile) => Promise<void>;

    /**
     * Can be used to add custom commands to the cli. This is a good way to add custom functionality to your project.
     * @param program The underlying commander program you can use to add commands to
     * @param context The context of the bin/env program. This gives you access to everything there is.
     */
    commands?: (program: Command) => Promise<void>;

    /**
     * Can be used to modify some of the user interface options of the cli.
     * With this you can make the cli more "yours" if you like.
     */
    ui?: () => Promise<AddonUiConfig>;

    /**
     * Allows your addon to register extensions for the config object.
     * The value should be a unique "key" that is used to identify your context facette, the value should be the config object you want to register.
     */
    context?: () => Promise<Partial<Record<keyof Context, object | (() => object)>>>;
}

export type AddonEntrypoint = (context: Context) => Promise<AddonConfig>

export async function loadAddons(context: Context) {
    const {paths, events} = context;
    const addons = await findAddons(...paths.addonPaths);

    const configs: AddonConfig[] = [];
    for (const addon of addons) {
        const config = await addon(context);
        configs.push(config);
    }

    const foreachConfig = async (callback: (config: AddonConfig) => Promise<void>) => {
        for (const config of configs) {
            await callback(config);
        }
    };

    await foreachConfig(config => applyContextExtension(context, config));
    await foreachConfig(config => applyEvents(events, config));
    await foreachConfig(config => applyEnvDefinition(events, config));
    await foreachConfig(config => applyUiCustomizing(events, config));
    await foreachConfig(config => applyCommands(events, config));
}

async function findAddons(...sourceDir: string[]): Promise<AddonEntrypoint[]> {
    const addonFiles = new Set<string>();
    for (const src of sourceDir) {
        if (!fs.existsSync(src) || !fs.statSync(src).isDirectory()) {
            continue;
        }
        for (const file of await glob(path.join(src, '*.addon.ts'))) {
            addonFiles.add(file);
        }
    }

    const addons: AddonEntrypoint[] = [];
    for (const addonFile of addonFiles) {
        try {
            const addon = await import(addonFile);
            if (addon) {
                if (typeof addon.default === 'function') {
                    addons.push(addon.default);
                } else if (typeof addon.addon === 'function') {
                    addons.push(addon.addon);
                }
            }
        } catch (e) {
            console.error(`Failed to load addon from ${addonFile}`, e);
        }
    }

    return addons;
}

async function applyContextExtension(context: Context, config: AddonConfig) {
    if (typeof config.context !== 'function') {
        return;
    }

    const extension = await config.context();

    for (const [key, value] of Object.entries(extension)) {
        extendContext(context, key as keyof Context, value);
    }
}

async function applyEvents(events: EventBus, config: AddonConfig) {
    if (typeof config.events !== 'function') {
        return;
    }

    await config.events(events);
}

async function applyEnvDefinition(events: EventBus, config: AddonConfig) {
    if (typeof config.env !== 'function') {
        return;
    }

    events.on('env:define', async ({definition, envFile}) => {
        await config.env(definition, envFile);
    });
}

async function applyUiCustomizing(events: EventBus, config: AddonConfig) {
    if (typeof config.ui !== 'function') {
        return;
    }

    const ui = await config.ui();

    if (ui.helpHeader) {
        events.onSync('ui:filter:helpHeader', v => v.value = ui.helpHeader);
    }
    if (ui.helpDescription) {
        events.onSync('ui:filter:helpDescription', v => v.value = ui.helpDescription);
    }
    if (ui.errorHeader) {
        events.onSync('ui:filter:errorHeader', v => v.value = ui.errorHeader);
    }
}

async function applyCommands(events: EventBus, config: AddonConfig) {
    if (typeof config.commands !== 'function') {
        return;
    }

    events.on('commands:define', async ({program}) => {
        await config.commands(program);
    });
}
