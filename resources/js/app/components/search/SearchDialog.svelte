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
            <SearchBar {search} active={open} autofocus onSelect={select}>
                {#snippet hint()}
                    <!-- The keycap is decorative (aria-hidden); the text next to
                         it carries the hint for screen readers. -->
                    <span class="u-sr-only">{__('ui.search.closeHint')}</span>
                    <Kbd key="Escape" label={__('ui.search.closeKey')} alwaysVisible />
                {/snippet}
            </SearchBar>
        </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
</DialogPrimitive.Root>

<style>
    /* Portalled out of this subtree, so everything is addressed globally under
       the dialog's own class. The overlay reuses `.dialog-overlay` from
       Dialog.svelte so both kinds of modal dim the page identically. */
    :global(.search-dialog) {
        position: fixed;
        /* Spotlight sits in the upper third rather than dead centre: the list
           below the field grows and shrinks with the results, and anchoring
           the top keeps the field from jumping as it does. */
        top: 18vh;
        left: 50%;
        translate: -50% 0;
        z-index: var(--layer-overlay);
        display: flex;
        flex-direction: column;
        width: min(36rem, calc(100vw - 2 * var(--space-4)));
        max-height: 64vh;
        border: var(--border);
        border-radius: var(--corner-md);
        background-color: var(--color-surface-raised);
        box-shadow: var(--elevation-2);
        overflow: hidden;

        &[data-state='open'] {
            animation: search-dialog-in var(--duration-normal, 200ms) var(--easing-default, ease);
        }

        &[data-state='closed'] {
            animation: search-dialog-out var(--duration-normal, 200ms) var(--easing-default, ease);
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

    /* The field snaps to the top edge so the on-screen keyboard leaves as much
       room as possible for results. */
    @media (--bp-md-and-smaller) {
        :global(.search-dialog) {
            top: var(--space-4);
            max-height: calc(100dvh - 2 * var(--space-4));
        }
    }
</style>
