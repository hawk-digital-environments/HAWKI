<!--
  @component Section header row inside `ToolMenuList` — labels a group of tool rows
  (the "HAWKI tools" section, or one row per MCP server) and, when a description is
  available, adds an `InfoPopover` trigger next to the label.

  The info trigger is not part of bits-ui's menu roving-tabindex (it's a popover trigger,
  not a menu item), so this component registers itself with `ToolMenuFocusContext` under
  key `group-info:${id}` and drives its own ArrowUp/ArrowDown/Tab navigation to the
  adjacent registered entry (a tool row or another group header). Enter/Space are
  swallowed so they only open the popover, never toggle a tool underneath.

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
    import {useToolMenuFocusContext} from '$plugins/core/modules/chat/components/composer/contexts/ToolMenuFocusContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    interface Props {
        /** Unique key for this group, used both as the focus-registry key
         *  (`group-info:${id}`) and, for MCP groups, the server id. */
        id: string;
        /** The group's display label, e.g. an MCP server's `server_label`. */
        label: string;
        /** Optional description shown via an `InfoPopover`; when `null`, no popover trigger
         *  is rendered — just the plain label. */
        description: string | null;
    }

    const {id, label, description}: Props = $props();
    const focusContext = useToolMenuFocusContext();

    let triggerEl = $state<HTMLButtonElement | null>(null);

    $effect(() => {
        if (!triggerEl) return;
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
</script>

<DropdownMenuLabel>
    {label}
    {#if description}
        <InfoPopover
            bind:triggerEl
            info={description}
            ariaLabel={__('chat.composer.toolMenu.showGroupDescription', {label})}
            triggerProps={{
                tabindex: -1,
                onkeydown: onKeydown,
                onpointerdown: (e: Event) => e.stopPropagation(),
                onpointerup: (e: Event) => e.stopPropagation()
            }}/>
    {/if}
</DropdownMenuLabel>

