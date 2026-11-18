<!--
  @component Lets neighbouring tooltips share one bubble that travels between
  their triggers instead of closing one and opening the next. Wrap a row of
  triggers whose tooltips sit close together (a tab strip, a toolbar); every
  `Tooltip` inside joins the group without further wiring, including the ones
  inside `ButtonWithTooltip` and `SidebarIconButton`.

  The first tooltip waits out the group's delay and fades in as usual. While it
  shows, pointing at or focusing another trigger in the group moves the same
  bubble over: it glides to the new trigger and eases to the new content's
  width, clipping the content meanwhile. Crossing a gap between triggers keeps
  it open; leaving the group closes it. The motion runs on the `moderate` spring
  tier and becomes a jump under reduced motion.

  A grouped tooltip keeps its content, side and offset. The group owns the
  delay, so a grouped tooltip's `delayDuration` is not used, and neither are its
  `open`, `customAnchor` or content attributes.

  Renders no element of its own, so it doesn't affect layout.

  @example
  ```svelte
  <TooltipGroup delayDuration={300}>
      <div class="toolbar">
          <ButtonWithTooltip variant="ghost" iconLeft={BoldIcon} tooltip="Bold"/>
          <ButtonWithTooltip variant="ghost" iconLeft={ItalicIcon} tooltip="Italic"/>
      </div>
  </TooltipGroup>
  ```
-->
<script module lang="ts">
    import {getContext, hasContext, setContext, type Snippet} from 'svelte';
    import {Tooltip as TooltipPrimitive} from 'bits-ui';

    /** What a grouped tooltip hands the shared bubble while its trigger is the active one. */
    export interface TooltipGroupPayload {
        tooltip?: Snippet | string;
        side: 'top' | 'right' | 'bottom' | 'left';
        sideOffset: number;
    }

    export interface TooltipGroupContext {
        /** Connects each grouped trigger to the group's one tooltip. */
        tether: TooltipPrimitive.Tether<TooltipGroupPayload>;
    }

    // From the global symbol registry, so the pairing survives the module being
    // evaluated again during development (see `createStableContext`).
    const contextKey = Symbol.for('hawki.ui.tooltip-group');

    /** The surrounding `TooltipGroup`, or `null` outside of one. */
    export function getTooltipGroup(): TooltipGroupContext | null {
        return hasContext(contextKey) ? getContext<TooltipGroupContext>(contextKey) : null;
    }
</script>

<script lang="ts">
    import {untrack} from 'svelte';
    import {Spring} from 'svelte/motion';
    import SnippetOrString from '$lib/components/util/snippetOrString/SnippetOrString.svelte';
    import {springs} from '$lib/utils/springs.js';
    import {useReducedMotion} from '$lib/utils/transitions/reducedMotion.svelte.js';

    interface Props {
        /** Delay in milliseconds before the group's tooltip first appears. Default is 1000ms, as for `Tooltip`. */
        delayDuration?: number;
        /** The triggers, at any depth. */
        children: Snippet;
    }

    const {delayDuration = 1000, children: triggers}: Props = $props();

    interface Edges {
        left: number;
        top: number;
        right: number;
        bottom: number;
    }

    const tether = TooltipPrimitive.createTether<TooltipGroupPayload>();
    setContext(contextKey, {tether} satisfies TooltipGroupContext);

    const reducedMotion = useReducedMotion();

    let open = $state(false);

    // The trigger the bubble sits on, and whether it showed there when the
    // active trigger last changed: only then does it travel rather than start
    // in place.
    let anchorNode: HTMLElement | null = null;
    let shown = false;

    // Floating UI places the bubble against these edges instead of the trigger's,
    // so the bubble goes wherever the spring takes them.
    const edges = new Spring<Edges>({left: 0, top: 0, right: 0, bottom: 0}, springs.moderate);

    // While travelling, the bubble's content box is pinned to this width, easing
    // from the old content's to the new one's. Otherwise it sizes itself.
    let gliding = $state(false);
    let naturalWidth = $state(0);
    const width = new Spring(0, springs.moderate);
    let clip = $state<HTMLElement | null>(null);

    function measure(node: HTMLElement): Edges {
        const {left, top, right, bottom} = node.getBoundingClientRect();
        return {left, top, right, bottom};
    }

    function sameEdges(a: Edges, b: Edges): boolean {
        return a.left === b.left && a.top === b.top && a.right === b.right && a.bottom === b.bottom;
    }

    // Read every frame (the content updates its position with `always`), and from
    // inside bits-ui's effects too, so nothing here may be tracked.
    const anchor = {
        getBoundingClientRect: () => untrack(() => {
            if (anchorNode?.isConnected) {
                const live = measure(anchorNode);
                // A trigger that moves while the bubble is on it is followed, not jumped to.
                if (!sameEdges(live, edges.target)) edges.set(live, {instant: reducedMotion.current});
            }
            const {left, top, right, bottom} = edges.current;
            return new DOMRect(left, top, right - left, bottom - top);
        })
    };

    function follow(node: HTMLElement | null) {
        if (!node) {
            // Closing: the bubble fades out where it is.
            shown = false;
            gliding = false;
            return;
        }
        if (node === anchorNode && shown) return;

        // Starting from the width it has now; already gliding, it carries on from
        // where it is.
        const fromWidth = shown && !reducedMotion.current ? clip?.offsetWidth : undefined;
        const travel = fromWidth !== undefined;
        if (fromWidth !== undefined && !gliding) width.set(fromWidth, {instant: true});
        gliding = travel;
        edges.set(measure(node), {instant: !travel});
        anchorNode = node;
        shown = true;
    }

    // Before the DOM updates, so the old content can still be measured.
    $effect.pre(() => {
        const node = open ? tether.state.registry.activeTriggerNode : null;
        untrack(() => follow(node));
    });

    $effect(() => {
        if (gliding && naturalWidth) width.set(naturalWidth);
    });
</script>

<TooltipPrimitive.Provider>
    <TooltipPrimitive.Root {tether} {delayDuration} bind:open ignoreNonKeyboardFocus>
        {#snippet children({payload})}
            <TooltipPrimitive.Portal>
                <TooltipPrimitive.Content
                    class="tooltip-content"
                    side={payload?.side ?? 'top'}
                    sideOffset={payload?.sideOffset ?? 4}
                    customAnchor={anchor}
                    updatePositionStrategy="always"
                >
                    <span class="clip" style:width={gliding ? `${width.current}px` : undefined} bind:this={clip}>
                        <span class="body" bind:offsetWidth={naturalWidth}>
                            <SnippetOrString value={payload?.tooltip}/>
                        </span>
                    </span>
                </TooltipPrimitive.Content>
            </TooltipPrimitive.Portal>
        {/snippet}
    </TooltipPrimitive.Root>
</TooltipPrimitive.Provider><!-- No whitespace: it would render as a stray space before inline triggers.
-->{@render triggers()}

<style>
    /* The content keeps its own width and is centred in the clip, so while the
       width eases it is cut at both edges instead of wrapping anew. */
    .clip {
        display: flex;
        justify-content: center;
        overflow: hidden;
    }

    .body {
        flex: none;
        /* The bubble's own cap, less its padding and border. */
        max-width: calc(var(--tooltip-max-width) - 2 * var(--space-3) - 2 * var(--divider-width));
    }
</style>
