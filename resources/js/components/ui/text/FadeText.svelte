<!--
  @component Text clamped to a line count with a bottom fade; when that cuts
  it off, a "Read more" control — or a click on the cut-off text itself —
  expands it to its full height. One-way: it never collapses back.

  The clip animates on expand: max-height grows from the line cap to the
  measured content height while the fade band shrinks away (its length is the
  registered property --fade-text-fade, resources/css/properties.css).

  Typography travels through inheritable custom properties, so consumers stay
  scoped — no `:global()` reaching into this child:
    .row { --fade-text-font-size: var(--font-size-base); --fade-text-color: var(--color-text); }

  Supported vars: `--fade-text-font-size`, `--fade-text-font-weight`,
  `--fade-text-color`, `--fade-text-line-height` (all default to `inherit`).

  The `class` prop is an escape hatch for layout CSS the vars can't express;
  selectors targeting it from the consumer still need `:global()` since the
  wrapper is rendered inside this component.

  Truncation is detected by `observeOverflow` (`$lib/utils/overflow`):
  re-measured on content change and on element resize (e.g. a card grid
  track narrowing).

  @example
  ```svelte
  <FadeText value={assistant.detailDescription} lines={7}
            expandLabel={__('assistants.detail.read_more')} />
  ```
-->
<script lang="ts">
    import {observeOverflow} from '$lib/utils/overflow';

    type Props = {
        /** Text to render. */
        value: string;
        /** Line count the collapsed state is capped at. */
        lines: number;
        /** Label for the expand control; omit for a purely visual clamp. */
        expandLabel?: string;
        /** Class for the wrapper; escape hatch beyond the custom properties. */
        class?: string;
    };

    let {
        value,
        lines,
        expandLabel,
        class: className,
    }: Props = $props();

    let clipEl = $state<HTMLDivElement | null>(null);
    let textEl = $state<HTMLParagraphElement | null>(null);
    /** Full content height of the text, kept current by bind:clientHeight. */
    let contentHeight = $state(0);
    let truncated = $state(false);
    let expanded = $state(false);

    const textId = $props.id();

    const canExpand = $derived(truncated && !expanded);

    // A new text collapses again; truncation detection is (re-)wired in the
    // same pass, so the cap and the expand control follow the content.
    $effect(() => {
        void value;
        expanded = false;
        const el = clipEl;
        if (!el) return;
        return observeOverflow(el, ({y}) => truncated = y);
    });

    function expand() {
        expanded = true;
        // The control disappears and the text stops being a click target; keep
        // keyboard focus on the text they revealed.
        textEl?.focus();
    }
</script>

<div class="fade-text{className ? ` ${className}` : ''}">
    <div
        class="fade-text-clip"
        class:capped={!expanded}
        class:faded={truncated}
        class:expanded
        style:--fade-text-lines={lines}
        style:max-height={expanded ? `${contentHeight}px` : undefined}
        bind:this={clipEl}
    >
        <!-- Clicking the cut-off text expands it too (pointer convenience). -->
        <!-- svelte-ignore a11y_click_events_have_key_events -- the expand control is the keyboard path. -->
        <!-- svelte-ignore a11y_no_noninteractive_element_interactions -- the text is not tab-reachable (tabindex="-1"). -->
        <p
            id={textId}
            class="fade-text-content"
            class:expandable={canExpand}
            tabindex="-1"
            bind:this={textEl}
            bind:clientHeight={contentHeight}
            onclick={canExpand ? expand : undefined}
        >{value}</p>
    </div>
    {#if canExpand && expandLabel}
        <button
            type="button"
            class="fade-text-expand"
            aria-expanded="false"
            aria-controls={textId}
            onclick={expand}
        >{expandLabel}</button>
    {/if}
</div>

<style>
    .fade-text {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-1);
    }
    /* The cap counts its lines in `1lh` resolved on this element, so the
       text's line-height must live here too: set on the clip, inherited by
       the text, cap and line boxes can never drift apart. */
    .fade-text-clip {
        width: 100%;
        overflow: hidden;
        line-height: var(--fade-text-line-height, inherit);
        transition:
            max-height var(--duration-medium) ease,
            --fade-text-fade var(--duration-medium) ease;
    }
    /* The cap applies whenever the text is collapsed — including before
       truncation has been detected, since the overflow it creates is what
       detection runs on. max-height only caps, never stretches, so text that
       fits within `lines` is laid out identically. */
    .fade-text-clip.capped {
        max-height: calc(var(--fade-text-lines, 1) * 1lh);
    }
    /* The fade rides on detection instead: it would wash out the last lines
       of text that fits. Same eased ramp as the chat panel's --header-fade
       (PageHeaderBar), applied over the last --fade-text-fade of the clip.
       The fade length is a registered property
       (resources/css/properties.css) so it animates away on expand instead
       of snapping off. */
    .fade-text-clip.faded {
        --fade-text-fade: 2.5rem;
        --fade-text-mask: linear-gradient(
            to bottom,
            black 0,
            black calc(100% - var(--fade-text-fade)),
            rgba(0, 0, 0, 0.86) calc(100% - var(--fade-text-fade) * 0.727),
            rgba(0, 0, 0, 0.55) calc(100% - var(--fade-text-fade) * 0.509),
            rgba(0, 0, 0, 0.25) calc(100% - var(--fade-text-fade) * 0.291),
            rgba(0, 0, 0, 0.08) calc(100% - var(--fade-text-fade) * 0.145),
            transparent 100%
        );
        mask-image: var(--fade-text-mask);
        -webkit-mask-image: var(--fade-text-mask);
    }
    .fade-text-clip.faded.expanded {
        --fade-text-fade: 0rem;
    }
    .fade-text-content {
        margin: 0;
        outline: none; /* focus target only (tabindex="-1"), not interactive */
        /* `anywhere` (not `break-word`): break opportunities must also count
           towards min-content sizing, or a single unbreakable token widens
           the element past its container instead of filling `lines` lines. */
        overflow-wrap: anywhere;
        font-size: var(--fade-text-font-size, inherit);
        font-weight: var(--fade-text-font-weight, inherit);
        color: var(--fade-text-color, inherit);
    }
    /* Cut-off text is itself a click target (see the onclick above). */
    .fade-text-content.expandable {
        cursor: pointer;
    }
    .fade-text-expand {
        align-self: flex-start;
        padding: 0;
        border: none;
        background: none;
        font: inherit;
        font-size: var(--font-size-sm);
        font-weight: var(--fade-text-font-weight, var(--font-weight-medium));
        color: var(--color-accent-text);
        cursor: pointer;
    }
    .fade-text-expand:hover {
        text-decoration: underline;
    }
    .fade-text-expand:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
        border-radius: var(--corner-sm);
    }
</style>
