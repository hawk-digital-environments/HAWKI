import type {HawkiCorePlugin, HawkiPluginContextWithConfig} from '$lib/kernel/plugins/types.js';
import type {MigrationRegistrar} from '$lib/kernel/migrations/migrationRegistrar.js';
import type {StoreRegistrar} from '$lib/kernel/stores/storeRegistrar.js';
import {AiHandleStore} from '$plugins/core/stores/AiHandleStore.svelte.js';
import {AiModelStore} from '$plugins/core/stores/AiModelStore.svelte.js';
import {AiToolStore} from '$plugins/core/stores/AiToolStore.svelte.js';
import {SystemPromptStore} from '$plugins/core/stores/SystemPromptStore.svelte.js';
import {ThemeStore} from '$plugins/core/stores/ThemeStore.svelte.js';
import {KeychainStore} from '$plugins/core/stores/KeychainStore.svelte.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {Component} from 'svelte';
import {HTMLSvelteSnippetElement} from '$lib/legacy/svelteSnippetLoader.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiPlugins {
        core: CorePlugin;
    }
}

export default class CorePlugin implements HawkiCorePlugin {
    readonly name = 'core';

    public boot(app: HawkiApp, ctx: HawkiPluginContextWithConfig): void | Promise<void> {
        const glob = import.meta.glob('$lib/plugins/core/snippets/**/*.svelte', {eager: true});
        for (const [path, module] of Object.entries(glob)) {
            const snippetName = path.split('/').pop()?.replace('.svelte', '');
            if (!snippetName) {
                console.warn(`Failed to register snippet from path '${path}': Could not determine snippet name.`);
                continue;
            }
            app.snippets.register(snippetName, (module as { default: Component }).default);
        }

        ctx.bootstrapper.onStagePassed('finalization', () => {
            customElements.define('svelte-snippet', HTMLSvelteSnippetElement);
        });
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
