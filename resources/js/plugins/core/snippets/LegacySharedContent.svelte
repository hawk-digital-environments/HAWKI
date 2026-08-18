<!--
@component Since we are currently working with "snippets" and not a real single page app,
there is no way to have a single instance of ui elements (namely the Toaster) that is shared across all snippets
on the same page. Therefore we have this special snippet that is included at the top of the page and mounts the
single shared `Toaster`. Every snippet's `useToastContext()` calls resolve to the same `ToastContext` instance via
the shared `useApp().toast` bridge (see `ToastContext.svelte.ts`), so any snippet can push a toast that ends up
here regardless of which `<svelte-snippet>` it renders in. -->
<script lang="ts">
    import {Toaster} from '@hawk-hhg/hawki-svelte-components';
</script>

<Toaster/>
