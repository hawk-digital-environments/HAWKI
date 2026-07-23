<!--
@component Since we are currently working with "snippets" and not a real single page app,
there is no way to have a single instance of ui elements (namely the Toaster) that is shared across all snippets
on the same page. Therefore we have this special snippet that is included at the top of the page,
which uses the shared {@link AppContext} to render the Toaster component.
With this approach, we can have a single instance of the Toaster that is shared across all snippets on the same page. -->
<script lang="ts">
    import Toaster from '$lib/components/ui/toast/Toaster.svelte';
    import {useAppContext} from '$lib/components/app/AppContext.svelte.js';

    useAppContext().legacySharedContentLoaded = true;
</script>

<Toaster/>
