<script module lang="ts">
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    /** One row of a command list. */
    export interface CommandItemDefinition {
        /** Stable unique id. Returned by `onSelect`. */
        value: string;
        /** Text shown in the row. */
        label: string;
        /** Leading icon; rendered with the list's own size/stroke. */
        icon?: IconComponent;
        /** Groups rows under a heading. Groups keep the order they first appear in. */
        groupLabel?: string;
        /** Extra terms handed to Command for matching. Never shown. */
        keywords?: string[];
        /** Non-selectable row. */
        disabled?: boolean;
    }

    /** Rows bucketed under one heading. */
    export interface CommandGroupDefinition {
        /** Stable unique id of the group. */
        id: string;
        /** Heading shown above the rows; omitted for an unlabelled group. */
        label?: string;
        /** The group's rows, in display order. */
        items: CommandItemDefinition[];
    }
</script>

<!--
  @component The scrollable results part of a command UI: Command's List and
  Viewport with the given groups and rows inside, styled the same wherever it
  is mounted. `CommandPalette` (popover) and `SearchDialog` (modal) both render
  their rows through it and keep only what differs — the shell, the input, the
  hover treatment — to themselves.

  Must sit inside a `Command.Root`. Rows call `onSelect` with their `value`.
  A `current` value renders a trailing check on that row. `children` renders
  inside the viewport, before the groups (used for the palette's sliding hover
  highlight); `empty` renders instead of the groups when there are none — it
  sits *after* the List rather than inside it, since the List is a `listbox`
  whose only permitted children are options and groups. Remaining props go to
  the List element; pass a translated `aria-label` for it, or bits-ui falls
  back to an English default.
-->
<script lang="ts">
    import {Command as CommandPrimitive, mergeProps, type CommandListProps} from 'bits-ui';
    import type {Snippet} from 'svelte';
    import TickIcon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';

    interface Props extends Omit<CommandListProps, 'children' | 'ref'> {
        /** Groups to show, in display order. */
        groups: CommandGroupDefinition[];
        /** Fired with the chosen row's `value`. */
        onSelect: (value: string) => void;
        /** Value marked as the current one — renders a trailing check. */
        current?: string;
        /** Rendered inside the viewport ahead of the groups. */
        children?: Snippet;
        /** Rendered instead of the groups when there are none. */
        empty?: Snippet;
        /** The viewport element. Supports bind:viewport. */
        viewport?: HTMLElement | null;
    }

    let {
        groups,
        onSelect,
        current,
        children,
        empty,
        viewport = $bindable(null),
        ...restProps
    }: Props = $props();
</script>

