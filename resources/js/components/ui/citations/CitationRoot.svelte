<script lang="ts">
    import {createCitationContext} from '$lib/components/ui/citations/CitationContext.js';
    import type {Snippet} from 'svelte';

    interface Props {
        children: Snippet;
    }

    const {children}: Props = $props();

    createCitationContext();
</script>

{@render children?.()}
