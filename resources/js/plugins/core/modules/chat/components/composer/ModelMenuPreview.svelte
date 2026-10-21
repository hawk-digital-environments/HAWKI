<!--
  @component Floating preview of a model's `ModelCard`, shown beside `ModelPickerV2`'s
  dropdown while the user moves through the model rows (pointer or arrow keys).

  The panel reveals sideways out of the menu edge with a cross-blur (transitions.dev
  "panel reveal", turned onto the X axis), after a short intent delay so sweeping the pointer
  across the menu doesn't flash it. While it is open, moving to another row cross-fades the
  card in place ("text swap soft"): nothing moves, so even a quick sweep stays calm, and the
  panel eases to the new card's height ("card resize") instead of snapping.

  Purely visual and hidden from assistive tech — the same card is reachable through the
  row's detail view. Sits to the right of the menu, or to its left when there's no room; it
  stays hidden when neither side fits.

  ## Usage
  ```svelte
  <ModelMenuPreview model={previewModel} anchor={menuEl}/>
  ```
-->
<script lang="ts">
    import {Portal} from 'bits-ui';
    import {cubicInOut} from 'svelte/easing';
    import ModelCard from '$plugins/core/components/ModelCard.svelte';
    import {motionDuration} from '$lib/utils/transitions/reducedMotion.svelte.js';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';

    interface Props {
        /** The model to preview; `null` hides the panel. */
        model: AiModel | null;
        /** The menu panel the preview is placed next to. */
        anchor: HTMLElement | null;
    }

    const {model, anchor}: Props = $props();

    // transitions.dev: panel reveal (open/close) and text swap soft (card swap).
    const INTENT_DELAY = 200;
    const SWAP_DURATION = 200;
    const RESIZE_DURATION = 240;
    const GAP = 4;
    const VIEWPORT_MARGIN = 8;

    let panelEl = $state<HTMLDivElement | null>(null);
    let panelHeight = $state(0);
    let open = $state(false);
    // Kept after `model` clears, so the card stays in place while the panel fades out.
    let shown = $state<AiModel | null>(null);
    let anchorRect = $state<DOMRect | null>(null);
    // Height of the current card; the stack eases to it. Set without easing while the panel is
    // closed, so it opens at the right size instead of growing.
    let cardHeight = $state<number | null>(null);
    let resizing = $state(false);

    $effect(() => {
        if (!model || !anchor) {
            open = false;
            cardHeight = null;
            return;
        }
        const next = model;
        anchorRect = anchor.getBoundingClientRect();
        if (open) {
            shown = next;
            return;
        }
        const t = setTimeout(() => {
            shown = next;
            open = true;
        }, INTENT_DELAY);
        return () => clearTimeout(t);
    });

    $effect(() => {
        if (!open || !anchor) {
            return;
        }
        const update = () => {
            anchorRect = anchor.getBoundingClientRect();
        };
        window.addEventListener('resize', update);
        return () => window.removeEventListener('resize', update);
    });

    const placement = $derived.by(() => {
        if (!anchorRect || !panelEl) {
            return null;
        }
        const width = panelEl.offsetWidth;
        let side: 'right' | 'left' | null = null;
        if (anchorRect.right + GAP + width <= window.innerWidth - VIEWPORT_MARGIN) {
            side = 'right';
        } else if (anchorRect.left - GAP - width >= VIEWPORT_MARGIN) {
            side = 'left';
        }
        if (!side) {
            return null;
        }
        const maxTop = window.innerHeight - VIEWPORT_MARGIN - panelHeight;
        return {
            side,
            left: side === 'right' ? anchorRect.right + GAP : anchorRect.left - GAP - width,
            top: Math.max(VIEWPORT_MARGIN, Math.min(anchorRect.top, maxTop))
        };
    });

    // Reports the current card's height; the outgoing card keeps observing until it's gone,
    // so only the card for `shown` counts.
    function measureCard(node: HTMLElement, modelId: string) {
        const observer = new ResizeObserver(() => {
            if (modelId !== shown?.model_id) {
                return;
            }
            resizing = open;
            cardHeight = node.offsetHeight;
        });
        observer.observe(node);
        return {destroy: () => observer.disconnect()};
    }

    // The new card fades in on top of the old one, which stays fully opaque underneath
    // until it's covered: fading both at once would dim everything the two cards share (the
    // layout, the labels) halfway through, which reads as a blink. No swap while the panel
    // itself is revealing; its own cross-blur covers that.
    function cardIn(_node: Element) {
        return {
            duration: open ? motionDuration(SWAP_DURATION) : 0,
            easing: cubicInOut,
            css: (t: number) => `
                z-index: 1;
                opacity: ${t};
            `
        };
    }

    function cardOut(_node: Element) {
        return {
            duration: open ? motionDuration(SWAP_DURATION) : 0,
            css: () => 'z-index: 0;'
        };
    }