<CommandPrimitive.List {...mergeProps({class: 'command-list'}, restProps)}>
    <CommandPrimitive.Viewport class="command-viewport" bind:ref={viewport}>
        {@render children?.()}
        {#each groups as group (group.id)}
            <CommandPrimitive.Group class="command-group" value={group.id}>
                {#if group.label}
                    <CommandPrimitive.GroupHeading class="command-group-heading">
                        {group.label}
                    </CommandPrimitive.GroupHeading>
                {/if}
                <CommandPrimitive.GroupItems class="command-group-items">
                    {#each group.items as item (item.value)}
                        {@const Icon = item.icon}
                        <CommandPrimitive.Item
                            class="command-item"
                            value={item.value}
                            keywords={item.keywords}
                            disabled={item.disabled}
                            onSelect={() => onSelect(item.value)}
                        >
                            <span class="item-icon" aria-hidden="true">
                                {#if Icon}
                                    <Icon size={17} strokeWidth={2} />
                                {/if}
                            </span>
                            <span class="item-label">{item.label}</span>
                            {#if current !== undefined}
                                <!-- The check keeps its slot at all times so labels
                                     never shift as the selection moves between rows. -->
                                <span class="item-check" class:on={current === item.value} aria-hidden="true">
                                    <TickIcon size={15} strokeWidth={2.5} />
                                </span>
                            {/if}
                        </CommandPrimitive.Item>
                    {/each}
                </CommandPrimitive.GroupItems>
            </CommandPrimitive.Group>
        {/each}
    </CommandPrimitive.Viewport>
</CommandPrimitive.List>
{#if groups.length === 0}
    {@render empty?.()}
{/if}

<style>
    /* Command renders the elements itself, so they are addressed globally
       under the list's own class. Size and placement of the list are the
       host's business (`.command-palette .command-list`, `.search-dialog
       .command-list`); everything from the viewport down is decided here. */
    :global(.command-list) {
        min-height: 0;
        overflow-y: auto;
        /* The list still scrolls; the bar itself is hidden so a long result set
           doesn't put a gutter between the rows and the panel edge. Keyboard
           navigation scrolls the active row into view, so nothing depends on
           the bar being visible. */
        overscroll-behavior: contain;
        scrollbar-width: none;
        -ms-overflow-style: none;
        -webkit-overflow-scrolling: touch;
    }

    :global(.command-list::-webkit-scrollbar) {
        display: none;
    }

    /* Rows sit apart on the same rhythm as the sidebar's nav list. The gap is
       set at every level between the viewport and the rows, since Command wraps
       them in group / group-items containers. */
    :global(.command-list .command-viewport),
    :global(.command-list .command-group),
    :global(.command-list .command-group-items) {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    /* Anchors anything the host places absolutely inside the viewport, such as
       the palette's sliding hover highlight. */
    :global(.command-list .command-viewport) {
        position: relative;
    }

    :global(.command-list .command-group-heading) {
        padding: var(--space-2) var(--space-2_5) var(--space-1);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium, 500);
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--color-text-muted);
    }

    /* Headings carry their own top padding, so the first one would double up
       against the container's own padding. */
    :global(.command-list .command-group:first-of-type .command-group-heading) {
        padding-top: var(--space-1);
    }

    :global(.command-list .command-item) {
        position: relative;
        --command-item-z: 1;
        z-index: var(--command-item-z);
        display: flex;
        align-items: center;
        gap: var(--space-2_5);
        min-height: 2.25rem;
        padding: 0 var(--space-2) 0 var(--space-2_5);
        border-radius: var(--corner-sm);
        font-size: var(--font-size-xs);
        color: var(--color-text);
        cursor: pointer;
        outline: none;
        transition: background-color var(--transition-fast);
    }

    /* In Command, `data-selected` marks the row the keyboard/pointer is on.
       Painting that wash is left to the host (`.command-palette` only once the
       user arrows into the list, `.search-dialog` always), since when it
       should show differs between the two. The accent ink + check mark below
       mark the row that is actually the current one. */

    :global(.command-list .command-item[data-disabled]) {
        color: var(--color-text-disabled);
        cursor: not-allowed;
    }

    :global(.command-list .item-icon) {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    :global(.command-list .item-label) {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    :global(.command-list .item-check) {
        display: inline-flex;
        flex-shrink: 0;
        padding-inline: var(--space-1);
        color: var(--color-active-text);
        opacity: 0;
        transform: scale(0.7);
        transition:
            opacity 140ms var(--easing-default),
            transform 200ms var(--easing-spring);
    }

    :global(.command-list .item-check.on) {
        opacity: 1;
        transform: scale(1);
    }

    /* The current row wears the sidebar's active highlight — same surface and
       ink as the nav's sliding highlight — whether or not it is also the row
       the keyboard/pointer is on. */
    :global(.command-list .command-item:has(.item-check.on)) {
        background-color: var(--color-active-surface);
        color: var(--color-active-text);
    }

    :global(.command-list .command-item:has(.item-check.on) .item-icon) {
        color: inherit;
    }

    /* Roomier rows on touch. */
    @media (--bp-md-and-smaller) {
        :global(.command-list .command-item) {
            min-height: 2.75rem;
            font-size: var(--font-size-sm);
        }
    }
</style>
