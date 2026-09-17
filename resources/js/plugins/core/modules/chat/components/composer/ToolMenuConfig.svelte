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
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import RadioCardGroup from '$lib/components/ui/radio-card/RadioCardGroup.svelte';
    import RadioCard from '$lib/components/ui/radio-card/RadioCard.svelte';
    import StatusDotForTool from '$plugins/core/modules/chat/components/composer/StatusDotForTool.svelte';
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import InfoPopover from '$lib/components/ui/popover/InfoPopover.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const composerContext = useComposerContext();
    const aiModelStore = useStore('ai-models');
    const {__} = useTranslator();
    const uid = $props.id();
    const variantLabelId = `${uid}-variant-label`;

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
        <DropdownMenuLabel id={variantLabelId}>{__('chat.composer.toolMenuConfig.variantLabel')}</DropdownMenuLabel>
        <RadioCardGroup
            value={currentToolSelectionString}
            onChange={handleToolSelectionChange}
            aria-labelledby={variantLabelId}
        >
            <RadioCard value="auto">
                {__('chat.composer.toolMenuConfig.autoLabel')}
                {#snippet meta()}
                    <StatusDotForTool tool={entry.tool} supported={isAnyToolAvailableForCurrentModel}/>
                    <InfoPopover
                        label={__('chat.composer.toolMenuConfig.autoLabel')}
                        info={__('chat.composer.toolMenuConfig.autoInfo')}/>
                {/snippet}
            </RadioCard>
            {#if anyModelHasNativeCapability}
                <RadioCard value="native">
                    {__('chat.composer.toolMenuConfig.nativeLabel')}
                    {#snippet meta()}
                        <StatusDotForTool tool={entry.tool} supported={hasNativeCapability}/>
                        <InfoPopover
                            label={__('chat.composer.toolMenuConfig.nativeLabel')}
                            info={__('chat.composer.toolMenuConfig.nativeInfo')}/>
                    {/snippet}
                </RadioCard>
            {/if}
            {#each toolSelectOptions as option}
                <RadioCard value={option.name}>
                    {option.displayName}
                    {#snippet meta()}
                        <StatusDotForTool tool={option} supported={option.isAvailableFor(composerContext.model.current)}/>
                        <InfoPopover label={option.displayName} info={option.description}/>
                    {/snippet}
                </RadioCard>
            {/each}
        </RadioCardGroup>
    {/if}
{/if}
