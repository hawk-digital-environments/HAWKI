import type { HawkiModule } from '$lib/kernel/modules/types.js';
import type { HawkiApp } from '$lib/kernel/HawkiApp.js';
import type { Translator } from '$lib/kernel/localization/translator.js';
import type { RouteRegistrar } from '$lib/components/ui/routing/index.js';
import Settings01Icon from '$lib/components/ui/icons/iconset/Settings01Icon.svelte';
import AdminSidebar from './components/AdminSidebar.svelte';
import { registerAdminRoutes } from './routes.js';

export class AdminModule implements HawkiModule {
    readonly name = 'admin';
    title(translate: Translator['translate']) {
        return translate('admin.title');
    }
    icon() {
        return Settings01Icon;
    }
    sidebar() {
        return AdminSidebar;
    }
    visible(app: HawkiApp) {
        return app.can('admin.access');
    }

    routes(registrar: RouteRegistrar) {
        registerAdminRoutes(registrar);
    }
}
