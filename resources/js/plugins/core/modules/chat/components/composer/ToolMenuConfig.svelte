<!--
  @component Capability-variant picker rendered inline inside `ToolMenuDetail`.

  Only renders anything for **capability** entries that have more than one way to be
  fulfilled (e.g. "web search" backed by two different MCP tools, or a capability the
  model can also do natively). It shows a `RadioRowGroup` with an "auto" option, an
  optional "native" option (when `entry.tool.hasNativeCapabilityFor` is true for some
  model), and one radio row per concrete tool that can fulfill the capability.
  Like the tool and model lists, only exceptions get a `StatusDotForTool`: an option that is
  not fully online or does not work with the current `composerContext.model.current`.

  Selecting an option calls `composerContext.tools.set(tool, selection, settings)`,
  which becomes the new `toolSelection` read back via `composerContext.tools.get(tool, true)`.

  Renders nothing (no wrapper element at all) for plain tools or single-option
  capabilities — `ToolMenuDetail` includes it unconditionally, relying on this
  component's own `{#if show}` guard.

  ## Usage
  Always used together with `ToolMenuDetail`, one level below the tool's toggle/description:
  ```svelte
  <ToolMenuDetail entry={detailEntry} onCloseDetail={closeToolDetail}>
  // inside ToolMenuDetail:
  <ToolMenuConfig entry={entry}/>
  ```
-->
<script lang="ts">

    import type {ToolMenuEntry} from '$plugins/core/modules/chat/components/composer/ToolMenu.svelte';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import RadioRowGroup from '$lib/components/ui/radio-row/RadioRowGroup.svelte';
    import RadioRow from '$lib/components/ui/radio-row/RadioRow.svelte';
    import StatusDotForTool from '$plugins/core/modules/chat/components/composer/StatusDotForTool.svelte';
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import InfoPopover from '$lib/components/ui/popover/InfoPopover.svelte';
    import type {ComponentProps} from 'svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const composerContext = useComposerContext();
    const aiModelStore = useStore('ai-models');
    const {__} = useTranslator();

    interface Props {
        /** The tool-menu entry to configure. Must be the live entry from `ToolMenu`'s
         *  `filteredEntries`/`detailEntry` so `active`/`available` stay in sync while open. */
        entry: ToolMenuEntry;
    }

    const {
        entry
    }: Props = $props();

    const hasNativeCapability = $derived.by(() => {
        const tool = entry.tool;
        return tool.is_capability && tool.hasNativeCapabilityFor(composerContext.model.current);
    });

    const anyModelHasNativeCapability = $derived.by(() => {
        const tool = entry.tool;
        return tool.is_capability && aiModelStore.models.some(model => tool.hasNativeCapabilityFor(model));
    });

    const toolSelectOptions = $derived.by(() => {
        const tool = entry.tool;
        if (!tool.is_capability) {
            return [];
        }
        return tool.getTools().filter(t => aiModelStore.models.some(model => t.isAvailableFor(model)));
    });

    const isCapabilityWithMultipleTools = $derived.by(() => {
        if (!entry.tool.is_capability) {
            return false;
        }
        let count = toolSelectOptions.length;
        if (anyModelHasNativeCapability) {
            count++;
        }
        return count > 1;
    });

    const isAnyToolAvailableForCurrentModel = $derived.by(() => {
        if (!entry.tool.is_capability) {
            return false;
        }
        return toolSelectOptions.some(t => t.isAvailableFor(composerContext.model.current)) || (hasNativeCapability);
    });

    const show = $derived.by(() => {
        return isCapabilityWithMultipleTools;
    });

    const currentState = $derived(composerContext.tools.get(entry.tool, true));
    const currentToolSelectionString = $derived.by(() => {
        if (!currentState) {
            return 'auto';
        }
        if (typeof currentState.toolSelection === 'string') {
            return currentState.toolSelection;
        }

        return currentState.toolSelection?.name || 'auto';
    });

    type StatusTool = ComponentProps<typeof StatusDotForTool>['tool'];

    /** The list only surfaces exceptions: an option that is not online, or not supported. */
    function needsStatus(tool: StatusTool, supported: boolean): boolean {
        return !supported || tool.status !== 'online';
    }

    function handleToolSelectionChange(newValue: string) {
        const tool = entry.tool;
        if (!tool.is_capability) {
            return;
        }

        if (newValue === 'auto' || newValue === 'native') {
            composerContext.tools.set(tool, newValue);
            return;
        }

        const selectedTool = tool.getTools().find(t => t.name === newValue);
        if (!selectedTool) {
            console.warn(`Selected tool ${newValue} not found for capability ${tool.name}`);
            return;
        }

        composerContext.tools.set(tool, selectedTool, currentState?.toolSettings);
    }
</script>

{#snippet meta(tool: StatusTool, supported: boolean, info: string)}
    {#if needsStatus(tool, supported)}
        <StatusDotForTool {tool} {supported}/>
    {/if}
    <InfoPopover {info}/>
{/snippet}

{#if show}
    <DropdownMenuSeparator/>
    <DropdownMenuLabel>{__('chat.composer.toolMenuConfig.variantLabel')}</DropdownMenuLabel>
    <RadioRowGroup value={currentToolSelectionString} onChange={handleToolSelectionChange}>
        <RadioRow value="auto">
            {__('chat.composer.toolMenuConfig.autoLabel')}
            {#snippet trailing()}
                {@render meta(entry.tool, isAnyToolAvailableForCurrentModel, __('chat.composer.toolMenuConfig.autoInfo'))}
            {/snippet}
        </RadioRow>
        {#if anyModelHasNativeCapability}
            <RadioRow value="native">
                {__('chat.composer.toolMenuConfig.nativeLabel')}
                {#snippet trailing()}
                    {@render meta(entry.tool, hasNativeCapability, __('chat.composer.toolMenuConfig.nativeInfo'))}
                {/snippet}
            </RadioRow>
        {/if}
        {#each toolSelectOptions as option (option.name)}
            <RadioRow value={option.name}>
                {option.displayName}
                {#snippet trailing()}
                    {@render meta(option, option.isAvailableFor(composerContext.model.current), option.description)}
                {/snippet}
            </RadioRow>
        {/each}
    </RadioRowGroup>
{/if}

