<!--
  @component Section header row inside `ToolMenuList` — labels a group of tool rows
  (the "HAWKI tools" section, or one row per MCP server) and, when a description is
  available, adds an `InfoPopover` trigger next to the label. An MCP server exposing
  multiple picker tools renders as one checkbox row with a tool count and disclosure:
  the checkbox toggles every tool, while the chevron expands the individual rows.

  For non-toggleable labels the info trigger is not part of bits-ui's menu roving-tabindex,
  so this component registers it with `ToolMenuFocusContext` and drives its keyboard
  navigation. A grouped MCP is itself a regular menu checkbox item, with the server
  description as its second line; ArrowRight/ArrowLeft expand and collapse its tools.

  ## Usage
  Rendered by `ToolMenuList` once per section — a fixed "function-tools" header, and one
  per MCP server (id = server id, description = server description):
  ```svelte
  <ToolMenuGroupHeader
      id="function-tools"
      label={__('chat.composer.toolMenu.hawkiToolsLabel')}
      description={__('chat.composer.toolMenu.hawkiToolsDescription')}/>
  ```
-->
<script lang="ts">
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import InfoPopover from '$lib/components/ui/popover/InfoPopover.svelte';
    import DropdownMenuCheckboxItem from '$lib/components/ui/dropdown-menu/DropdownMenuCheckboxItem.svelte';
    import {useToolMenuFocusContext} from '$plugins/core/modules/chat/components/composer/contexts/ToolMenuFocusContext.svelte.js';
    import type {ToolMenuEntry} from '$plugins/core/modules/chat/components/composer/ToolMenu.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import ChevronDownIcon from '$lib/components/ui/icons/iconset/ChevronDownIcon.svelte';
    import McpServerIcon from '$lib/components/ui/icons/iconset/McpServerIcon.svelte';
    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';
    import MenuPinButton from '$plugins/core/modules/chat/components/composer/MenuPinButton.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';

    const {__} = useTranslator();
    const pinStore = useStore('composer-pins');

    interface Props {
        /** Unique key for this group, used both as the focus-registry key
         *  (`group-info:${id}`) and, for MCP groups, the server id. */
        id: string;
        /** The group's display label, e.g. an MCP server's `server_label`. */
        label: string;
        /** Optional description shown via an `InfoPopover`; when `null`, no popover trigger
         *  is rendered — just the plain label. */
        description: string | null;
        /** All picker-eligible tools exposed by this MCP. More than one turns the header
         *  into a group checkbox; omitted for ordinary section labels. */
        entries?: ToolMenuEntry[];
        /** Applies the group checkbox's next state to every entry. */
        onToggle?: (active: boolean) => void;
        /** Whether this multi-tool MCP's child rows are visible. */
        expanded?: boolean;
        /** Expands or collapses this multi-tool MCP. */
        onExpandedChange?: (expanded: boolean) => void;
        /** Search results force matching groups open, temporarily disabling their disclosure. */
        disclosureLocked?: boolean;
    }

    const {
        id,
        label,
        description,
        entries = [],
        onToggle,
        expanded = false,
        onExpandedChange,
        disclosureLocked = false
    }: Props = $props();
    const focusContext = useToolMenuFocusContext();

    let triggerEl = $state<HTMLButtonElement | null>(null);

    const grouped = $derived(entries.length > 1 && !!onToggle);
    const activeEntries = $derived(entries.filter(entry => entry.active));
    const enableableEntries = $derived(entries.filter(entry => !entry.disabled));
    const checked = $derived(
        enableableEntries.length > 0 && enableableEntries.every(entry => entry.active)
    );
    const indeterminate = $derived(activeEntries.length > 0 && !checked);
    const groupPinned = $derived(grouped && pinStore.isPinned('mcp', id));
    // An all-offline group stays actionable when one of its tools was already selected,
    // allowing the user to clear that stale selection.
    const disabled = $derived(enableableEntries.length === 0 && activeEntries.length === 0);
    const nextActive = $derived(
        checked
            ? false
            : !(enableableEntries.length === 0 && activeEntries.length > 0)
    );
    const toggleLabel = $derived(__(
        nextActive
            ? 'chat.composer.toolMenu.activateGroup'
            : 'chat.composer.toolMenu.deactivateGroup',
        {label, count: String(entries.length)}
    ));
    const disclosureLabel = $derived(__(
        expanded
            ? 'chat.composer.toolMenu.collapseGroup'
            : 'chat.composer.toolMenu.expandGroup',
        {label, count: String(entries.length)}
    ));

    $effect(() => {
        if (grouped || !triggerEl) return;
        return focusContext.register(`group-info:${id}`, triggerEl, 'group-info');
    });

    // bits-ui's menu wants Enter/Space to activate the focused row; for this
    // info trigger we swallow them so the underlying tool row never toggles,
    // and we drive arrow/Tab movement ourselves since the popover trigger isn't
    // part of the menu's roving tabindex.
    function onKeydown(event: KeyboardEvent) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.stopPropagation();
            return;
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            const direction: 1 | -1 = event.key === 'ArrowDown' ? 1 : -1;
            if (focusContext.focusAdjacent(`group-info:${id}`, direction)) {
                event.preventDefault();
                event.stopPropagation();
            }
            return;
        }
        if (event.key === 'Tab') {
            if (focusContext.focusAdjacent(`group-info:${id}`, event.shiftKey ? -1 : 1)) {
                event.preventDefault();
                event.stopPropagation();
            }
        }
    }

    function onGroupKeydown(event: KeyboardEvent) {
        if (disclosureLocked) return;
        if (event.key === 'ArrowRight' && !expanded) {
            event.preventDefault();
            event.stopPropagation();
            onExpandedChange?.(true);
        } else if (event.key === 'ArrowLeft' && expanded) {
            event.preventDefault();
            event.stopPropagation();
            onExpandedChange?.(false);
        }
    }

    function toggleGroup() {
        onToggle?.(nextActive);
    }

    function toggleGroupPin() {
        pinStore.toggle('mcp', id);
    }

    function toggleExpanded(event: MouseEvent) {
        event.preventDefault();
        event.stopPropagation();
        if (disclosureLocked) return;
        onExpandedChange?.(!expanded);
    }
