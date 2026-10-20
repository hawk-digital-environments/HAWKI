<!--
  @component A single model row inside `ModelPickerV2` — the model picker's counterpart to
  `ToolMenuListItem`/`AssistantMenuListItem`, built on a `DropdownMenuRadioItem` instead of a
  checkbox because exactly one model is selected at a time. Must be rendered inside the
  picker's `DropdownMenuRadioGroup`, whose value is the current model id.

  Like the tool and assistant rows, it leads with a swatch — the provider logo
  (`ProviderSwatch`), which carries the check for the current model. Next to it is a single
  line with the model's label, plus its provider where no section label names it
  (`showProvider`, used for the favourites). Status and demand only appear
  when they deserve a look: a model whose status is unknown, or one under medium/high demand.
  Offline models are listed but disabled — greyed out, with no indicators.

  A favourite button (`MenuPinButton`, wired to the `model-favorites` store) and an info
  chevron are revealed on the row being pointed at or focused — in the mobile sheet, which has
  no hover, the chevron stays visible. The chevron — or ArrowRight — opens `ModelMenuDetail`
  instead of selecting the model, stopping propagation so it never also picks it.

  ## Usage
  ```svelte
  <DropdownMenuRadioGroup value={current?.model_id ?? ''} onValueChange={selectModel}>
      {#each models as model (model.model_id)}
          <ModelMenuListItem {model} selected={model.model_id === current?.model_id} onOpenDetail={openModelDetail}/>
      {/each}
  </DropdownMenuRadioGroup>
  ```
-->
<script lang="ts">
    import DropdownMenuRadioItem from '$lib/components/ui/dropdown-menu/DropdownMenuRadioItem.svelte';
    import ModelDemandBars from '$plugins/core/modules/chat/components/composer/ModelDemandBars.svelte';
    import StatusDotForModel from '$plugins/core/modules/chat/components/composer/StatusDotForModel.svelte';
    import MenuPinButton from '$plugins/core/modules/chat/components/composer/MenuPinButton.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import ProviderSwatch from '$plugins/core/modules/chat/components/composer/utils/ProviderSwatch.svelte';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const modelFavorites = useStore('model-favorites');
    const {__} = useTranslator();

    interface Props {
        /** The model this row represents. */
        model: AiModel;
        /** `true` when the row is the current model; its swatch then carries the check. */
        selected: boolean;
        /** Shows the provider next to the label, for sections that mix providers. */
        showProvider?: boolean;
        /** Called with `model` when the user clicks the info button or presses ArrowRight.
         *  `ModelPickerV2` uses this to open `ModelMenuDetail` for that model. */
        onOpenDetail?: (model: AiModel) => void;
    }

    const {model, selected, showProvider = false, onOpenDetail}: Props = $props();

    const offline = $derived(model.status === 'offline');
    const providerName = $derived(model.provider?.name ?? __('chat.composer.modelPicker.otherProvider'));

    // The list only surfaces exceptions; the detail view always shows both indicators. An
    // offline row is faded as disabled and also carries its red dot.
    const showStatus = $derived(model.status !== 'online');
    const showDemand = $derived(!offline && model.demand !== 'low');

    function openDetail(event: MouseEvent) {
        event.preventDefault();
        event.stopPropagation();
        onOpenDetail?.(model);
    }

    function onRowKeydown(event: KeyboardEvent) {
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            event.stopPropagation();
            onOpenDetail?.(model);
        }
    }
</script>

<DropdownMenuRadioItem
    value={model.model_id}
    disabled={offline}
    onkeydown={onRowKeydown}
    data-model-id={model.model_id}
    aria-keyshortcuts="ArrowRight"
    class="model-menu-item">
    <span class="model-item-main">
        <ProviderSwatch {model} checked={selected}/>
        <span class="model-item-name">
            <span class="model-item-label">{model.label}</span>
            {#if showProvider}
                <span class="model-item-provider">{providerName}</span>
            {/if}
        </span>
        {#if showDemand || showStatus}
            <span class="model-item-status">
                {#if showDemand}
                    <ModelDemandBars {model} focusable={false}/>
                {/if}
                {#if showStatus}
                    <StatusDotForModel {model} focusable={false}/>
                {/if}
            </span>
        {/if}
        <MenuPinButton
            pinned={modelFavorites.has(model.model_id)}
            onToggle={() => modelFavorites.toggle(model.model_id)}/>
        <button
            type="button"
            class="model-item-info"
            aria-label={__('chat.composer.modelPicker.infoDefault')}
            tabindex={-1}
            onpointerdown={(e) => e.stopPropagation()}
            onpointerup={(e) => e.stopPropagation()}
            onkeydown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.stopPropagation();
                }
            }}
            onclick={openDetail}>
            <ArrowRight01Icon size={16}/>
        </button>
    </span>
</DropdownMenuRadioItem>

<style>
    /* The check lives on the swatch, so drop the radio dot and its reserved padding. The
       left inset matches the vertical one, so the swatch sits evenly in the row's corner. */
    :global(.dropdown-radio-item.model-menu-item) {
        padding-left: var(--space-1_5);
    }

    /* Concentric with the row's highlight: its corner radius minus the inset, so the gap
       around the swatch stays even into the corner too. */
    :global(.dropdown-radio-item.model-menu-item .provider-swatch) {
        border-radius: calc(var(--corner-sm) - var(--space-1_5));
    }

    :global(.dropdown-radio-item.model-menu-item .dropdown-item-indicator) {
        display: none;
    }

    /* Disabled rows still take the pointer (bits-ui ignores their selection), so hovering
       one reveals its info chevron like any other row — the user can still read why. The
       doubled class outranks the radio item's own `pointer-events: none`. */
    :global(.dropdown-radio-item.model-menu-item.model-menu-item[data-disabled]) {
        pointer-events: auto;
        cursor: not-allowed;
    }

    /* A single-line row: the controls must not stand taller than the label, or they would
       stretch every row. */
    :global(.dropdown-radio-item.model-menu-item .menu-pin-button) {
        padding-block: 0;
    }

    /* In the dropdown the controls stay out of sight until the row is pointed at or focused —
       favourites included, since those already sit under their own section label. The current
       model keeps them shown, so its options are at hand without hunting for them. */
    :global(.dropdown-content--dropdown .dropdown-radio-item.model-menu-item .menu-pin-button),
    :global(.dropdown-content--dropdown .dropdown-radio-item.model-menu-item .model-item-info) {
        opacity: 0;
    }

    :global(.dropdown-content .dropdown-radio-item.model-menu-item:is(:hover, :focus-within, [data-highlighted], [data-state='checked']) .menu-pin-button),
    :global(.dropdown-content .dropdown-radio-item.model-menu-item:is(:hover, :focus-within, [data-highlighted], [data-state='checked']) .model-item-info) {
        opacity: 1;
    }

    .model-item-main {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        min-width: 0;
        flex: 1;
    }

    .model-item-name {
        display: flex;
        align-items: baseline;
        gap: var(--space-1_5);
        flex: 1;
        min-width: 0;
    }

    .model-item-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .model-item-provider {
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    .model-item-status {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        gap: var(--space-2);
    }

    .model-item-info {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        padding: 0;
        margin: 0;
        border: none;
        background: none;
        pointer-events: all;
        line-height: 0;
        color: var(--color-text-muted);
        cursor: pointer;
    }

    .model-item-info:hover {
        color: var(--color-text);
    }
</style>
