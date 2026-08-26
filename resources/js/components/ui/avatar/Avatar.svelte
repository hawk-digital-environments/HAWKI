<!--
  @component User avatar primitive. Displays an image when available and falls
  back to initials, while callers provide the context-specific accessible name.
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';

    interface Props extends HTMLAttributes<HTMLSpanElement> {
        /** Image URL. When omitted or it fails to load, initials are shown. */
        src?: string;
        /** Full name; used for the alt text and to derive initials. */
        name?: string;
        /** Accessible name supplied by the translated calling context. */
        label: string;
        /** Pixel diameter of the avatar. */
        size?: number;
        /** Color treatment for the initials fallback. */
        variant?: 'accent' | 'neutral';
    }

    const {
        src,
        name = '',
        label,
        size = 32,
        variant = 'accent',
        class: className,
        style,
        ...rest
    }: Props = $props();

    let failed = $state(false);

    $effect(() => {
        src;
        failed = false;
    });

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
    {...mergeProps(
        rest,
        {
            class: ['avatar', variant === 'neutral' && 'neutral', className],
            style,
            role: 'img',
            'aria-label': label
        },
        {
            style: {
                width: `${size}px`,
                height: `${size}px`,
                fontSize: `${Math.round(size * 0.45)}px`
            }
        }
    )}
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
        background: var(--color-accent-100);
        color: var(--color-accent-text);
        font-weight: 600;
        line-height: 1;
        user-select: none;
    }

    .avatar.neutral {
        background: var(--color-active-surface);
        color: var(--color-active-text);
    }

    .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
</style>
