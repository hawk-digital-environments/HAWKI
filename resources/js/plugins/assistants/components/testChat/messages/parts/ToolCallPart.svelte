<script lang="ts">
    import Wrench01Icon from '$lib/components/ui/icons/iconset/Wrench01Icon.svelte';
    let {name}: { name: string } = $props();
</script>

<div class="tool-call">
    <span class="icon"><Wrench01Icon size="1em" /></span>
    <span class="name">{name}</span>
</div>

<style>
    .tool-call {
        display: flex;
        align-items: center;
        gap: .35rem;
        flex-wrap: wrap;
        font-size: var(--font-size-xs);
        padding: .15rem 0;
        color: var(--color-accent-text);
    }
    .icon {
        font-size: var(--font-size-base);
    }
    .name {
        font-weight: 600;
    }
</style>