</script>

{#if grouped}
    <DropdownMenuCheckboxItem
        {checked}
        {indeterminate}
        {disabled}
        indicator="none"
        closeOnSelect={false}
        onCheckedChange={toggleGroup}
        aria-label={toggleLabel}
        aria-expanded={expanded}
        aria-controls={`tool-menu-mcp-panel-${id}`}
        aria-keyshortcuts="ArrowLeft ArrowRight"
        onkeydown={onGroupKeydown}
        class="tool-menu-group-label tool-menu-group-toggle">
        {#snippet children(isChecked)}
            <span class="tool-menu-group-main">
                <span
                    class="tool-menu-group-swatch"
                    class:tool-menu-group-swatch--active={isChecked || indeterminate}
                    aria-hidden="true">
                    <McpServerIcon size={16}/>
                    {#if isChecked}
                        <span class="tool-menu-group-state"><Tick02Icon size={16}/></span>
                    {:else if indeterminate}
                        <span class="tool-menu-group-state"><span class="tool-menu-group-state-mixed"></span></span>
                    {/if}
                </span>
                <span class="tool-menu-group-text">
                    <span class="tool-menu-group-label-text">{label}</span>
                    <span class="tool-menu-group-description">
                        {description || __('chat.composer.toolMenu.groupToolCount', {count: String(entries.length)})}
                    </span>
                </span>
                <span
                    class="tool-menu-group-action t-icon-swap"
                    class:t-icon-swap--pinned={groupPinned}>
                    <span class="tool-menu-group-count t-icon t-icon-count" aria-hidden="true">
                        {entries.length}
                    </span>
                    <span class="t-icon t-icon-favorite">
                        <MenuPinButton pinned={groupPinned} onToggle={toggleGroupPin}/>
                    </span>
                </span>
                <button
                    type="button"
                    class="tool-menu-group-disclosure"
                    aria-label={disclosureLabel}
                    aria-expanded={expanded}
                    aria-controls={`tool-menu-mcp-panel-${id}`}
                    disabled={disclosureLocked}
                    tabindex={-1}
                    onpointerdown={(event) => event.stopPropagation()}
                    onpointerup={(event) => event.stopPropagation()}
                    onclick={toggleExpanded}>
                    <span class="t-acc-chevron"><ChevronDownIcon size={16}/></span>
                </button>
            </span>
        {/snippet}
    </DropdownMenuCheckboxItem>
{:else}
    <DropdownMenuLabel class="tool-menu-group-label">
        {label}
        {#if description}
            <InfoPopover
                bind:triggerEl
                info={description}
                ariaLabel={__('chat.composer.toolMenu.showGroupDescription', {label})}
                triggerProps={{
                    tabindex: -1,
                    // Hovering the icon brightens it rather than moving the list's row highlight.
                    'data-list-no-hover': '',
                    onkeydown: onKeydown,
                    onpointerdown: (e: Event) => e.stopPropagation(),
                    onpointerup: (e: Event) => e.stopPropagation()
                }}/>
        {/if}
    </DropdownMenuLabel>
{/if}

<style>
    :global(.tool-menu-group-label) {
        display: flex;
        align-items: center;
        gap: var(--space-1, 0.25rem);
    }

    /* Like the favourite icon on tool rows: hover brightens the icon instead of
       putting a surface behind it. Doubled class to beat ActionIcon's own hover. */
    :global(.tool-menu-group-label .info-button.info-button:hover:not(:disabled)) {
        opacity: 1;
        color: var(--color-text);
        background-color: transparent;
    }

    :global(.dropdown-checkbox-item.tool-menu-group-toggle) {
        gap: var(--space-2);
        padding-left: var(--space-1_5);
        color: var(--color-text);
    }

    .tool-menu-group-main {
        display: flex;
        min-width: 0;
        flex: 1;
        align-items: center;
        gap: var(--space-2);
    }

    .tool-menu-group-swatch {
        position: relative;
        display: inline-flex;
        width: calc(0.25rem * 8);
        height: calc(0.25rem * 8);
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: calc(var(--corner-sm) - var(--space-1_5));
        background-color: color-mix(in oklab, var(--color-tool-category-mcp) 10%, transparent);
        color: var(--color-tool-category-mcp);
        line-height: 0;
    }

    .tool-menu-group-swatch--active {
        background-color: color-mix(in oklab, var(--color-tool-category-mcp) 20%, var(--color-surface-raised));
    }

    .tool-menu-group-state {
        position: absolute;
        display: inline-flex;
        inset: 0;
        align-items: center;
        justify-content: center;
        border-radius: inherit;
        background-color: inherit;
        color: var(--color-tool-category-mcp);
    }

    .tool-menu-group-state-mixed {
        width: calc(0.25rem * 2);
        height: 1.5px;
        border-radius: 999px;
        background-color: currentColor;
    }

    .tool-menu-group-text {
        display: flex;
        min-width: 0;
        flex: 1;
        flex-direction: column;
    }

    .tool-menu-group-label-text,
    .tool-menu-group-description {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        line-height: calc(0.25rem * 4);
    }

    .tool-menu-group-label-text {
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium, 500);
    }

    .tool-menu-group-description {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    .tool-menu-group-action {
        --icon-swap-dur: var(--duration-fast, 250ms);
        --icon-swap-blur: 2px;
        --icon-swap-start-scale: 0.25;
        --icon-swap-ease: ease-in-out;

        position: relative;
        display: inline-grid;
        min-width: var(--space-5);
        height: var(--space-5);
        flex-shrink: 0;
        place-items: center;
        /* Match MenuPinButton's row spacing so this slot shares the same
           horizontal column as the hearts on child tool rows. */
        margin-right: calc(-1 * var(--space-1_5));
    }

    .t-icon-swap .t-icon {
        grid-area: 1 / 1;
        transition:
            opacity var(--icon-swap-dur) var(--icon-swap-ease),
            filter var(--icon-swap-dur) var(--icon-swap-ease),
            transform var(--icon-swap-dur) var(--icon-swap-ease);
    }

    .t-icon-count {
        opacity: 1;
        filter: blur(0);
        transform: scale(1);
    }

    .t-icon-favorite {
        opacity: 0;
        filter: blur(var(--icon-swap-blur));
        transform: scale(var(--icon-swap-start-scale));
        pointer-events: none;
    }

    :global(.dropdown-checkbox-item.tool-menu-group-toggle:is(:hover, :focus-visible, :has(:focus-visible))) .t-icon-count,
    :global(.t-acc[data-open="true"] .dropdown-checkbox-item.tool-menu-group-toggle) .t-icon-count,
    .t-icon-swap--pinned .t-icon-count {
        opacity: 0;
        filter: blur(var(--icon-swap-blur));
        transform: scale(var(--icon-swap-start-scale));
    }

    :global(.dropdown-checkbox-item.tool-menu-group-toggle:is(:hover, :focus-visible, :has(:focus-visible))) .t-icon-favorite,
    :global(.t-acc[data-open="true"] .dropdown-checkbox-item.tool-menu-group-toggle) .t-icon-favorite,
    .t-icon-swap--pinned .t-icon-favorite {
        opacity: 1;
        filter: blur(0);
        transform: scale(1);
        pointer-events: auto;
    }

    :global(.tool-menu-group-action .menu-pin-button) {
        margin: 0;
        padding: 2px;
        opacity: 1;
    }

    .tool-menu-group-count {
        display: inline-flex;
        min-width: var(--space-5);
        height: var(--space-5);
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding-inline: var(--space-1_5);
        background-color: color-mix(in oklch, var(--color-text) 8%, transparent);
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium, 500);
    }

    .tool-menu-group-disclosure {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border: none;
        padding: 0;
        background: none;
        color: var(--color-text-muted);
        cursor: pointer;
    }

    .tool-menu-group-disclosure:hover {
        color: var(--color-text);
    }

    .tool-menu-group-disclosure:disabled {
        cursor: default;
        opacity: 0.5;
    }

    @media (prefers-reduced-motion: reduce) {
        .t-icon-swap .t-icon {
            transition: none !important;
        }
    }
</style>
