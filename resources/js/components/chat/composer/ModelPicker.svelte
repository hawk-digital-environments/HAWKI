<script lang="ts">

    import SingleSelect, {type ItemSnippetProps, type SelectItemDefinition} from '$lib/components/ui/select/SingleSelect.svelte';
    import ModelDemandBars from '$lib/components/chat/composer/ModelDemandBars.svelte';
    import {aiModelStore} from '$lib/stores/AiModelStore.svelte.js';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {mergeProps} from 'bits-ui';
    import StatusDotForModel from '$lib/components/chat/composer/StatusDotForModel.svelte';
    import {__} from '$lib/utils/translator.js';

    const composerContext = useComposerContext();

    const selectItems: Array<SelectItemDefinition> = $derived.by(() => {
        return Array.from(aiModelStore.models).map(model => ({
            value: model.model_id,
            label: model.label,
            groupLabel: model.provider!.name,
            disabled: model.status === 'offline'
        }));
    });

    function handleModelChange(newModelId: string) {
        composerContext.model.set(newModelId);
    }

</script>

{#snippet itemSnippet({item, selected}: ItemSnippetProps)}
    {@const m = aiModelStore.getOneById(item.value)!}
    <div class="model-row">
        <span class={{'model-selected': selected }}>
            {m.label}
        </span>
        <span class="model-load">
            {#if m.status !== 'offline'}
                <ModelDemandBars model={m}/>
            {/if}
            <StatusDotForModel model={m}/>
        </span>
    </div>
{/snippet}

{#snippet triggerValue()}
    <span>{composerContext.model.current.label}</span>
{/snippet}

<Tooltip tooltip={__('chat.composer.modelPicker.switchModel')}>
    {#snippet children(a)}
        <SingleSelect
            bind:value={
                () => composerContext.model.current.model_id,
                (newValue) => handleModelChange(newValue)
                }
            disabled={composerContext.guard.disablesFeature('models')}
            items={selectItems}
            itemSnippet={itemSnippet}
            triggerValue={triggerValue}
            placeholder={__('chat.composer.modelPicker.placeholder')}
            onValueChange={handleModelChange}
            triggerProps={mergeProps(a.props, {class: 'chat-model-trigger'})}
            contentProps={{class: 'chat-model-content'}}
        />
    {/snippet}
</Tooltip>

<style>
    :global(.chat-model-trigger) {
        gap: var(--space-0_5);
    }

    :global(.select-content.chat-model-content.select-content--dropdown) {
        max-height: min(24rem, calc(var(--bits-floating-available-height, 999px) - var(--space-4)));
        overflow-y: auto;
    }

    :global(.chat-model-content .select-item[data-highlighted]) {
        font-weight: inherit;
    }

    :global(.chat-model-content .select-item) {
        padding-block: var(--space-1_5);
        padding-right: var(--space-4);
    }

    :global(.chat-model-content.select-content--sheet .select-item) {
        padding-inline: var(--space-4);
        min-height: 2.5rem;
        font-size: var(--font-size-xs);
    }

    .model-row {
        display: flex;
        align-items: center;
        gap: calc(0.25rem * 2);
        width: 100%;
    }

    .model-selected {
        font-weight: var(--font-weight-medium, 500);
    }

    .model-load {
        display: flex;
        gap: var(--space-2);
        align-items: center;
        margin-left: auto;
        padding-left: var(--space-3, calc(0.25rem * 3));
    }
</style>
