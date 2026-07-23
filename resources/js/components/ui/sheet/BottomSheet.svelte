<!--
  @component Mobile bottom sheet — slides up from the bottom edge and is the
  touch-friendly counterpart to popovers/dropdowns/selects on small viewports.

  Built on the bits-ui Dialog primitive, so it inherits focus trapping, Escape
  to close, scroll locking and an accessible title. Supports tap-the-overlay to
  dismiss and drag-the-handle-down to dismiss.

  Pair it with `<Breakpoint>` to swap a desktop popover for a sheet:
  ```svelte
  <Breakpoint>
      {#snippet bpSmallerThanMd()}
          <BottomSheet bind:open title="…">…</BottomSheet>
      {/snippet}
      {#snippet children()}
          <Popover>…</Popover>
      {/snippet}
  </Breakpoint>
  ```
-->
<script lang="ts">
    import {Dialog as DialogPrimitive, type DialogContentProps, mergeProps} from 'bits-ui';
    import type {Snippet} from 'svelte';
    import SnippetOrString from '$lib/components/util/snippetOrString/SnippetOrString.svelte';
    import {__} from '$lib/utils/translator.js';

    interface Props {
        /** Whether the sheet is open. Supports bind:open. */
        open?: boolean;
        /** Called when the open state changes. */
        onOpenChange?: (open: boolean) => void;
        /** Visible heading; also used as the accessible dialog title. */
        title?: Snippet | string;
        /** Optional sub-text rendered under the title. */
        description?: Snippet | string;
        /** Sheet body content. */
        children?: Snippet;
        /** Additional props forwarded to the sheet panel (Dialog.Content). */
        contentProps?: Omit<DialogContentProps, 'children'>;
    }

    let {
        open = $bindable(false),
        onOpenChange,
        title,
        description,
        children,
        contentProps
    }: Props = $props();

    /** Distance (px) past which a downward drag dismisses the sheet. */
    const DISMISS_DISTANCE = 110;
    /** Downward flick speed (px/ms) that dismisses regardless of distance. */
    const DISMISS_VELOCITY = 0.55;

    let dragOffset = $state(0);
    let dragging = $state(false);
    let releasing = $state(false);

    let pointerId: number | null = null;
    let releaseTimer: ReturnType<typeof setTimeout> | undefined;
    let startY = 0;
    let lastY = 0;
    let lastT = 0;
    let velocity = 0;

    function onPointerDown(e: PointerEvent) {
        pointerId = e.pointerId;
        dragging = true;
        startY = lastY = e.clientY;
        lastT = e.timeStamp;
        velocity = 0;
        (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
    }

    function onPointerMove(e: PointerEvent) {
        if (!dragging || e.pointerId !== pointerId) return;
        // Only react to downward movement.
        dragOffset = Math.max(0, e.clientY - startY);
        const dt = e.timeStamp - lastT;
        if (dt > 0) velocity = (e.clientY - lastY) / dt;
        lastY = e.clientY;
        lastT = e.timeStamp;
    }

    function onPointerUp(e: PointerEvent) {
        if (!dragging || e.pointerId !== pointerId) return;
        dragging = false;
        pointerId = null;
        clearTimeout(releaseTimer);

        if (dragOffset > DISMISS_DISTANCE || velocity > DISMISS_VELOCITY) {
            // Close. Keep the dragged offset (no inline transition) so the exit
            // keyframe carries the panel down from where it was released, then
            // reset once it has unmounted so the next open starts fresh.
            open = false;
            releaseTimer = setTimeout(() => (dragOffset = 0), 250);
        } else {
            // Snap back up with a one-off transition.
            releasing = true;
            dragOffset = 0;
            releaseTimer = setTimeout(() => (releasing = false), 220);
        }
    }

    // Always (re)open un-dragged.
    $effect(() => {
        if (open) {
            dragOffset = 0;
            releasing = false;
        }
    });

    // Only ever emit an inline transition while snapping back — a permanent one
    // would stall the dialog's keyframe-based exit-animation detection.
    const panelStyle = $derived.by(() => {
        if (dragging) {
            return `transform: translateY(${dragOffset}px); transition: none;`;
        }
        if (dragOffset > 0) {
            const transition = releasing
                ? ' transition: transform var(--duration-extra-fast, 200ms) var(--easing-spring);'
                : '';
            return `transform: translateY(${dragOffset}px);${transition}`;
        }
        return undefined;
    });
</script>

<DialogPrimitive.Root bind:open {onOpenChange}>
    <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay {...mergeProps({class: 'sheet-overlay'})}/>
        <DialogPrimitive.Content
            {...mergeProps({class: 'sheet-content', style: panelStyle}, contentProps) as DialogContentProps}
        >
            <div
                role="dialog"
                tabindex={-1}
                class="sheet-drag-region u-no-select"
                onpointerdown={onPointerDown}
                onpointermove={onPointerMove}
                onpointerup={onPointerUp}
                onpointercancel={onPointerUp}
            >
                <div class="sheet-grabber" aria-hidden="true"></div>

                {#if title}
                    <DialogPrimitive.Title class="sheet-title">
                        <SnippetOrString value={title}/>
                    </DialogPrimitive.Title>
                {:else}
                    <DialogPrimitive.Title class="u-sr-only">{__('ui.sheet.menuTitle')}</DialogPrimitive.Title>
                {/if}

                {#if description}
                    <DialogPrimitive.Description class="sheet-description">
                        <SnippetOrString value={description}/>
                    </DialogPrimitive.Description>
                {/if}
            </div>

            <div class="sheet-body">
                {@render children?.()}
            </div>
        </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
</DialogPrimitive.Root>

<style>
    /* ── Overlay ──────────────────────────────────────────────────────── */

    :global(.sheet-overlay) {
        position: fixed;
        inset: 0;
        background-color: color-mix(in oklch, var(--color-bg) 55%, transparent);

        &[data-state='open'] {
            animation: sheet-overlay-in var(--duration-fast, 300ms) var(--easing-out);
        }

        &[data-state='closed'] {
            animation: sheet-overlay-out var(--duration-extra-fast, 200ms) var(--easing-default);
        }
    }

    /* ── Panel ────────────────────────────────────────────────────────── */

    :global(.sheet-content) {
        --sheet-bg: var(--color-surface-raised);

        position: fixed;
        inset-inline: 0;
        bottom: 0;
        display: flex;
        flex-direction: column;
        max-height: min(85vh, 40rem);
        padding-bottom: env(safe-area-inset-bottom, 0px);
        border-top-left-radius: var(--corner-lg);
        border-top-right-radius: var(--corner-lg);
        border-top: var(--border);
        background-color: var(--sheet-bg);
        box-shadow: var(--elevation-2);
        will-change: transform;

        &[data-state='open'] {
            animation: sheet-in var(--duration-fast, 300ms) var(--easing-spring);
        }

        &[data-state='closed'] {
            animation: sheet-out var(--duration-extra-fast, 200ms) var(--easing-default);
        }

        &:focus-visible {
            outline: none;
        }
    }

    /* ── Drag region / handle ─────────────────────────────────────────── */

    .sheet-drag-region {
        flex-shrink: 0;
        padding: var(--space-2) var(--space-5) var(--space-3);
        touch-action: none;
        cursor: grab;

        &:active {
            cursor: grabbing;
        }
    }

    .sheet-grabber {
        width: 2.25rem;
        height: 0.25rem;
        margin: 0 auto var(--space-3);
        border-radius: var(--corner-full);
        background-color: color-mix(in oklch, var(--color-text-muted) 45%, transparent);
    }

    :global(.sheet-title) {
        margin: 0;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium, 500);
        color: var(--color-text);
    }

    :global(.sheet-description) {
        margin-top: var(--space-1);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }

    /* ── Body ─────────────────────────────────────────────────────────── */

    .sheet-body {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        padding: 0 var(--space-5) var(--space-5);
    }

    /* ── Keyframes ────────────────────────────────────────────────────── */

    /* `from` is implicit (the panel's current transform) so a drag-to-dismiss
       continues smoothly from wherever the finger released. */
    @keyframes sheet-in {
        from {
            transform: translateY(100%);
        }
    }

    @keyframes sheet-out {
        to {
            transform: translateY(100%);
        }
    }

    @keyframes sheet-overlay-in {
        from {
            opacity: 0;
        }
    }

    @keyframes sheet-overlay-out {
        to {
            opacity: 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        :global(.sheet-content),
        :global(.sheet-overlay) {
            animation-duration: 1ms;
        }

        :global(.sheet-content) {
            transition: none;
        }
    }
</style>
