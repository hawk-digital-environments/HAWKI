<script lang="ts">

    import type {ToolMenuEntry} from '$lib/components/chat/composer/ToolMenu.svelte';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import RadioCardGroup from '$lib/components/ui/radio-card/RadioCardGroup.svelte';
    import RadioCard from '$lib/components/ui/radio-card/RadioCard.svelte';
    import {aiModelStore} from '$lib/stores/AiModelStore.svelte.js';
    import StatusDotForTool from '$lib/components/chat/composer/StatusDotForTool.svelte';
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import InfoPopover from '$lib/components/ui/popover/InfoPopover.svelte';
    import {__} from '$lib/utils/translator.js';

    const composerContext = useComposerContext();

    interface Props {
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

        return currentState.toolSelection.name;
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
