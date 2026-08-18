<!--
  @component Page component rendered for the chat module's `/` route (see
  `ChatModule.ts`). This is currently a placeholder stub — the real chat UI
  (message list + `ChatComposer` snippet) still lives in the legacy,
  non-Svelte-routed part of the app during the ongoing routing migration.

  Usage: never imported directly — it is resolved lazily by
  `ChatModule.routes()` via `registrar.lazyRoute('/', ...)` and rendered by
  the kernel's route renderer when the chat route is navigated to.
-->
<script lang="ts">
    // TODO(docs): This page currently just renders a placeholder heading. Once the
    // real chat view (message history + ChatComposer) is migrated into the
    // Svelte routing tree, its props/behaviour should be documented here —
    // please confirm what this page is expected to render before that migration
    // lands, so the doc block above doesn't become stale.

    import {Link} from '@hawk-hhg/hawki-svelte-components';
    import {useRouter} from '@hawk-hhg/hawki-svelte-components';

    /**
     * The kernel's route renderer instantiates page components without passing
     * any props, so this interface is intentionally empty. Add route-derived
     * props here (and document them individually) once the real chat view lands.
     */
    interface Props {
    }

    const {}: Props = $props();

    const {p, isActive} = useRouter();
</script>
<h1>Chat index!</h1>
<Link href={p('chat.conversation', {id: '123'})}>Go to conversation 123</Link>
<hr/>
<Link href={{name: 'chat.conversation', params: {id: '456'}}}>Go to conversation 456</Link>
<hr/>
<Link href={p('/')}>Go to home</Link>
