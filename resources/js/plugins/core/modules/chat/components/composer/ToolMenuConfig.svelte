<!--
  @component Capability-variant picker rendered inline inside `ToolMenuDetail`.

  Only renders anything for **capability** entries that have more than one way to be
  fulfilled (e.g. "web search" backed by two different MCP tools, or a capability the
  model can also do natively). It shows a `RadioCardGroup` with an "auto" option, an
  optional "native" option (when `entry.tool.hasNativeCapabilityFor` is true for some
  model), and one radio card per concrete tool that can fulfill the capability — each
  annotated with a `StatusDotForTool` reflecting whether it works with the currently
  selected `composerContext.model.current`.

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
    import {DropdownMenuLabel, DropdownMenuSeparator, InfoPopover, RadioCard, RadioCardGroup} from '@hawk-hhg/hawki-svelte-components';
    import StatusDotForTool from '$plugins/core/modules/chat/components/composer/StatusDotForTool.svelte';
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

{#if show}
    <DropdownMenuSeparator/>
    {#if isCapabilityWithMultipleTools}
        <DropdownMenuLabel>{__('chat.composer.toolMenuConfig.variantLabel')}</DropdownMenuLabel>
        <RadioCardGroup value={currentToolSelectionString} onChange={handleToolSelectionChange}>
            <RadioCard value="auto" class="tool-menu-config-select-item">
                {__('chat.composer.toolMenuConfig.autoLabel')}
                <span class="select-item-meta">
                    <StatusDotForTool tool={entry.tool} supported={isAnyToolAvailableForCurrentModel}/>
                    <InfoPopover info={__('chat.composer.toolMenuConfig.autoInfo')}/>
                </span>
            </RadioCard>
            {#if anyModelHasNativeCapability}
                <RadioCard
                    value="native" class="tool-menu-config-select-item">
                    {__('chat.composer.toolMenuConfig.nativeLabel')}
                    <span class="select-item-meta">
                        <StatusDotForTool tool={entry.tool} supported={hasNativeCapability}/>
                        <InfoPopover info={__('chat.composer.toolMenuConfig.nativeInfo')}/>
                    </span>
                </RadioCard>
            {/if}
            {#each toolSelectOptions as option}
                <RadioCard value={option.name} class="tool-menu-config-select-item">
                    {option.displayName}
                    <span class="select-item-meta">
                        <StatusDotForTool tool={option} supported={option.isAvailableFor(composerContext.model.current)}/>
                        <InfoPopover info={option.description}/>
                    </span>
                </RadioCard>
            {/each}
        </RadioCardGroup>
    {/if}
{/if}

<style>
    :global(.tool-menu-config-select-item .radio-card-body) {
        display: flex;
        justify-content: space-between;
        width: 100%;
    }

    .select-item-meta {
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }
</style>
