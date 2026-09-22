<!--
  @component A collapsible group of nav rows: a `SidebarItem` heading with a
  trailing caret that reveals its entries as indented child rows inline. In the
  collapsed icon rail, where sub-trees cannot render, the same entries open in
  a dropdown instead — every entry stays reachable either way.

  Entries are plain data (`items`), so the group owns no routing or feature
  knowledge: activation goes through each item's `onclick` (required for the
  rail dropdown, whose items are not links), while an optional `href` renders
  the expanded child row as a real link carrying `aria-current` while active.

  The group expands itself while one of its entries is active, so a route
  change into a grouped section reveals it.
-->
<script module lang="ts">
    /** One entry rendered inside a `SidebarGroup`. */
    export interface SidebarGroupItem {
        /** Identity for keyed iteration. */
        id: string;
        /** Text label. */
        label: string;
        /** Icon shown before the label. */
        icon?: IconComponent;
        /** Marks the entry as the current selection. */
        active?: boolean;
        /** Navigation target; renders the expanded row as a link instead of a
            button. */
        href?: ComponentProps<typeof Link>['href'];
        /** Called when the entry is activated — from the rail dropdown always,
            from an expanded row whenever it has no `href` of its own. */
        onclick?: () => void;
    }
</script>

<script lang="ts">
    import type {ComponentProps} from 'svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import Link from '$lib/components/util/link/Link.svelte';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    interface Props {
        /** Group heading shown on the collapsible row. */
        label: string;
        /** Icon shown before the group label. */
        icon?: IconComponent;
        /** The group's entries, revealed beneath the heading row. */
        items: SidebarGroupItem[];
        /** Whether the group starts expanded. */
        defaultExpanded?: boolean;
        /** Bindable expansion state of the group; defaults to `defaultExpanded`. */
        expanded?: boolean;
    }

    let {
        label,
        icon: Icon,
        items,
        defaultExpanded = false,
        expanded = $bindable(defaultExpanded)
    }: Props = $props();

    const sidebar = useSidebar();
    const collapsed = $derived(!sidebar.navOpen);

    let menuOpen = $state(false);

    const anyActive = $derived(items.some((item) => item.active));

    // Navigating into one of the entries reveals the group — the inverse
    // (collapsing it again) stays a user decision.
    $effect(() => {
        if (anyActive) expanded = true;
    });

    /** Runs one entry: closes the rail dropdown, then hands over to the item. */
    function activate(item: SidebarGroupItem): void {
        menuOpen = false;
        item.onclick?.();
    }
</script>

{#if collapsed}
    <!-- Icon rail: the sub-tree has no room, so the entries open as a dropdown
         beside it. The trigger carries the popup semantics itself. -->
    <DropdownMenu bind:open={menuOpen} title={label} side="right" align="start" sideOffset={12}>
        {#snippet trigger({props})}
            <SidebarItem {label} icon={Icon} active={menuOpen || anyActive} {...props} />
        {/snippet}
        {#each items as item (item.id)}
            <DropdownMenuItem
                icon={item.icon}
                aria-current={item.active ? 'page' : undefined}
                onclick={() => activate(item)}
            >
                {item.label}
            </DropdownMenuItem>
        {/each}
    </DropdownMenu>
{:else}
    <SidebarItem {label} icon={Icon} bind:expanded>
        {#each items as item (item.id)}
            <SidebarItem
                indent
                label={item.label}
                icon={item.icon}
                href={item.href}
                active={item.active}
                onclick={() => item.onclick?.()}
            />
        {/each}
    </SidebarItem>
{/if}
