import type {HawkiCorePlugin, HawkiPluginContext} from '$lib/kernel/plugins/types.js';
import type {MigrationRegistrar} from '$lib/kernel/migrations/migrationRegistrar.js';
import type {StoreRegistrar} from '$lib/kernel/stores/storeRegistrar.js';
import {AiHandleStore} from '$plugins/core/stores/AiHandleStore.svelte.js';
import {AiModelStore} from '$plugins/core/stores/AiModelStore.svelte.js';
import {AiToolStore} from '$plugins/core/stores/AiToolStore.svelte.js';
import {SystemPromptStore} from '$plugins/core/stores/SystemPromptStore.svelte.js';
import {ThemeStore} from '$plugins/core/stores/ThemeStore.svelte.js';
import {KeychainStore} from '$plugins/core/stores/KeychainStore.svelte.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiPlugins {
        core: CorePlugin;
    }
}

export default class CorePlugin implements HawkiCorePlugin {
    readonly name = 'core';

    async init(ctx: HawkiPluginContext) {
        console.log('core plugin initialized', ctx);
    }

    public migrations(registrar: MigrationRegistrar): void | Promise<void> {
        registrar.addFromModules(import.meta.glob('$lib/plugins/core/migrations/**/*.ts', {eager: false}));
    }

    public stores({add}: StoreRegistrar): void | Promise<void> {
        add(new KeychainStore());
        add(new AiHandleStore());
        add(new AiModelStore());
        add(new AiToolStore());
        add(new SystemPromptStore());
        add(new ThemeStore());
    }
}
