import type {HawkiModule} from '$lib/kernel/modules/types.js';
import type {RouteRegistrar} from '$lib/kernel/routing/RouteRegistrar.js';

export class ChatModule implements HawkiModule {
    readonly name = 'chat';

    public routes(registrar: RouteRegistrar): void | Promise<void> {
        registrar.lazyRoute('/', async () => (await import('./pages/ChatIndex.svelte')).default);
    }
}
