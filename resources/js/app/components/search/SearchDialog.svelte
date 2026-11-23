<!--
  @component Spotlight-style search: a centred modal around a single
  `SearchBar`. Opened from the sidebar's search action or with Ctrl/⌘ + K,
  closed with Escape. Picking a row closes the dialog *before* running the
  entry's action, so the page the action navigates to is never covered by a
  palette on its way out.

  The dialog is built from the bits-ui Dialog primitive directly rather than on
  `Dialog.svelte`: that component's header/body/footer frame and padding is
  right for confirmation dialogs but wrong for a palette, where the input *is*
  the header. Everything below the modal shell — the field, the scope filters,
  the ranked rows, the loading and error states — belongs to `SearchBar`, which
  owns one search session for as long as the dialog is open.
-->
<script lang="ts">
    import {Dialog as DialogPrimitive} from 'bits-ui';
    import HeightTransition from '$lib/components/ui/height-transition/HeightTransition.svelte';
    import Kbd from '$lib/components/ui/kbd/Kbd.svelte';
    import SearchBar from '$lib/app/components/search/SearchBar.svelte';
    import BottomSheet from '$lib/components/ui/sheet/BottomSheet.svelte';
    import {useBreakpoint} from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {SearchEntry} from '$lib/kernel/search/types.js';

    interface Props {
        /** Whether the dialog is open. Supports bind:open. */
        open?: boolean;
        /** Called when a result is picked, after the dialog closes and before its action runs. */
        onSelect?: (entry: SearchEntry) => void;
    }

    let {open = $bindable(false), onSelect}: Props = $props();

    const search = useApp().search;
    const {__} = useTranslator();
    // The sidebar's mobile breakpoint: where the nav is a drawer, search is a sheet.
    const breakpoint = useBreakpoint();
    const compact = $derived(breakpoint.is('bpMdAndSmaller'));

    /**
     * The session outlives the close animation so the results do not
     * collapse into a bare field while the dialog is still fading out.
     * `CLOSE_LINGER_MS` tracks the close animation duration
     * (`--duration-extra-fast`, 200ms); with reduced motion the extra 20ms are
     * invisible.
     */
    const CLOSE_LINGER_MS = 220;
    let sessionActive = $state(false);
    let closeTimer: ReturnType<typeof setTimeout> | null = null;

    $effect(() => {
        if (open) {
            if (closeTimer !== null) {
                clearTimeout(closeTimer);
                closeTimer = null;
            }
            sessionActive = true;
            return;
        }
        closeTimer = setTimeout(() => {
            closeTimer = null;
            sessionActive = false;
        }, CLOSE_LINGER_MS);
        return () => {
            if (closeTimer !== null) {
                clearTimeout(closeTimer);
                closeTimer = null;
            }
        };
    });

    function select(entry: SearchEntry) {
        open = false;
        onSelect?.(entry);
        entry.onSelect();
    }
</script>

<Kbd key="k" ctrl onPress={() => (open = !open)}>
    {#snippet children()}{/snippet}
</Kbd>

{#if compact}
    <!-- Mobile: a sheet from the bottom edge, at a fixed height so the field
         holds still while the rows under it filter. -->
    <BottomSheet bind:open contentProps={{class: 'search-sheet', 'aria-describedby': undefined}}>
        {#snippet title()}<span class="u-sr-only">{__('ui.search.title')}</span>{/snippet}
        <div class="search-sheet-fill">
            <SearchBar {search} active={sessionActive} autofocus onSelect={select} />
        </div>
    </BottomSheet>
{:else}
    <DialogPrimitive.Root bind:open>
        <DialogPrimitive.Portal>
            <DialogPrimitive.Overlay class="dialog-overlay" />
            <DialogPrimitive.Content class="search-dialog" aria-describedby={undefined}>
                <DialogPrimitive.Title class="u-sr-only">{__('ui.search.title')}</DialogPrimitive.Title>
                <!-- The panel eases to the rows' height as they filter. -->
                <HeightTransition>
                    <SearchBar {search} active={sessionActive} autofocus onSelect={select} />
                </HeightTransition>
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
{/if}

<style>
    /* Portalled out of this subtree, so everything is addressed globally under
       the dialog's own class. The overlay reuses `.dialog-overlay` from
       Dialog.svelte so both kinds of modal dim the page identically. */
    :global(.search-dialog) {
        --search-dialog-cap: 27.5rem;
        --search-dialog-max-height: min(var(--search-dialog-cap), 76dvh);
        /* Stops at the panel's cap, less its border, so the list scrolls past it. */
        --height-transition-max: calc(var(--search-dialog-max-height) - 2 * var(--divider-width));
        position: fixed;
        /* The top edge stays where a panel at the cap height sits centred, so
           the field holds still while the rows under it filter down. */
        top: max(12dvh, calc(50dvh - var(--search-dialog-cap) / 2));
        left: 50%;
        translate: -50% 0;
        z-index: var(--layer-overlay);
        display: flex;
        flex-direction: column;
        width: min(33.75rem, calc(100vw - 2 * var(--space-4)));
        max-height: var(--search-dialog-max-height);
        border: var(--border);
        border-radius: var(--corner-md);
        background-color: var(--color-surface-raised);
        overflow: hidden;

        &[data-state='open'] {
            animation: search-dialog-in var(--duration-fast) var(--easing-spring);
        }

        &[data-state='closed'] {
            animation: search-dialog-out var(--duration-extra-fast) var(--easing-default);
        }
    }

    @keyframes search-dialog-in {
        from {
            opacity: 0;
            scale: 0.97;
        }
        to {
            opacity: 1;
            scale: 1;
        }
    }

    @keyframes search-dialog-out {
        from {
            opacity: 1;
            scale: 1;
        }
        to {
            opacity: 0;
            scale: 0.97;
        }
    }

    /* The palette appears and disappears either way; only the movement goes. */
    @media (prefers-reduced-motion: reduce) {
        :global(.search-dialog[data-state='open']),
        :global(.search-dialog[data-state='closed']) {
            animation: none;
        }
    }

    /* Mobile sheet: a fixed height rather than one that follows the rows, so
       the field never moves under the thumb while typing. */
    :global(.sheet-content.search-sheet) {
        height: min(85dvh, 40rem);
    }

    /* Fills the sheet's body so the result list, not the whole body, scrolls
       and the field stays pinned at the top. */
    .search-sheet-fill {
        display: flex;
        height: 100%;
        flex-direction: column;
    }
</style>
