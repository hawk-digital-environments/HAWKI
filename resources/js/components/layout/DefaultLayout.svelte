<script lang="ts">
    import type {Snippet} from 'svelte';
    import Toaster from '$lib/components/ui/toast/Toaster.svelte';
    import {createToastContext} from '$lib/components/ui/toast/ToastContext.svelte';

    interface Props {
        children: Snippet;
    }

    const {
        children
    }: Props = $props();

    createToastContext();
</script>

<Toaster/>

{@render children?.()}
