<!--
  @component Two-panel animated container for a `DropdownMenu` (or the
  `BottomSheet` it renders as on mobile) that needs a drill-down "detail"
  view — e.g. selecting a row shows configuration for that row. When `open`
  is false the default `children` panel (typically a list of
  `DropdownMenuItem`/`DropdownMenuCheckboxItem` rows) is visible; setting
  `open` to true slides in the `details` panel instead. Height is
  spring-animated so the transition between panels (which typically differ
  in height) eases instead of snapping.

  This is placed *inside* a `DropdownMenu`'s `children`, replacing the plain
  list of items — it owns both panels itself rather than being combined with
  individual item components at the same level. See `ToolMenu.svelte` for
  the real usage: the list panel renders `ToolMenuList` (built from
  `DropdownMenuLabel` + `DropdownMenuSeparator` + `DropdownMenuCheckboxItem`
  rows), and the detail panel renders per-tool configuration.

  ```svelte
  <DropdownMenu bind:open trigger={triggerSnippet}>
      <DropdownMenuDetailView open={!!selectedEntry}>
          {#snippet details()}
              {#if selectedEntry}
                  <ToolMenuDetail entry={selectedEntry} onCloseDetail={closeDetail} />
              {/if}
          {/snippet}
          <ToolMenuList entries={groupedEntries} onOpenDetail={openDetail} />
      </DropdownMenuDetailView>
  </DropdownMenu>
  ```
-->
<script lang="ts">

    import {type Snippet, untrack} from 'svelte';
    import {Spring} from 'svelte/motion';
    import {drillTransition} from '$lib/utils/transitions/drillTransition';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {useReducedMotion} from '$lib/utils/transitions/reducedMotion.svelte.js';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Whether the detail panel is visible. False shows `children`; true shows `details`. */
        open: boolean;
        /** Default list/summary panel, e.g. a `ToolMenuList` of menu items. */
        children: Snippet;
        /** Detail panel revealed when `open` is true, e.g. per-item configuration. */
        details: Snippet;
    }

    const {
        open,
        children,
        details,
        ...restProps
    }: Props = $props();

    let defaultHeight = $state(0);
    let detailHeight = $state(0);
    const reducedMotion = useReducedMotion();

    // Spring-animate the popover height so switching between the list and detail
    // views (which differ in height) eases instead of snapping.
    const viewportHeight = new Spring(0, {stiffness: 0.15, damping: 0.85});

    // Each view reports its natural height; the spring follows the active one.
    const naturalHeight = $derived(open ? detailHeight : defaultHeight);

    let viewport = $state<HTMLDivElement | null>(null);

    /**
     * The most height the viewport can take inside a panel capped with `max-height`
     * (e.g. the composer menus): the panel's cap minus its padding, borders and the
     * viewport's siblings (like a search field). Infinity when the panel is uncapped.
     */
    function availableHeight(): number {
        const panel = viewport?.parentElement;
        if (!viewport || !panel) return Infinity;
        const style = getComputedStyle(panel);
        const cap = parseFloat(style.maxHeight);
        if (!Number.isFinite(cap)) return Infinity;
        const chrome = ['paddingTop', 'paddingBottom', 'borderTopWidth', 'borderBottomWidth']
            .reduce((sum, key) => sum + (parseFloat(style[key as keyof CSSStyleDeclaration] as string) || 0), 0);
        const siblings = Array.from(panel.children)
            .filter((child) => child !== viewport)
            .reduce((sum, child) => sum + (child as HTMLElement).offsetHeight, 0);
        return Math.max(0, cap - chrome - siblings);
    }

    // The cap behind `availableHeight()` isn't final when the menu opens: bits-ui sets the
    // available-height variable only once it has positioned the panel, so the first
    // measurement can see the 24rem fallback. Re-measure whenever the panel resizes
    // (a tighter cap, a search field coming or going) so the clamp tracks the real space.
    let available = $state(Infinity);
    $effect(() => {
        const panel = viewport?.parentElement;
        if (!panel) return;
        const measure = () => (available = availableHeight());
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(panel);
        return () => observer.disconnect();
    });

    // Snap to the natural height on first measurement so the popover doesn't
    // visibly grow from 0 on open; only spring on later list<->detail switches.
    // The target is clamped to the space the panel actually has: springing toward
    // a long list's full height would spend most of the motion past the cap,
    // leaving only a few frames of visible movement — an abrupt jump.
    let initialized = $state(false);
    $effect(() => {
        if (naturalHeight <= 0) return;
        const cap = Math.min(available, availableHeight());
        const targetHeight = Math.min(naturalHeight, cap);
        if (!initialized || reducedMotion.current) {
            viewportHeight.set(targetHeight, {instant: true});
            initialized = true;
            return;
        }
        // Past the cap the panel already clips the viewport, so pulling the spring's
        // value down to it is invisible — but it keeps the next switch from starting at
        // a height nobody can see, which reads as a stall followed by a lurch.
        if (untrack(() => viewportHeight.current) > cap) {
            void viewportHeight.set(cap, {instant: true});
        }
        viewportHeight.target = targetHeight;
    });

</script>
<!--
  Both views share the same grid cell so switching crossfades in place
  without resizing the popover. Transitions are local, so they only play
  on the list<->detail switch, not when the menu itself opens or closes.
-->
<div
    bind:this={viewport}
    style:height={`${viewportHeight.current}px`}
    {...mergeProps(
        {class: 'viewport'},
        restProps
    )}>
    {#if open}
        <div class="view"
             bind:clientHeight={detailHeight}
             in:drillTransition={{distance: 16, duration: 150}}
             out:drillTransition={{distance: 16, duration: 150}}>
            {@render details?.()}
        </div>
    {:else}
        <div class="view"
             bind:clientHeight={defaultHeight}
             in:drillTransition={{direction: 'back', distance: 16, duration: 150}}
             out:drillTransition={{direction: 'back', distance: 16, duration: 150}}>
            {@render children?.()}
        </div>
    {/if}
</div>

<style>

    .viewport {
        display: grid;
        flex: 0 1 auto;
        min-height: 0;
        /* Top-align so each view keeps its natural height (measured for the
           spring) instead of stretching to the animated viewport height. */
        align-content: start;
        align-items: start;
        /* Clip the horizontal slide transition while allowing long lists to scroll. */
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: none;
        -ms-overflow-style: none;
        -webkit-overflow-scrolling: touch;
        min-width: 0;
    }

    .viewport::-webkit-scrollbar {
        display: none;
    }

    /* Stack both views in the same cell so the switch crossfades in place. */
    .view {
        grid-column: 1;
        grid-row: 1;
        min-width: 0;
    }
</style>
