import {ConfigurationExtension} from '$lib/kernel/config/ConfigurationExtension.js';
import {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import {ModuleExtension} from '$lib/kernel/modules/ModuleExtension.js';
import {createApp} from '$lib/kernel/HawkiApp.js';
import {MigrationExtension} from '$lib/kernel/migrations/MigrationExtension.js';
import {ClientExtension} from '$lib/kernel/client/ClientExtension.js';
import {ResourceSchemaExtension} from '$lib/kernel/resources/ResourceSchemaExtension.js';
import {PluginExtension} from '$lib/kernel/plugins/PluginExtension.js';
import {LocalizationExtension} from '$lib/kernel/localization/LocalizationExtension.svelte.js';
import {RoutingExtension} from '$lib/kernel/routing/RoutingExtension.js';
import {createDefaultRouteRenderer} from '$lib/kernel/routing/routeRenderer.js';
import {provideLegacyGlobals, runLegacyWaitUntilBootstrapQueue, runLegacyWaitUntilReadyQueue, setHawkiApp} from '$lib/legacy/legacy.js';
import {StoreExtension} from '$lib/kernel/stores/StoreExtension.js';
import {SnippetExtension} from '$lib/legacy/SnippetExtension.js';
import {LegacyToastExtension} from '$lib/legacy/LegacyToastExtension.js';

declare global {
    interface Window {
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

    setHawkiApp(await createApp(
        bootstrapper,
        [
            new ResourceSchemaExtension(),
            new ClientExtension(),
            new PluginExtension(),
            new ConfigurationExtension(),
            new MigrationExtension(),
            new LocalizationExtension(),
            new ModuleExtension(),
            new RoutingExtension(createDefaultRouteRenderer()),
            new StoreExtension(),
            new SnippetExtension(),
            new LegacyToastExtension()
        ]
    ));

    // @deprecated This is only here to support the "AppContext" through multiple Svelte apps on the same page.
    // It will be removed once we have a single-page app and can use Svelte contexts instead.
    bootstrapper.onLateStage(() => {
        // @todo remove this once we have a single-page app, and can use Svelte contexts instead of global variables.
        // Inject the "LegacySharedContent" snippet into the page (as first child of the body)
        const legacySharedContentSnippet = document.createElement('svelte-snippet');
        legacySharedContentSnippet.setAttribute('type', 'LegacySharedContent');
        document.body.insertBefore(legacySharedContentSnippet, document.body.firstChild);
    });

    bootstrapper.onFinalizationStage(() => new Promise(resolve => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                resolve();
            });
        } else {
            resolve();
        }
    }));

    await runLegacyWaitUntilBootstrapQueue(bootstrapper);
    await bootstrapper.run();
    await runLegacyWaitUntilReadyQueue();
})();
