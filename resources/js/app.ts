/**
 * Frontend application entry point.
 *
 * This is the single script loaded by the page (see the bundler entry config)
 * that boots the whole HAWKI SPA/legacy-hybrid frontend. It has two jobs:
 *
 * 1. Assemble the {@link HawkiApp} by calling `createApp()` with the ordered
 *    list of {@link HawkiAppExtension}s that make up the application. Each
 *    extension's `provideProperties()` merges its own surface onto the app
 *    instance (see `HawkiApp.ts`), and each extension augments
 *    `HawkiAppExtensions` via TypeScript declaration merging so the merged
 *    surface stays fully typed (e.g. `app.config`, `app.stores`,
 *    `app.restApi`, ...). The order of the array matters: extensions run
 *    their `init()` hook in this order, and later extensions can rely on
 *    properties provided by earlier ones (via `app.getOrFail(...)`).
 * 2. Kick off the {@link Bootstrapper}, which runs app startup in six ordered
 *    stages (preparation → migration → early → main → late → finalization).
 *    `bootstrapper.run()` is awaited so that any code relying on
 *    `window.hawkiIsBooting` or the legacy wait-queues behaves correctly.
 *
 * This file also guards against being executed twice (e.g. if the bootstrap
 * `<script>` tag is accidentally included more than once on the same page)
 * via the `window.hawkiIsBooting` flag, and wires up legacy-bridge concerns
 * (`provideLegacyGlobals`, `setHawkiApp`, the legacy wait-until queues) that
 * exist only to support old, non-extension code during the ongoing
 * refactor to a single-page Svelte app.
 *
 * You should not normally need to change this file when adding a feature —
 * add a new {@link HawkiAppExtension} (or a plugin, see `PluginExtension`)
 * instead. Only touch this file when you need to add a fundamentally new,
 * app-wide extension, or change the relative ordering of existing ones.
 */
import {ConfigurationExtension} from '$lib/kernel/config/ConfigurationExtension.svelte.js';
import {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import {ModuleExtension} from '$lib/kernel/modules/ModuleExtension.js';
import {createApp} from '$lib/kernel/HawkiApp.js';
import {MigrationExtension} from '$lib/kernel/migrations/MigrationExtension.js';
import {ClientExtension} from '$lib/kernel/client/ClientExtension.svelte.js';
import {ResourceSchemaExtension} from '$lib/kernel/resources/ResourceSchemaExtension.js';
import {PluginExtension} from '$lib/kernel/plugins/PluginExtension.js';
import {HookExtension} from '$lib/kernel/hooks/HookExtension.js';
import {LocalizationExtension} from '$lib/kernel/localization/LocalizationExtension.svelte.js';
import {RoutingExtension} from '$lib/kernel/routing/RoutingExtension.js';
import {provideLegacyGlobals, runLegacyWaitUntilBootstrapQueue, runLegacyWaitUntilReadyQueue, setHawkiApp} from '$lib/legacy/legacy.js';
import {StoreExtension} from '$lib/kernel/stores/StoreExtension.js';
import {SnippetExtension} from '$lib/legacy/SnippetExtension.js';
import {LegacyToastExtension} from '$lib/legacy/LegacyToastExtension.js';
import {ShellExtension} from '$lib/kernel/shell/ShellExtension.svelte.js';
import {StorageExtension} from '$lib/kernel/storage/StorageExtension.js';
import {passkeySessionExtension} from '$lib/kernel/keychain/PasskeySessionExtension.svelte.js';
import {EventExtension} from '$lib/kernel/events/EventExtension.js';

declare global {
    interface Window {
        /** Guard flag so a second inclusion of this bootstrap script fails loudly instead of double-booting the app. */
        hawkiIsBooting: boolean;
    }
}

if (window.hawkiIsBooting) {
    throw new Error('Hawki is already booting. This may indicate that the bootstrap script has been included multiple times.');
}
window.hawkiIsBooting = true;

provideLegacyGlobals();

(async () => {
    const bootstrapper = new Bootstrapper();
    const eventExtension = new EventExtension();
    const events = eventExtension.events;

    setHawkiApp(await createApp(
        bootstrapper,
        [
            eventExtension,
            new ResourceSchemaExtension(),
            passkeySessionExtension,
            new ClientExtension(events),
            new PluginExtension(),
            new ConfigurationExtension(),
            new HookExtension(),
            new MigrationExtension(),
            new LocalizationExtension(),
            new ModuleExtension(),
            new RoutingExtension(),
            new StorageExtension(),
            new StoreExtension(),
            new ShellExtension(),
            new SnippetExtension(),
            new LegacyToastExtension()
        ]
    ));

    await runLegacyWaitUntilBootstrapQueue(bootstrapper);
    await bootstrapper.run();
    await runLegacyWaitUntilReadyQueue();
})();
