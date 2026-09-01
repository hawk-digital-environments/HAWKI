/**
 * The `core` plugin — HAWKI's first-party feature bundle.
 *
 * This is the only plugin shipped with HAWKI by default and it wires together the
 * foundational, always-on features of the frontend:
 *   - **Stores**: registers the core reactive stores ({@link KeychainStore},
 *     {@link AiHandleStore}, {@link AiModelStore}, {@link AiToolStore},
 *     {@link SystemPromptStore}, {@link ThemeStore}, {@link ExperimentsStore}) with
 *     the kernel's `StoreExtension` so their `loadData` runs on the bootstrapper's main stage.
 *   - **Migrations**: lazy-globs the `plugins/core/migrations/` directory and
 *     hands the loaders to the `MigrationExtension` for the keychain/encryption
 *     format upgrades.
 *   - **Modules**: registers {@link ChatModule}, the chat feature's own bundle of
 *     routes/stores/etc.
 *   - **Routes**: declares the root `/` route, lazily loading `pages/Index.svelte`.
 *
 * Augments `HawkiPlugins` via declaration merging so `app.plugins.core` is typed as a
 * `CorePlugin`. Auto-discovered by the kernel via `import.meta.glob` for
 * `*.plugin.ts` files.
 */
import type {HawkiCorePlugin} from '$lib/kernel/plugins/types.js';
import type {MigrationRegistrar} from '$lib/kernel/migrations/migrationRegistrar.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {StoreRegistrar} from '$lib/kernel/stores/storeRegistrar.js';
import {AiHandleStore} from '$plugins/core/stores/AiHandleStore.svelte.js';
import {AnnouncementStore} from '$plugins/core/stores/AnnouncementStore.svelte.js';
import {AiModelStore} from '$plugins/core/stores/AiModelStore.svelte.js';
import {AiToolStore} from '$plugins/core/stores/AiToolStore.svelte.js';
import {SystemPromptStore} from '$plugins/core/stores/SystemPromptStore.svelte.js';
import {ThemeStore} from '$plugins/core/stores/ThemeStore.svelte.js';
import {ExperimentsStore} from '$plugins/core/stores/ExperimentsStore.svelte.js';
import {KeychainStore} from '$plugins/core/stores/KeychainStore.svelte.js';
import {ChatStore} from '$plugins/core/stores/ChatStore.svelte.js';
import {ModelFavoritesStore} from '$plugins/core/stores/ModelFavoritesStore.svelte.js';
import {ModelSelectionStore} from '$plugins/core/stores/ModelSelectionStore.svelte.js';
import {registerChatSearch} from '$plugins/core/modules/chat/search.js';
import type {ModuleRegistrar} from '$lib/kernel/modules/moduleRegistrar.js';
import {ChatModule} from '$plugins/core/modules/chat/ChatModule.js';
import type {RouteRegistrar} from '$lib/components/ui/routing/index.js';
import type {ResourceSchemaRegistrar} from '$lib/kernel/resources/resourceSchemaRegistrar.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiPlugins {
        core: CorePlugin;
    }
}

export default class CorePlugin implements HawkiCorePlugin {
    readonly name = 'core';

    public resourceSchemas(registrar: ResourceSchemaRegistrar): void {
        registrar.addFromModules(
            import.meta.glob('$lib/plugins/core/schemas/resources/*.schema.ts', {eager: true})
        );
    }

    public migrations(registrar: MigrationRegistrar): void | Promise<void> {
        registrar.addFromModules(import.meta.glob('$lib/plugins/core/migrations/**/*.ts', {eager: false}));
    }

    public modules({add}: ModuleRegistrar): void | Promise<void> {
        add(new ChatModule());
    }

    public routes(registrar: RouteRegistrar): void | Promise<void> {
        registrar.lazyRoute('/', async () => import('$plugins/core/pages/Index.svelte'));
        registrar.lazyRoute('/announcements', async () => import('$plugins/core/pages/Announcements.svelte'), {name: 'announcements.index'});
        registrar.lazyRoute('/models', async () => import('$plugins/core/pages/Models.svelte'), {name: 'models.index'});
    }

    public stores({add}: StoreRegistrar): void | Promise<void> {
        add(new KeychainStore());
        add(new AnnouncementStore());
        add(new AiHandleStore());
        add(new AiModelStore());
        add(new AiToolStore());
        add(new SystemPromptStore());
        add(new ThemeStore());
        add(new ExperimentsStore());
        add(new ModelFavoritesStore());
        add(new ModelSelectionStore());
        add(new ChatStore());
    }

    public ready(app: HawkiApp): void {
        registerChatSearch(app);
    }
}
