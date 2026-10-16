<!--
  @component AI model selector for the composer. Wraps `SingleSelect` with a list built
  from the `ai-models` store (grouped by `model.provider.name`, offline models disabled),
  and renders each option with its `ModelDemandBars` load indicator and `StatusDotForModel`.

  Reads the current selection from `composerContext.model.current.model_id` and writes
  changes through `composerContext.model.set(newModelId)`, which resets sampling parameters
  to the new model's defaults unless the user had already customised them (see
  `ModelSlice.set`). Disabled whenever `composerContext.guard.disablesFeature('models')`
  is true (e.g. during edit mode or while a message is sending).

  Without props it is the self-contained composer feature. Passing `onSelect` detaches it
  from the composer context: the selection then comes from `model` (may be empty) and
  changes are reported to the callback — used by the assistant builder's `ModelSelector`.

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
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';
    import ModelDemandBars from '$plugins/core/modules/chat/components/composer/ModelDemandBars.svelte';
    import StatusDotForModel from '$plugins/core/modules/chat/components/composer/StatusDotForModel.svelte';

    interface Props {
        /** Selected model when used outside the composer (requires `onSelect`). */
        model?: AiModel | null;
        /** Receives the picked `model_id`. When set, the composer context is not used. */
        onSelect?: (modelId: string) => void;
        /** Disables the trigger; outside the composer this replaces the composer guard. */
        disabled?: boolean;
        /** Id for the trigger button, e.g. to associate a `<label>`. */
        id?: string;
        /** `pill` is the compact composer chip; `field` a full-width trigger styled like a form input. */
        variant?: 'pill' | 'field';
    }

    const {model, onSelect, disabled = false, id, variant = 'pill'}: Props = $props();

    // svelte-ignore state_referenced_locally
    const composerContext = onSelect ? null : useComposerContext();
    const current = $derived(composerContext ? composerContext.model.current : (model ?? null));
    const isDisabled = $derived(disabled || (composerContext?.guard.disablesFeature('models') ?? false));
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
        if (onSelect) {
            onSelect(newModelId);
            return;
        }
        composerContext?.model.set(newModelId);
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
    <span>{current?.label}</span>
{/snippet}

<Tooltip tooltip={__('chat.composer.modelPicker.switchModel')}>
    {#snippet children(a)}
        <SingleSelect
            bind:value={
                () => current?.model_id ?? '',
                (newValue) => handleModelChange(newValue)
                }
            disabled={isDisabled}
            items={selectItems}
            itemSnippet={itemSnippet}
            triggerValue={triggerValue}
            placeholder={__('chat.composer.modelPicker.placeholder')}
            onValueChange={handleModelChange}
            triggerProps={mergeProps(a.props, {
                id,
                class: ['chat-model-trigger', variant === 'field' && 'chat-model-trigger--field'],
                'aria-label': current
                    ? __('chat.composer.modelPicker.switchModelCurrent', {model: current.label})
                    : __('chat.composer.modelPicker.placeholder')
            })}
            contentProps={{class: ['chat-model-content', variant === 'field' && 'chat-model-content--field']}}
        />
    {/snippet}
</Tooltip>

<style>
    /* Combine with .select-trigger so these win over SingleSelect's own
       resting/hover backgrounds regardless of style injection order. */
    :global(.select-trigger.chat-model-trigger) {
        /* Pinned rather than left to the line box: the trigger's text sits at the inherited
           1.5 line-height, which lands a couple of pixels short of the assistant tags it
           shares the row with. Includes the border, since everything here is border-box. */
        height: var(--chat-composer-control-height, 2rem);
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

    /* `field` variant: sized and outlined like the form inputs/selects it sits
       next to, label left and chevron right. */
    :global(.select-trigger.chat-model-trigger.chat-model-trigger--field) {
        flex-direction: row-reverse;
        width: 100%;
        height: auto;
        min-height: 2.5rem;
        padding: var(--space-2) var(--space-2_5);
        border: var(--border);
        border-radius: var(--corner-md);
        background: transparent;
        font-size: var(--font-size-sm);
    }

    :global(.select-trigger.chat-model-trigger.chat-model-trigger--field:hover),
    :global(.select-trigger.chat-model-trigger.chat-model-trigger--field[data-state='open']) {
        background: transparent;
    }

    :global(.select-trigger.chat-model-trigger.chat-model-trigger--field:focus-visible) {
        border-color: var(--color-focus-ring);
        outline: 1px solid var(--color-focus-ring);
        outline-offset: 0;
    }

    :global(.select-trigger.chat-model-trigger.chat-model-trigger--field[disabled]) {
        opacity: 0.5;
    }

    /* The dropdown spans the field instead of hugging its longest label. */
    :global(.select-content.chat-model-content.chat-model-content--field.select-content--dropdown) {
        width: var(--bits-floating-anchor-width, max-content);
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
