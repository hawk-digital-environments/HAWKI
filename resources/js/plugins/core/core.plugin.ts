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
import type {StoreRegistrar} from '$lib/kernel/stores/storeRegistrar.js';
import type {HookRegistrar} from '$lib/kernel/hooks/hookRegistrar.js';
import Chat01Icon from '$lib/components/ui/icons/iconset/Chat01Icon.svelte';
import ChatSidebar from '$plugins/core/modules/chat/components/ChatSidebar.svelte';
import NewChatButton from '$plugins/core/modules/chat/components/NewChatButton.svelte';
import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';
import {AiHandleStore} from '$plugins/core/stores/AiHandleStore.svelte.js';
import {AiModelStore} from '$plugins/core/stores/AiModelStore.svelte.js';
import {AiToolStore} from '$plugins/core/stores/AiToolStore.svelte.js';
import {SystemPromptStore} from '$plugins/core/stores/SystemPromptStore.svelte.js';
import {ThemeStore} from '$plugins/core/stores/ThemeStore.svelte.js';
import {ComposerPinStore} from '$plugins/core/stores/ComposerPinStore.svelte.js';
import {ExperimentsStore} from '$plugins/core/stores/ExperimentsStore.svelte.js';
import {KeychainStore} from '$plugins/core/stores/KeychainStore.svelte.js';
import {ChatStore} from '$plugins/core/stores/ChatStore.svelte.js';
import {ModelFavoritesStore} from '$plugins/core/stores/ModelFavoritesStore.svelte.js';
import {ModelSelectionStore} from '$plugins/core/stores/ModelSelectionStore.svelte.js';
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

    /**
     * Contributes the chat feature's sidebar UI via the sidebar hooks: one
     * module selector entry and one sidebar panel, both active while a chat
     * route is shown. Handlers are pure filters over the collected entries
     * (see `app/ui/sidebarHooks.ts`).
     */
    public hooks(registrar: HookRegistrar): void {
        const chatGroup = getModuleRouteGroupName('core', 'chat');

        registrar.add('moduleSelectorEntries', (entries, ctx) => [...entries, {
            id: 'core:chat',
            label: ctx.translate('chat.module.title'),
            icon: Chat01Icon,
            onSelect: (selectCtx) => {
                void selectCtx.router.goToRoute('chat.index');
            },
            active: ctx.router.isRouteActive(chatGroup)
        }]);

        registrar.add('sidebarSlots', (slots, ctx) => [...slots,
            {
                id: 'core:chat-sidebar',
                position: 'panel',
                component: ChatSidebar,
                active: ctx.router.isRouteActive(chatGroup)
            },
            {
                id: 'core:new-chat',
                position: 'action',
                component: NewChatButton,
                active: ctx.router.isRouteActive(chatGroup)
            }
        ]);
    }

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
    }

    public stores({add}: StoreRegistrar): void | Promise<void> {
        add(new KeychainStore());
        add(new AiHandleStore());
        add(new AiModelStore());
        add(new AiToolStore());
        add(new SystemPromptStore());
        add(new ThemeStore());
        add(new ComposerPinStore());
        add(new ExperimentsStore());
        add(new ModelFavoritesStore());
        add(new ModelSelectionStore());
        add(new ChatStore());
    }
}
