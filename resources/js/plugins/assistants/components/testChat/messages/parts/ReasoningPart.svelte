<script lang="ts">
    import ArrowDown01Icon from '$lib/components/ui/icons/iconset/ArrowDown01Icon.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    let {text} = $props<{ text: string }>();
    let open = $state(false);
</script>

<div class="reasoning" class:open>
    <button type="button" class="toggle" onclick={() => (open = !open)}>
        <span class="icon">{#if open}<ArrowDown01Icon size="1em" />{:else}<ArrowRight01Icon size="1em" />{/if}</span>
        <span class="label">Thinking</span>
    </button>
    {#if open}
        <div class="text">{text}</div>
    {/if}
</div>

<style>
    .reasoning {
        border-left: 2px solid var(--color-text-muted);
        margin: .25rem 0;
        padding-left: .5rem;
    }
    .toggle {
        display: flex;
        align-items: center;
        gap: .15rem;
        cursor: pointer;
        background: none;
        border: none;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        padding: .15rem 0;
    }
    .toggle .icon {
        font-size: var(--font-size-base);
    }
    .text {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        white-space: pre-wrap;
        padding: .15rem 0;
        word-break: break-word;
    }
</style>
