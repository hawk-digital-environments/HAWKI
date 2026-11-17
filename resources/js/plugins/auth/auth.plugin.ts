/** Registers the SPA authentication pages and auth resource schema. */
import type {HawkiCorePlugin} from '$lib/kernel/plugins/types.js';
import type {RouteRegistrar} from '$lib/components/ui/routing/index.js';
import type {ResourceSchemaRegistrar} from '$lib/kernel/resources/resourceSchemaRegistrar.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiPlugins {
        auth: AuthPlugin;
    }
}

export default class AuthPlugin implements HawkiCorePlugin {
    readonly name = 'auth';

    public resourceSchemas(registrar: ResourceSchemaRegistrar): void {
        registrar.addFromModules(
            import.meta.glob('$lib/plugins/auth/schemas/resources/*.schema.ts', {eager: true})
        );
    }

    public routes(registrar: RouteRegistrar): void {
        registrar.lazyRoute('/auth/login', async () => import('$plugins/auth/pages/Login.svelte'), {name: 'auth.login', meta: {access: 'public', chrome: 'none'}});
        registrar.lazyRoute('/auth/register', async () => import('$plugins/auth/pages/Register.svelte'), {name: 'auth.register', meta: {access: 'public', chrome: 'none'}});
        registrar.lazyRoute('/auth/handshake', async () => import('$plugins/auth/pages/Handshake.svelte'), {name: 'auth.handshake', meta: {access: 'server-session', chrome: 'none'}});
        registrar.lazyRoute('/auth/inconsistent', async () => import('$plugins/auth/pages/Inconsistent.svelte'), {name: 'auth.inconsistent', meta: {access: 'server-session', chrome: 'none'}});
    }
}
