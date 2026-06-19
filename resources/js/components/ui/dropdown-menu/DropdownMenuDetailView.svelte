<script lang="ts">

    import {type Snippet} from 'svelte';
    import {Spring} from 'svelte/motion';
    import {fly} from 'svelte/transition';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        open: boolean;
        children: Snippet;
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

    // Spring-animate the popover height so switching between the list and detail
    // views (which differ in height) eases instead of snapping.
    const viewportHeight = new Spring(0, {stiffness: 0.15, damping: 0.85});

    // Each view reports its natural height; the spring follows the active one.
    const targetHeight = $derived(open ? detailHeight : defaultHeight);

    // Snap to the natural height on first measurement so the popover doesn't
    // visibly grow from 0 on open; only spring on later list<->detail switches.
    let initialized = $state(false);
    $effect(() => {
        if (targetHeight <= 0) return;
        if (!initialized) {
            viewportHeight.set(targetHeight, {instant: true});
            initialized = true;
        } else {
            viewportHeight.target = targetHeight;
        }
    });

</script>
<!--
  Both views share the same grid cell so switching crossfades in place
  without resizing the popover. Transitions are local, so they only play
  on the list<->detail switch, not when the menu itself opens or closes.
-->
<div
    style:height={`${viewportHeight.current}px`}
    {...mergeProps(
        {class: 'viewport'},
        restProps
    )}>
    {#if open}
        <div class="view"
             bind:clientHeight={detailHeight}
             in:fly={{x: 16, duration: 150}}
             out:fly={{x: 16, duration: 150}}>
            {@render details?.()}
        </div>
    {:else}
        <div class="view"
             bind:clientHeight={defaultHeight}
             in:fly={{x: -16, duration: 150}}
             out:fly={{x: -16, duration: 150}}>
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
