<!--
  @component Modal showing an assistant's detail view over the list it was
  opened from, filling the main content area beside the sidebar. The list
  stays mounted beneath it, so its scroll position, filters and loaded page
  survive the round trip untouched. The sidebar stays usable: no focus trap,
  no scroll lock, and clicks on it don't count as a close request (navigating
  away unmounts the list, and the modal with it).

  Rendered only while open (mount = open): the store→detail morph
  (morphToDetail) captures the moment it mounts or unmounts, so it carries no
  enter/exit animation of its own. Escape and the detail's back button both
  ask the owner to close via `onClose`.

  On the mobile layout the modal can also be pulled down to close: a
  downward drag that starts in the detail's scroll region (marked
  `data-pull-to-close`) while it is scrolled to the top. The modal rides a
  spring, and the gesture is interruptible — touching the modal while it
  snaps back catches it where it is, so the drag continues from there. A
  release past the threshold closes through `onClose` like the back button,
  so the detail morphs back into its card from where it was let go.
-->
<script lang="ts">
    import {Dialog as DialogPrimitive} from 'bits-ui';
    import {Spring} from 'svelte/motion';
    import AssistantDetail from '$plugins/assistants/modules/dashboard/components/assistantDetail/AssistantDetail.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useBreakpoint} from '$lib/components/util/breakpoints/useBreakpoint.svelte';
    import {useReducedMotion} from '$lib/utils/transitions/reducedMotion.svelte';
    import type {Assistant} from '$plugins/assistants/types/assistant/Assistant';

    interface Props {
        /** Id of the assistant to show. */
        id: string;
        /** Close request (Escape, back button). */
        onClose: () => void;
        /** After a successful delete. Defaults to `onClose`. */
        onDeleted?: () => void;
        /** The list's copy of the assistant, shown while the detail loads. */
        initial?: Assistant;
    }

    const {id, onClose, onDeleted, initial}: Props = $props();
    const {__} = useTranslator();
    const breakpoint = useBreakpoint();
    const reducedMotion = useReducedMotion();

    /** Movement (px) before a touch counts as a pull. */
    const PULL_SLOP = 8;
    /** Share of the modal's height past which a release closes it. */
    const DISMISS_FRACTION = 0.25;
    /** Downward release speed (px/ms) that closes regardless of distance. */
    const DISMISS_VELOCITY = 0.5;
    /** A release this long (ms) after the last move counts as resting. */
    const VELOCITY_STALE_MS = 100;

    /** The modal's downward offset (px). */
    const offset = new Spring(0, {stiffness: 0.2, damping: 0.85});

    let modal = $state<HTMLElement>();

    function release(target: number): Promise<void> {
        // A later set (a catch, another release) rejects the pending one.
        return offset.set(target, reducedMotion.current ? {instant: true} : undefined);
    }

    $effect(() => {
        const el = modal;
        if (!el) return;

        let touchId: number | null = null;
        let region: HTMLElement | null = null;
        let engaged = false;
        let startX = 0;
        let startY = 0;
        let base = 0;
        let lastY = 0;
        let lastT = 0;
        let velocity = 0;
        /** Released into the close morph: the modal is on its way out. */
        let closing = false;

        const findTouch = (list: TouchList) =>
            Array.from(list).find(t => t.identifier === touchId);

        function onTouchStart(e: TouchEvent) {
            if (closing || touchId !== null || e.touches.length !== 1) return;
            if (!breakpoint.is('bpModeMobile')) return;
            const touch = e.touches[0];
            const moving = offset.target > 0 || offset.current > 0.5;
            region = (e.target as Element).closest?.<HTMLElement>('[data-pull-to-close]') ?? null;
            // Mid-flight (snapping back or sliding out): catch it where it is.
            // At rest: only a pull from the top of the scroll region.
            if (!moving && (!region || region.scrollTop > 0)) return;

            touchId = touch.identifier;
            engaged = moving;
            startX = touch.clientX;
            startY = lastY = touch.clientY;
            lastT = e.timeStamp;
            velocity = 0;
            base = moving ? offset.current : 0;
            if (moving) void offset.set(base, {instant: true});
        }

        function onTouchMove(e: TouchEvent) {
            if (touchId === null) return;
            const touch = findTouch(e.changedTouches);
            if (!touch) return;
            const dx = touch.clientX - startX;
            const dy = touch.clientY - startY;

            if (!engaged) {
                // Up or sideways, or the content has scrolled: not a pull.
                const scrolled = !!region && region.scrollTop > 0;
                if (scrolled || dy < 0 || Math.abs(dx) > dy) {
                    if (scrolled || Math.max(Math.abs(dx), -dy) >= PULL_SLOP) touchId = null;
                    return;
                }
                // Downward at the top: keep the browser from bouncing or
                // committing to a scroll while the pull is undecided.
                if (e.cancelable) e.preventDefault();
                if (dy < PULL_SLOP) return;
                engaged = true;
                startY = touch.clientY;
            }

            if (e.cancelable) e.preventDefault();
            const dt = e.timeStamp - lastT;
            if (dt > 0) velocity = (touch.clientY - lastY) / dt;
            lastY = touch.clientY;
            lastT = e.timeStamp;
            void offset.set(Math.max(0, base + touch.clientY - startY), {instant: true});
        }

        function onTouchEnd(e: TouchEvent) {
            if (touchId === null || !findTouch(e.changedTouches)) return;
            touchId = null;
            if (!engaged) return;
            engaged = false;

            if (e.timeStamp - lastT > VELOCITY_STALE_MS) velocity = 0;
            const height = el!.offsetHeight;
            const pastThreshold = offset.current > height * DISMISS_FRACTION && velocity >= 0;
            if (e.type === 'touchend' && (pastThreshold || velocity > DISMISS_VELOCITY)) {
                // Hold the modal where it was let go: the close morph's
                // snapshot is taken from here, so the detail shrinks back
                // into its card from the dragged position.
                closing = true;
                onClose();
            } else {
                release(0).catch(() => {});
            }
        }

        el.addEventListener('touchstart', onTouchStart, {passive: true});
        el.addEventListener('touchmove', onTouchMove, {passive: false});
        el.addEventListener('touchend', onTouchEnd);
        el.addEventListener('touchcancel', onTouchEnd);
        return () => {
            el.removeEventListener('touchstart', onTouchStart);
            el.removeEventListener('touchmove', onTouchMove);
            el.removeEventListener('touchend', onTouchEnd);
            el.removeEventListener('touchcancel', onTouchEnd);
        };
    });

    // The snap-back may overshoot a little past the top; never show a gap there.
    const pullTransform = $derived(offset.current > 0 ? `translateY(${offset.current}px)` : undefined);
