<!--
  @component Detail panel for a single model, shown in place of the model list inside
  `ModelPicker`'s `DropdownMenuDetailView` when the user clicks a row's info icon — the model
  picker's counterpart to `ToolMenuDetail` and `AssistantMenuDetail`.

  Shows the model's name and provider and a `MenuPinButton`, then the facts the row only
  hints at with its indicators: availability spelled out by `StatusDotForModel`, load by
  `ModelDemandBars`, and the id the provider knows the model by. Unlike the other two panels
  it carries no toggle — picking a model is the row's job, and a chat always has one, so
  there would be nothing to switch off.

  Owns its own keyboard handling (`onDetailKeydown`) for the same reason its two siblings do:
  the panel lives inside a bits-ui dropdown menu whose roving-tabindex model doesn't fit a
  sub-panel, so Escape/ArrowLeft close the detail (via `onCloseDetail`) and ArrowUp/ArrowDown/Tab
  move focus between Back and the pin. It is also what makes pinning reachable from the
  keyboard, since the pin on a row is deliberately not in the tab order.

  ## Usage
  Rendered by `ModelPicker` inside the `details` snippet of `DropdownMenuDetailView`, driven
  by which model's info icon was last clicked (`detailModelId`):
  ```svelte
  <DropdownMenuDetailView open={!!detailModel}>
      {#snippet details()}
          {#if detailModel}
              <ModelMenuDetail model={detailModel} onCloseDetail={closeModelDetail}/>
          {/if}
      {/snippet}
      …rows…
  </DropdownMenuDetailView>
  ```
-->
<script lang="ts">
    import {onMount} from 'svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import MenuPinButton from '$plugins/core/modules/chat/components/composer/MenuPinButton.svelte';
    import ModelDemandBars from '$plugins/core/modules/chat/components/composer/ModelDemandBars.svelte';
    import StatusDotForModel from '$plugins/core/modules/chat/components/composer/StatusDotForModel.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';

    const {__} = useTranslator();

    interface Props {
        /** The model to show details for. */
        model: AiModel;
        /** Called when the user backs out of the detail view (Back button, Escape, or ArrowLeft).
         *  `ModelPicker` clears `detailModelId` and returns focus to the row that opened it. */
        onCloseDetail: () => void;
    }

    const {model, onCloseDetail}: Props = $props();

    let detailEl = $state<HTMLDivElement | null>(null);
    let backEl = $state<HTMLButtonElement | null>(null);
    const offline = $derived(model.status === 'offline');

    // Move focus into the panel when it opens. Back is the way out and the way in: the pin
    // beside it is reached from there with the arrows this panel handles itself.
    onMount(() => {
        const raf = requestAnimationFrame(() => backEl?.focus());
        return () => cancelAnimationFrame(raf);
    });

    function focusables(): HTMLElement[] {
        if (!detailEl) return [];
        return Array.from(detailEl.querySelectorAll<HTMLElement>('button:not([disabled]), [tabindex="0"]'));
    }

    function moveFocus(direction: 1 | -1) {
        const items = focusables();
        if (items.length === 0) return;
        const currentIndex = items.indexOf(document.activeElement as HTMLElement);
        const next = (currentIndex + direction + items.length) % items.length;
        items[next].focus();
    }

    // The panel lives inside a bits-ui menu whose keyboard model (arrow roving,
    // Tab-to-close, Escape-to-close) doesn't fit a sub-panel. Stop the handled
    // keys from reaching it and drive focus/back navigation ourselves.
    function onDetailKeydown(event: KeyboardEvent) {
        switch (event.key) {
            case 'Escape':
            case 'ArrowLeft':
                event.preventDefault();
                event.stopPropagation();
                onCloseDetail?.();
                break;
            case 'ArrowDown':
                event.preventDefault();
                event.stopPropagation();
                moveFocus(1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                event.stopPropagation();
                moveFocus(-1);
                break;
            case 'Tab':
                event.preventDefault();
                event.stopPropagation();
                moveFocus(event.shiftKey ? -1 : 1);
                break;
        }
    }
</script>
<!--
  Container-level keydown only delegates focus/back navigation to the child
  buttons (Back, pin, select), which carry their own roles. It is not an
  interactive widget itself.
-->
<!-- svelte-ignore a11y_no_static_element_interactions -->
<div class="model-detail" bind:this={detailEl} onkeydown={onDetailKeydown}>
    <button
        type="button"
        class="model-detail-back"
        bind:this={backEl}
        onpointerdowncapture={(e) => e.stopPropagation()}
        onclick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            onCloseDetail?.();
        }}>
        <ArrowLeft01Icon size={16}/>
        <span>{__('chat.composer.modelPicker.backButton')}</span>
    </button>

    <div class="model-detail-header">
        <span class="model-detail-names">
            <span class="model-detail-name">{model.label}</span>
            {#if model.provider}
                <span class="model-detail-provider">{model.provider.name}</span>
            {/if}
        </span>
        <MenuPinButton kind="model" id={model.model_id} variant="detail"/>
    </div>

    <dl class="model-detail-facts">
        <dt>{__('chat.composer.modelPicker.statusLabel')}</dt>
        <dd><StatusDotForModel model={model} showLabel/></dd>

        {#if !offline}
            <dt>{__('chat.composer.modelPicker.demandLabel')}</dt>
            <dd><ModelDemandBars model={model} showLabel/></dd>
        {/if}

        <dt>{__('chat.composer.modelPicker.identifierLabel')}</dt>
        <dd><code>{model.model_id}</code></dd>
    </dl>
</div>

<style>
    .model-detail {
        display: flex;
        flex-direction: column;
        gap: var(--space-1_5);
        min-width: 0;
        padding-bottom: var(--space-2, calc(0.25rem * 2));
    }

    .model-detail-back {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1, 0.25rem);
        align-self: flex-start;
        margin-bottom: var(--space-1, 0.25rem);
        padding: var(--space-1, 0.25rem) var(--space-2, calc(0.25rem * 2));
        border: none;
        border-radius: var(--corner-sm);
        background: none;
        color: var(--color-text-muted, var(--color-text));
        font-size: var(--font-size-xs);
        cursor: pointer;
        transition: background-color var(--duration-fast, 150ms);
    }

    .model-detail-back:hover {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    .model-detail-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2, calc(0.25rem * 2));
        padding-inline: var(--space-2, calc(0.25rem * 2));
    }

    .model-detail-names {
        display: inline-flex;
        flex-direction: column;
        min-width: 0;
    }

    .model-detail-name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium, 500);
        color: var(--color-text);
    }

    .model-detail-provider {
        font-size: var(--font-size-xxs);
        color: var(--color-text-muted, var(--color-text));
    }

    /* Two columns of facts: the caption stays narrow, the value takes what is left, and each
       pair sits on its own line. */
    .model-detail-facts {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: var(--space-1) var(--space-3);
        align-items: center;
        margin: 0;
        padding-inline: var(--space-2, calc(0.25rem * 2));
        font-size: var(--font-size-xs);
    }

    .model-detail-facts dt {
        color: var(--color-text-muted, var(--color-text));
    }

    .model-detail-facts dd {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        min-width: 0;
        margin: 0;
    }

    .model-detail-facts code {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-family: var(--font-family-mono, monospace);
        font-size: var(--font-size-xxs);
        color: var(--color-text-muted, var(--color-text));
    }
</style>
