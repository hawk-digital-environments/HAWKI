<!--
  @component Text element that truncates itself and reveals its full content in
  a tooltip, but only when the text is actually cut off — text that fits never
  gets a tooltip.

  Truncation is built in: `truncate="ellipsis"` (default) cuts a single line,
  `truncate="clamp"` cuts after `lines` lines, `truncate="none"` leaves the text
  unstyled (overflow detection still works for consumer-applied truncation CSS).

  Typography travels through inheritable custom properties, so consumers stay
  scoped — no `:global()` reaching into this child:
    .row { --overflow-text-font-size: var(--font-size-base); --overflow-text-color: var(--color-text); }

  Supported vars: `--overflow-text-font-size`, `--overflow-text-font-weight`,
  `--overflow-text-color`, `--overflow-text-line-height` (all default to
  `inherit`).

  The `class` prop is an escape hatch for layout CSS the vars can't express;
  selectors targeting it from the consumer still need `:global()` since the
  span is rendered inside this component.

  Overflow is detected by comparing scrollWidth/scrollHeight against
  clientWidth/clientHeight, re-measured on content change and on element resize
  (e.g. a card grid track narrowing); the 1px tolerance keeps fractional layout
  rounding from counting as truncation.

  @example Single-line ellipsis (default):
  ```svelte
  <OverflowTooltip value={assistant.name} focusable={false} />
  ```

  @example Clamped to two lines:
  ```svelte
  <OverflowTooltip value={assistant.description} truncate="clamp" lines={2} />
  ```
-->
<script lang="ts">
    import Tooltip from './Tooltip.svelte';
    import {untrack} from 'svelte';
    import type {ComponentProps} from 'svelte';

    type Props = {
        /** Text to render; also used as the tooltip content when truncated. */
        value: string;
        /** Truncation mode: single-line ellipsis (default), multi-line clamp, or none. */
        truncate?: 'ellipsis' | 'clamp' | 'none';
        /** Line count for `truncate="clamp"`. Ignored otherwise. Defaults to 1. */
        lines?: number;
        /** Class for the rendered text element; escape hatch beyond the custom properties. */
        class?: string;
    } & Partial<Pick<ComponentProps<typeof Tooltip>, 'side' | 'sideOffset' | 'delayDuration' | 'focusable'>>;

    let {
        value,
        truncate = 'ellipsis',
        lines = 1,
        class: className,
        side,
        sideOffset,
        delayDuration = 300,
        focusable = true
    }: Props = $props();

    let textEl = $state<HTMLSpanElement | null>(null);
    let truncated = $state(false);

    /* Re-measure when the text changes (effects run after the DOM update) and
       when the element resizes (e.g. its card grid track narrows). The 1px
       tolerance keeps fractional layout rounding from counting as truncation. */
    $effect(() => {
        const text = value;
        const el = untrack(() => textEl);
        if (!el || !text) return;

        const measure = () => {
            truncated = el.scrollWidth > el.clientWidth + 1
                || el.scrollHeight > el.clientHeight + 1;
        };
        measure();

        const observer = new ResizeObserver(measure);
        observer.observe(el);
        return () => observer.disconnect();
    });
</script>

<Tooltip
        tooltip={value}
        disabled={!truncated}
        style="width: max-content"
        maxWidth="calc(100vw - var(--space-8))"
        {side}
        {sideOffset}
        {delayDuration}
        {focusable}
>
    {#snippet children({props})}
        <span
                bind:this={textEl}
                class="overflow-text overflow-text--{truncate}{className ? ` ${className}` : ''}"
                style:--overflow-text-lines={lines}
                {...props}
        >{value}</span>
    {/snippet}
</Tooltip>

<style>
    .overflow-text {
        display: block;
        font-size: var(--overflow-text-font-size, inherit);
        font-weight: var(--overflow-text-font-weight, inherit);
        color: var(--overflow-text-color, inherit);
        line-height: var(--overflow-text-line-height, inherit);
    }

    .overflow-text--ellipsis {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .overflow-text--clamp {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: var(--overflow-text-lines, 1);
        line-clamp: var(--overflow-text-lines, 1);
        overflow: hidden;
    }
</style>
