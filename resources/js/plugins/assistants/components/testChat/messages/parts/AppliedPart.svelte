<script lang="ts">
    import CheckmarkCircle02Icon from '$lib/components/ui/icons/iconset/CheckmarkCircle02Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    /** Labels of the builder fields the guide filled in with this reply. */
    let {labels}: { labels: string[] } = $props();

    const {__} = useTranslator();
</script>

<p class="applied">
    <span class="icon" aria-hidden="true"><CheckmarkCircle02Icon size="1em"/></span>
    <span>{__('assistants.builder.guide.applied')}: {labels.join(', ')}</span>
</p>

<style>
    /* Icon sits on the first line even when the field list wraps. */
    .applied {
        display: flex;
        align-items: flex-start;
        gap: var(--space-1_5);
        margin: 0;
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }

    .icon {
        display: inline-flex;
        flex-shrink: 0;
        height: calc(1em * var(--line-height-normal));
        align-items: center;
        font-size: var(--font-size-sm);
    }
</style>
