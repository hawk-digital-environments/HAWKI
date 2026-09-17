<script lang="ts">
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import { useStore } from '$lib/app/hooks/useStore.svelte.js';
    let {
        name,
        light,
        dark,
        size = 32
    }: { name: string; light?: string | null; dark?: string | null; size?: number } = $props();
    const theme = useStore('theme');
    let failed = $state(false);
    const src = $derived((theme.isDark ? (dark ?? light) : light) || undefined);
    $effect(() => {
        src;
        failed = false;
    });
</script>

{#if src && !failed}
    <img
        {src}
        alt=""
        width={size}
        height={size}
        onerror={() => (failed = true)}
    />
{:else}
    <Avatar
        {name}
        label={name}
        {size}
        variant="neutral"
        aria-hidden="true"
    />
{/if}

<style>
    img {
        object-fit: contain;
        flex-shrink: 0;
    }
</style>
