import {ConfigurationAspect} from '$lib/kernel/config/ConfigurationAspect.js';
import {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import {createToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
import {createAppContext} from '$lib/components/app/AppContext.svelte.js';
import {ModuleAspect} from '$lib/kernel/modules/ModuleAspect.js';
import {createApp} from '$lib/kernel/HawkiApp.js';
import {MigrationAspect} from '$lib/kernel/migrations/MigrationAspect.js';
import {ClientAspect} from '$lib/kernel/client/ClientAspect.js';
import {ResourceSchemaAspect} from '$lib/kernel/resources/ResourceSchemaAspect.js';
import {PluginAspect} from '$lib/kernel/plugins/PluginAspect.js';
import {LocalizationAspect} from '$lib/kernel/localization/LocalizationAspect.svelte.js';
import {RoutingAspect} from '$lib/kernel/routing/RoutingAspect.js';
import {createDefaultRouteRenderer} from '$lib/kernel/routing/routeRenderer.js';
import {registerSvelteSnippetLoader} from '$lib/legacy/svelteSnippetLoader.js';
import {provideLegacyGlobals, runLegacyWaitUntilBootstrapQueue, runLegacyWaitUntilReadyQueue, setHawkiApp} from '$lib/legacy/legacy.js';
import {StoreAspect} from '$lib/kernel/stores/StoreAspect.js';

provideLegacyGlobals();

(async () => {
    const bootstrapper = new Bootstrapper();

    setHawkiApp(await createApp(
        bootstrapper,
        [
            new ResourceSchemaAspect(),
            new ClientAspect(),
            new PluginAspect(),
            new ConfigurationAspect(),
            new MigrationAspect(),
            new LocalizationAspect(),
            new ModuleAspect(),
            new RoutingAspect(createDefaultRouteRenderer()),
            new StoreAspect()
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
    bootstrapper.onFinalizationStage(registerSvelteSnippetLoader);

    await runLegacyWaitUntilBootstrapQueue(bootstrapper);
    await bootstrapper.run();
    await runLegacyWaitUntilReadyQueue();
})();
