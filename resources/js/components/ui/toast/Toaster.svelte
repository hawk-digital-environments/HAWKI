<!--
  @component Fixed-position pile of toast notifications driven by
  {@link toastStore}. Mount once (e.g. near the chat input); toasts pushed from
  anywhere stack bottom-centre Sonner-style — newest in front, older ones
  peeking behind with a slight scale/offset — and auto-dismiss.
-->
<script lang="ts">
    import {backOut, cubicIn} from 'svelte/easing';
    import {type ToastVariant, useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    import CheckmarkCircle01Icon from '$lib/components/ui/icons/iconset/CheckmarkCircle01Icon.svelte';
    import InformationCircleIcon from '$lib/components/ui/icons/iconset/InformationCircleIcon.svelte';

    const toastContext = useToastContext();

    const icons = {
        error: AlertCircleIcon,
        success: CheckmarkCircle01Icon,
        info: InformationCircleIcon
    } satisfies Record<ToastVariant, unknown>;

    /** How many toasts are visible in the collapsed pile before older ones fade out. */
    const MAX_VISIBLE = 3;

    let expanded = $state(false);

    const TOAST_GAP = 8;

    /** Measured heights keyed by toast id. */
    let heights = $state<Record<string, number>>({});

    /**
     * Distance from the bottom the toast at `index` should sit when expanded.
     * Toasts are ordered newest-first (index 0 = front), so we accumulate the
     * heights of all toasts below (higher index = older = lower position).
     */
    function expandedOffset(index: number): number {
        let offset = 0;
        for (let j = index + 1; j < toastContext.toasts.length; j++) {
            offset += (heights[toastContext.toasts[j].id] ?? 52) + TOAST_GAP;
        }
        return offset;
    }

    /**
     * Total height of the expanded stack, used to size the invisible hit area
     * so the gaps between toasts stay inside the hover region (otherwise
     * crossing a gap fires mouseleave and collapses the stack).
     */
    const stackHeight = $derived(
        toastContext.toasts.reduce(
            (sum, toast) => sum + (heights[toast.id] ?? 52) + TOAST_GAP,
            0
        ) - TOAST_GAP
    );

    /**
     * Height of the front toast (depth 0 = last in the list, newest). In the
     * collapsed pile every toast is forced to this height so the deeper toasts
     * peek out by a uniform sliver instead of being swallowed whole behind a
     * tall front toast — their own content is hidden, so without this they
     * collapse to a stub and disappear behind it.
     */
    const frontHeight = $derived(
        heights[toastContext.toasts.at(-1)?.id ?? ''] ?? 52
    );

    /** Springy entrance: scale + lift with an overshoot, fading in. */
    function springIn(_node: Element, {duration = 450} = {}) {
        return {
            duration,
            easing: backOut,
            css: (t: number) =>
                `opacity: ${Math.min(1, t * 1.5)}; transform: translateX(-50%) translateY(${(1 - t) * -24}px) scale(${0.85 + t * 0.15});`
        };
    }

    /** Quick, non-springy exit so dismissals feel snappy. */
    function springOut(_node: Element, {duration = 180} = {}) {
        return {
            duration,
            easing: cubicIn,
            css: (t: number) => `opacity: ${t}; transform: translateX(-50%) scale(${0.9 + t * 0.1});`
        };
    }
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -->
<div
    class="toaster"
    class:toaster--expanded={expanded}
    style="--front-height: {frontHeight}px;"
    aria-live="polite"
    onmouseenter={() => { expanded = true; toastContext.pause(); }}
    onmouseleave={() => { expanded = false; toastContext.resume(); }}
    onfocusin={() => { expanded = true; toastContext.pause(); }}
    onfocusout={() => { expanded = false; toastContext.resume(); }}
>
    {#if expanded}
        <!-- Invisible backdrop spanning the whole expanded stack so the cursor
             never crosses an empty gap and collapses the pile. -->
        <div class="toaster-hit-area" style="height: {stackHeight}px;"></div>
    {/if}
    {#each toastContext.toasts as toast, i (toast.id)}
        {@const Icon = icons[toast.variant]}
        {@const depth = toastContext.toasts.length - 1 - i}
        <div
            class="toast toast--{toast.variant}"
            class:toast--hidden={!expanded && depth >= MAX_VISIBLE}
            role="status"
            style="--depth: {depth}; --expanded-offset: {expandedOffset(i)}px; z-index: {100 - depth};"
            in:springIn
            out:springOut
            bind:clientHeight={heights[toast.id]}
        >
            <Icon class="toast-icon" size={18}/>
            <span class="toast-message">{toast.message}</span>
        </div>
    {/each}
</div>

<style>
    .toaster {
        /* Zero-width anchor pinned to the viewport centre; toasts centre
           themselves on it via translateX(-50%), so each card hugs its
           content instead of filling a fixed width. */
        position: fixed;
        inset-block-start: var(--space-4, 1rem);
        inset-inline-start: 50%;
        z-index: 100;
        pointer-events: none;

        &:not(.toaster--expanded) .toast:not(:last-of-type) {
            .toast-message {
                display: none;
            }

            :global(.toast-icon) {
                opacity: 0;
            }
        }
    }

    .toaster-hit-area {
        position: absolute;
        inset-block-start: 0;
        inset-inline-start: 0;
        width: min(26rem, calc(100vw - 2rem));
        transform: translateX(-50%);
        pointer-events: auto;
    }

    .toast {
        position: absolute;
        inset-block-start: 0;
        inset-inline-start: 0;
        display: flex;
        align-items: center;
        gap: var(--space-2, 0.5rem);
        /* Uniform width for every toast, shrinking to fit narrow viewports. */
        width: min(26rem, calc(100vw - 2rem));
        padding: var(--space-3, 0.75rem) var(--space-3, 0.75rem);
        border-radius: var(--corner-md);
        border: none;
        background-color: color-mix(in oklch, var(--toast-color) 12%, var(--color-surface-raised));
        color: var(--color-text);
        font-size: 0.875rem;
        pointer-events: auto;
        will-change: transform, opacity;
        /* Scale toward the bottom edge so the collapsed peek (translateY below)
           stays a constant `depth * 10px` regardless of toast height. With the
           default centre origin, scale-shrink cancels the offset for tall
           toasts and they pile exactly on top of one another. */
        transform-origin: bottom center;
        /* Collapsed: pile with scale/offset to peek behind the front toast. */
        opacity: calc(1 - var(--depth) * 0.25);
        transform: translateX(-50%) translateY(calc(var(--depth) * 10px)) scale(calc(1 - var(--depth) * 0.05));
        transition: transform 250ms var(--easing-spring, cubic-bezier(0.34, 1.56, 0.64, 1)),
        opacity 250ms ease;
    }

    /* Collapsed: every toast matches the front toast's height so deeper ones
       peek out by a uniform sliver rather than hiding behind a taller front
       toast (their own content is hidden, so they'd otherwise shrink to a stub
       and vanish). */
    .toaster:not(.toaster--expanded) .toast:not(:last-of-type) {
        height: var(--front-height);
        overflow: hidden;
    }

    /* Expanded: spread toasts upward with uniform spacing. */
    .toaster--expanded .toast {
        transform: translateX(-50%) translateY(var(--expanded-offset)) scale(1);
        opacity: 1;
    }

    .toast--hidden {
        opacity: 0;
        pointer-events: none;
    }

    .toast :global(.toast-icon) {
        flex-shrink: 0;
    }

    .toast--error {
        --toast-color: var(--color-error);
    }

    .toast--success {
        --toast-color: var(--color-success);
    }

    .toast--info {
        --toast-color: var(--color-info);
    }

    .toast--error :global(.toast-icon) {
        color: var(--color-error);
    }

    .toast--success :global(.toast-icon) {
        color: var(--color-success);
    }

    .toast--info :global(.toast-icon) {
        color: var(--color-info);
    }

    .toast-message {
        flex: 1;
        min-width: 0;
        line-height: 1.4;
    }
</style>
