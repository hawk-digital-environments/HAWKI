<script lang="ts">
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import { useStore } from '$lib/app/hooks/useStore.svelte.js';
    import { trimSvgIcon } from '$lib/utils/trimSvgIcon.js';
    let {
        name,
        light,
        dark,
        size = 32,
        bare = false
    }: {
        name: string;
        light?: string | null;
        dark?: string | null;
        size?: number;
        /** Without a logo, render just the provider's initial instead of a round avatar —
         *  for hosts that already draw their own tile around the icon. */
        bare?: boolean;
    } = $props();
    const theme = useStore('theme');
    let failed = $state(false);
    const raw = $derived((theme.isDark ? (dark ?? light) : light) || undefined);
    // Cropped to the artwork, so logos with and without built-in padding match in size.
    const src = $derived(raw && trimSvgIcon(raw));
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
{:else if bare}
    <span class="initial" style:width="{size}px" style:height="{size}px" style:font-size="{size * 0.75}px" aria-hidden="true">
        {name.trim().charAt(0).toUpperCase()}
    </span>
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

    .initial {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        font-weight: var(--font-weight-medium, 500);
        line-height: 1;
        color: var(--color-text-muted);
    }
</style>
