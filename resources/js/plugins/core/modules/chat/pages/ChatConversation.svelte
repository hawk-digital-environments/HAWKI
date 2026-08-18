<!--
  @component Page component rendered for the chat module's `/room/:id`
  conversation route (see `ChatModule.ts`, route name `chat.conversation`).
  Placeholder stub — the real conversation view still lives in the legacy,
  non-Svelte-routed part of the app during the ongoing routing migration.

  Usage: never imported directly — resolved lazily by `ChatModule.routes()`
  via `registrar.lazyRoute('/room/:id', ...)`. Receives the matched `id`
  param via its `params` prop.
-->
<script module lang="ts">
    import {z} from 'zod';
    import {configurePage} from '$lib/components/ui/routing/logistics/routeConfig.js';

    export const config = configurePage({
        paramSchema: z.object({id: z.string()}),
        loadData: async () => {
            return {
                foo: await new Promise<number>((resolve) => {
                    setTimeout(() => resolve(123), 4000);
                })
            };
        }
    });
</script>
<script lang="ts">
    import Link from '$lib/components/util/link/Link.svelte';
    import type {RouteProps} from '$lib/components/ui/routing/logistics/routeProps.js';

    const {params, data}: RouteProps<typeof config> = $props();

    $inspect(data);
</script>
<h1>Single chat!</h1>
<p>This is a single conversation with {params.id}</p>
<Link href="@chat.index">Go to chat index</Link>
<hr/>
<Link href="/new">Go back to main page</Link>