</script>

<Portal>
    <div
        class="model-preview"
        class:model-preview--left={placement?.side === 'left'}
        data-open={open && !!placement}
        aria-hidden="true"
        bind:this={panelEl}
        bind:offsetHeight={panelHeight}
        style:left={placement ? `${placement.left}px` : undefined}
        style:top={placement ? `${placement.top}px` : undefined}>
        <div
            class="model-preview-stack"
            style:height={cardHeight !== null ? `${cardHeight}px` : undefined}
            style:transition={resizing ? `height ${RESIZE_DURATION}ms cubic-bezier(0.22, 1, 0.36, 1)` : 'none'}>
            {#if shown}
                {#key shown.model_id}
                    <div class="model-preview-card" in:cardIn out:cardOut use:measureCard={shown.model_id}>
                        <ModelCard model={shown} dense/>
                    </div>
                {/key}
            {/if}
        </div>
    </div>
</Portal>

<style>
    /* transitions.dev "panel reveal" tuning, sliding out of the menu edge instead of up. */
    .model-preview {
        --panel-open-dur: 400ms;
        --panel-close-dur: 350ms;
        --panel-translate-x: -12px;
        --panel-blur: 2px;
        --panel-ease: cubic-bezier(0.22, 1, 0.36, 1);

        position: fixed;
        top: 0;
        left: 0;
        z-index: var(--layer-overlay);
        display: grid;
        width: calc(0.25rem * 80);
        max-height: calc(100vh - var(--space-4));
        overflow: hidden;
        padding: var(--space-3) var(--space-3) var(--space-2);
        border: var(--border);
        border-radius: var(--corner-md);
        background-color: var(--color-surface-raised);

        transform: translateX(var(--panel-translate-x));
        opacity: 0;
        filter: blur(var(--panel-blur));
        pointer-events: none;
        transition:
            transform var(--panel-close-dur) var(--panel-ease),
            opacity   var(--panel-close-dur) var(--panel-ease),
            filter    var(--panel-close-dur) var(--panel-ease);
        will-change: transform, opacity, filter;
    }

    /* Portalled to `body`, outside `#hawki-app`, so the reset layer's margin reset doesn't
       reach the card (its heading would keep the browser's margins). */
    .model-preview :global(*) {
        margin: 0;
    }

    /* A glance, not the full read: the description is cut to three lines here; the detail
       view still shows all of it. */
    .model-preview :global(.model-card__description) {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        line-clamp: 3;
        overflow: hidden;
    }

    .model-preview--left {
        --panel-translate-x: 12px;
    }

    .model-preview[data-open='true'] {
        transform: translateX(0);
        opacity: 1;
        filter: blur(0);
        transition:
            transform var(--panel-open-dur) var(--panel-ease),
            opacity   var(--panel-open-dur) var(--panel-ease),
            filter    var(--panel-open-dur) var(--panel-ease);
    }

    /* transitions.dev "card resize": eases between card heights (timing set inline, since it
       only applies once the panel is open). A taller outgoing card is clipped as it fades. */
    .model-preview-stack {
        display: grid;
        align-items: start;
        min-width: 0;
        overflow: hidden;
        will-change: height;
    }

    /* Outgoing and incoming cards share one cell, so the swap never reflows the panel. The
       card needs its own background to cover the one underneath. */
    .model-preview-card {
        grid-area: 1 / 1;
        background-color: var(--color-surface-raised);
        min-width: 0;
    }

    @media (prefers-reduced-motion: reduce) {
        .model-preview,
        .model-preview-stack {
            transition: none !important;
        }
    }
</style>
