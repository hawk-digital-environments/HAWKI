<!--
  @component Spotlight-style search: a centred modal with a single text field
  that filters the search groups registered on the kernel's `app.search` (see
  `kernel/search/SearchExtension.svelte.ts`) as the user types. Arrow keys / Enter pick a row;
  the dialog closes itself and then runs the item's `onSelect`. Opened from
  the sidebar's search action or with Ctrl/⌘ + K.

  The dialog is built from the bits-ui Dialog and Command primitives directly
  rather than on `Dialog.svelte`: that component's header/body/footer frame and
  padding is right for confirmation dialogs but wrong for a palette, where the
  input *is* the header.

  Filtering and ranking are done by the kernel: `buildSearchIndex` puts every
  registered row into an Orama index (rebuilt only when the rows change),
  `matchSearchGroups` queries it per keystroke with prefix and fuzzy matching
  and orders rows and groups by relevance. Command's own filter is switched
  off so it does not re-rank on top. Groups with no matching row are dropped
  entirely; an empty query shows everything in contributor order.
  The rows themselves are rendered by the shared `CommandResults`; only the
  modal shell, the input and the hover treatment live here.
-->
<script lang="ts">
    import {Command as CommandPrimitive, Dialog as DialogPrimitive} from 'bits-ui';
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import CommandResults, {type CommandGroupDefinition} from '$lib/components/ui/command/CommandResults.svelte';
    import Kbd from '$lib/components/ui/kbd/Kbd.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {buildSearchIndex, matchSearchGroups, type SearchItem} from '$lib/kernel/search/SearchExtension.svelte.js';

    interface Props {
        /** Whether the dialog is open. Supports bind:open. */
        open?: boolean;
    }

    let {open = $bindable(false)}: Props = $props();

    const search = useApp().search;
    const {__} = useTranslator();

    let query = $state('');

    const index = $derived(buildSearchIndex(search.groups));
    const results = $derived(matchSearchGroups(index, query));

    /** Matching rows by id, so a chosen `value` maps back to its item. */
    const itemsById = $derived(
        new Map(results.flatMap(group => group.items.map(item => [item.id, item] as const)))
    );

    const groups = $derived<CommandGroupDefinition[]>(
        results.map(group => ({
            id: group.id,
            label: group.label,
            items: group.items.map(item => ({
                value: item.id,
                label: item.title,
                icon: item.icon,
                keywords: item.keywords
            }))
        }))
    );

    // Each opening starts from a blank field; a stale query from last time
    // would hide most of the list before the user has typed anything.
    $effect(() => {
        if (!open) query = '';
    });

    function select(value: string) {
        const item: SearchItem | undefined = itemsById.get(value);
        if (!item) return;
        open = false;
        item.onSelect();
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
            <CommandPrimitive.Root
                label={__('ui.search.title')}
                loop
                shouldFilter={false}
                class="search-root"
            >
                <div class="search-field">
                    <span class="search-icon" aria-hidden="true">
                        <Search01Icon size={18} strokeWidth={2} />
                    </span>
                    <CommandPrimitive.Input
                        class="search-input"
                        placeholder={__('ui.search.placeholder')}
                        bind:value={query}
                    />
                    <!-- The keycap is decorative (aria-hidden); the text next to
                         it carries the hint for screen readers. -->
                    <span class="search-hint">
                        <span class="u-sr-only">{__('ui.search.closeHint')}</span>
                        <Kbd key="Escape" label={__('ui.search.closeKey')} alwaysVisible />
                    </span>
                </div>
                <CommandResults {groups} onSelect={select}>
                    {#snippet empty()}
                        <p class="search-empty">
                            {query ? __('ui.search.noResults', {query}) : __('ui.search.empty')}
                        </p>
                    {/snippet}
                </CommandResults>
            </CommandPrimitive.Root>
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

    :global(.search-dialog .search-root) {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    /* ── Field ────────────────────────────────────────────────────────── */

    :global(.search-dialog .search-field) {
        display: flex;
        align-items: center;
        gap: var(--space-2_5);
        padding: var(--space-3) var(--space-4);
        border-bottom: var(--border);
    }

    :global(.search-dialog .search-icon) {
        display: inline-flex;
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    :global(.search-dialog .search-input) {
        flex: 1;
        min-width: 0;
        border: none;
        outline: none;
        background: transparent;
        color: var(--color-text);
        font: inherit;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
    }

    :global(.search-dialog .search-input::placeholder) {
        color: var(--color-text-muted);
    }

    :global(.search-dialog .search-hint) {
        display: inline-flex;
        flex-shrink: 0;
    }

    /* ── List ─────────────────────────────────────────────────────────── */

    /* Rows and headings come styled from `CommandResults`; the dialog only
       sets the list's inset and paints the keyboard/pointer wash right away
       (Command moves `data-selected` on hover too, so one rule covers both). */
    :global(.search-dialog .command-list) {
        padding: var(--space-1);
    }

    :global(.search-dialog .command-item[data-selected]) {
        background-color: var(--color-hover);
    }

    :global(.search-dialog .search-empty) {
        margin: 0;
        padding: var(--space-6) var(--space-4);
        text-align: center;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
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

    /* The field snaps to the top edge so the on-screen keyboard leaves as much
       room as possible for results. */
    @media (--bp-md-and-smaller) {
        :global(.search-dialog) {
            top: var(--space-4);
            max-height: calc(100dvh - 2 * var(--space-4));
        }
    }
</style>
