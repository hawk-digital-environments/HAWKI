<!--
  @component AI model selector for the composer. Wraps `SingleSelect` with a list built
  from the `ai-models` store (grouped by `model.provider.name`, offline models disabled),
  and renders each option with its `ModelDemandBars` load indicator and `StatusDotForModel`.

  Reads the current selection from `composerContext.model.current.model_id` and writes
  changes through `composerContext.model.set(newModelId)`, which resets sampling parameters
  to the new model's defaults unless the user had already customised them (see
  `ModelSlice.set`). Disabled whenever `composerContext.guard.disablesFeature('models')`
  is true (e.g. during edit mode or while a message is sending).

  Takes no props — it is a self-contained composer feature component, not a reusable primitive.

  ## Usage
  Rendered once by `ChatComposer.svelte` in the top-left of the composer card:
  ```svelte
  <div class="chat-composer-left">
      <ModelPicker/>
  </div>
  ```
-->
<script lang="ts">

    import SingleSelect, {type ItemSnippetProps, type SelectItemDefinition} from '$lib/components/ui/select/SingleSelect.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {mergeProps} from 'bits-ui';
    import {useComposerContext} from './contexts/ComposerContext.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import ModelDemandBars from '$plugins/core/modules/chat/components/composer/ModelDemandBars.svelte';
    import StatusDotForModel from '$plugins/core/modules/chat/components/composer/StatusDotForModel.svelte';

    const composerContext = useComposerContext();
    const {__} = useTranslator();
    const aiModelStore = useStore('ai-models');

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
                <ModelDemandBars model={m} focusable={false}/>
            {/if}
            <StatusDotForModel model={m} focusable={false}/>
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
            triggerProps={mergeProps(a.props, {
                class: 'chat-model-trigger',
                'aria-label': __('chat.composer.modelPicker.switchModelCurrent', {model: composerContext.model.current.label})
            })}
            contentProps={{class: 'chat-model-content'}}
        />
    {/snippet}
</Tooltip>

<style>
    /* Combine with .select-trigger so these win over SingleSelect's own
       resting/hover backgrounds regardless of style injection order. */
    :global(.select-trigger.chat-model-trigger) {
        gap: var(--space-0_5);
        /* Lighter-than-surface neutral fill so the darker --color-hover below
           reads as a visible hover (the SingleSelect default is the slightly
           blue-tinted --color-bg-secondary). */
        background: var(--color-surface-light);
    }

    :global(.select-trigger.chat-model-trigger:hover),
    :global(.select-trigger.chat-model-trigger[data-state='open']) {
        background: var(--color-hover);
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
        gap: var(--space-2_5);
        align-items: center;
        margin-left: auto;
        padding-left: var(--space-3, calc(0.25rem * 3));
    }
</style>
