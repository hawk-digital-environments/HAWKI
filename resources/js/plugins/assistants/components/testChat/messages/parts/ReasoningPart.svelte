<script lang="ts">
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import ShimmerText from '$lib/components/ui/shimmer-text/ShimmerText.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    /** `active` while the model is still reasoning in this part. */
    let {text, active = false}: { text: string; active?: boolean } = $props();
    let open = $state(false);

    const {__} = useTranslator();
</script>

<div class="reasoning">
    <button type="button" class="toggle" aria-expanded={open} onclick={() => (open = !open)}>
        <span class="chevron" class:open aria-hidden="true"><ArrowRight01Icon size="1em"/></span>
        <ShimmerText {active}>{active ? __('chat.page.thinking') : __('chat.page.reasoning')}</ShimmerText>
    </button>
    {#if open}
        <p class="text">{text}</p>
    {/if}
</div>

<style>
    .toggle {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        padding: 0;
        border: none;
        background: none;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        cursor: pointer;
    }

    .toggle:hover {
        color: var(--color-text);
    }

    .chevron {
        display: inline-flex;
        font-size: var(--font-size-sm);
        transition: transform var(--duration-fast);
    }

    .chevron.open {
        transform: rotate(90deg);
    }

    .text {
        margin: var(--space-1) 0 0;
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
</style>