</script>

<!-- Controlled: bits-ui reports every close request instead of closing itself. -->
<DialogPrimitive.Root bind:open={() => true, (value) => { if (!value) onClose(); }}>
    <!-- Into the main area (positioned, clipped), so the sidebar stays beside it. -->
    <DialogPrimitive.Portal to="#main-content">
        <DialogPrimitive.Content trapFocus={false} preventScroll={false} interactOutsideBehavior="ignore">
            {#snippet child({props})}
                <div {...props} class="detail-modal" bind:this={modal} style:transform={pullTransform}>
                    <DialogPrimitive.Title class="u-sr-only">{__('assistants.detail.dialog_title')}</DialogPrimitive.Title>
                    <AssistantDetail {id} onBack={onClose} {onDeleted} {initial} />
                </div>
            {/snippet}
        </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
</DialogPrimitive.Root>

<style>
    .detail-modal {
        position: absolute;
        inset: 0;
        /* Above the list's own layers (e.g. its header bar), below the mobile
           navigation trigger (z-index 10), which keeps opening the sidebar. */
        z-index: 2;
        background: var(--color-surface-raised);
        /* Morph layer for the modal surface: fades in over the receding list
           on open and out on close (morphToDetail.css). */
        view-transition-name: assistant-modal;
    }
</style>
