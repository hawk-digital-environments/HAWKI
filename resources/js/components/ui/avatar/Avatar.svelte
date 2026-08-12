<script lang="ts">
    interface Props {
        /** Image URL. When omitted or it fails to load, initials are shown. */
        src?: string;
        /** Full name; used for the alt text and to derive initials. */
        name?: string;
        /** Pixel diameter of the avatar. */
        size?: number;
        /** Color treatment for the initials fallback. */
        variant?: 'accent' | 'neutral';
    }

    const {src, name = '', size = 32, variant = 'accent'}: Props = $props();

    let failed = $state(false);

    const initials = $derived(
        name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase() ?? '')
            .join('')
    );

    const showImage = $derived(Boolean(src) && !failed);
</script>

<span
    class="avatar"
    class:neutral={variant === 'neutral'}
    style:width="{size}px"
    style:height="{size}px"
    style:font-size="{Math.round(size * 0.45)}px"
    role="img"
    aria-label={name || 'Avatar'}
>
    {#if showImage}
        <img src={src} alt={name} onerror={() => (failed = true)} />
    {:else}
        <span class="initials" aria-hidden="true">{initials || '?'}</span>
    {/if}
</span>

<style>
    .avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border-radius: var(--corner-full);
        overflow: hidden;
        background: var(--color-accent-400);
        color: var(--color-on-interactive);
        font-weight: 600;
        line-height: 1;
        user-select: none;
    }

    .avatar.neutral {
        background: var(--color-active-surface);
        color: var(--color-active-text);
    }

    /* Accent stays blue in dark mode, but --color-on-interactive flips to dark
       ink — match the active sidebar label instead. Only the accent fill needs
       this; the neutral one carries its own ink. */
    :global(html.darkMode) .avatar:not(.neutral) {
        color: var(--color-active-text);
    }

    .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
</style>
