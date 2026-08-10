import {ConfigurationExtension} from '$lib/kernel/config/ConfigurationExtension.js';
import {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import {createToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
import {createAppContext} from '$lib/components/app/AppContext.svelte.js';
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
            new StoreExtension()
        ]
    ));

    // @deprecated This is only here to support the "AppContext" through multiple Svelte apps on the same page.
    // It will be removed once we have a single-page app and can use Svelte contexts instead.
    bootstrapper.onLateStage(() => {
        // @todo move this into the app component once we have a single-page app
        createAppContext();
        createToastContext();

        // @todo remove this once we have a single-page app, and can use Svelte contexts instead of global variables.
        // Inject the "LegacySharedContent" snippet into the page (as first child of the body)
        const legacySharedContentSnippet = document.createElement('svelte-snippet');
        legacySharedContentSnippet.setAttribute('type', 'LegacySharedContent');
        document.body.insertBefore(legacySharedContentSnippet, document.body.firstChild);
    });

    // As a last step, we wait until the DOM is fully loaded
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
