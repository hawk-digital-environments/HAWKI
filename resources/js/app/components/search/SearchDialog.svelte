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
    import Kbd from '$lib/components/ui/kbd/Kbd.svelte';
    import SearchBar from '$lib/app/components/search/SearchBar.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {SearchEntry} from '$lib/kernel/search/types.js';

    interface Props {
        /** Whether the dialog is open. Supports bind:open. */
        open?: boolean;
    }

    let {open = $bindable(false)}: Props = $props();

    const search = useApp().search;
    const {__} = useTranslator();

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

    /** The content's height: the panel springs to it as the rows change. */
    let contentHeight = $state<number | undefined>();

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
            // Unset, so the next opening doesn't grow from this one's height.
            contentHeight = undefined;
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
        entry.onSelect();
    }
</script>

<Kbd key="k" ctrl onPress={() => (open = !open)}>
    {#snippet children()}{/snippet}
</Kbd>

<DialogPrimitive.Root bind:open>
    <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay class="dialog-overlay" />
        <DialogPrimitive.Content class="search-dialog" aria-describedby={undefined}>
            <DialogPrimitive.Title class="u-sr-only">{__('ui.search.title')}</DialogPrimitive.Title>
            <div class="search-dialog-frame" style:height={contentHeight === undefined ? undefined : `${contentHeight}px`}>
                <div class="search-dialog-content" bind:offsetHeight={contentHeight}>
                    <SearchBar {search} active={sessionActive} autofocus onSelect={select} />
                </div>
            </div>
        </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
</DialogPrimitive.Root>

<style>
    /* Portalled out of this subtree, so everything is addressed globally under
       the dialog's own class. The overlay reuses `.dialog-overlay` from
       Dialog.svelte so both kinds of modal dim the page identically. */
    :global(.search-dialog) {
        --search-dialog-cap: 27.5rem;
        --search-dialog-max-height: min(var(--search-dialog-cap), 76dvh);
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

    /* Both stop at the panel's cap, less its border, so the list scrolls past it. */
    :global(.search-dialog-frame),
    :global(.search-dialog-content) {
        max-height: calc(var(--search-dialog-max-height) - 2 * var(--divider-width));
    }

    :global(.search-dialog-frame) {
        overflow: hidden;
        transition: height var(--duration-extra-fast) var(--easing-out);
    }

    :global(.search-dialog-content) {
        display: flex;
        flex-direction: column;
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

        :global(.search-dialog-frame) {
            transition: none;
        }
    }

    /* The field snaps to the top edge so the on-screen keyboard leaves as much
       room as possible for results. */
    @media (--bp-md-and-smaller) {
        :global(.search-dialog) {
            --search-dialog-max-height: calc(100dvh - 2 * var(--space-4));
            top: var(--space-4);
        }
    }
</style>
