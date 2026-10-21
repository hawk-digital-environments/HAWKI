<!--
  @component Detail panel for a single model, shown in place of the model list inside
  `ModelPicker`'s `DropdownMenuDetailView` when the user clicks a row's info icon — the
  model picker's counterpart to `ToolMenuDetail`/`AssistantMenuDetail`.

  A bar with the back button and a `MenuPinButton` (favourite), the model's `dense`
  `ModelCard`, and one full-width button that picks the model — disabled, and labelled as
  such, for the current model (or an offline one).

  Owns its own keyboard handling (`onDetailKeydown`) for the same reason the other detail
  panels do: it lives inside a bits-ui dropdown menu whose roving-tabindex model doesn't fit
  a sub-panel, so Escape/ArrowLeft close the detail (via `onCloseDetail`) and
  ArrowUp/ArrowDown/Tab move focus between this panel's own focusable children.

  ## Usage
  ```svelte
  <DropdownMenuDetailView open={!!detailModel}>
      {#snippet details()}
          {#if detailModel}
              <ModelMenuDetail model={detailModel} selected={…} onSelect={selectModel} onCloseDetail={closeModelDetail}/>
          {/if}
      {/snippet}
      …
  </DropdownMenuDetailView>
  ```
-->
<script lang="ts">
    import {onMount} from 'svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import ModelCard from '$plugins/core/components/ModelCard.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import MenuPinButton from '$plugins/core/modules/chat/components/composer/MenuPinButton.svelte';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const modelFavorites = useStore('model-favorites');
    const {__} = useTranslator();

    interface Props {
        /** The model to show details for. */
        model: AiModel;
        /** `true` when `model` is the currently selected model. */
        selected: boolean;
        /** Picks `model`; `ModelPicker` sets it and closes the menu. */
        onSelect: (model: AiModel) => void;
        /** Called when the user backs out of the detail view (Back button, Escape, or ArrowLeft). */
        onCloseDetail: () => void;
    }

    const {model, selected, onSelect, onCloseDetail}: Props = $props();

    const offline = $derived(model.status === 'offline');

    let detailEl = $state<HTMLDivElement | null>(null);
    let backEl = $state<HTMLButtonElement | null>(null);
    let selectEl = $state<HTMLButtonElement | null>(null);

    // Move focus into the panel when it opens, landing on the primary control. That button
    // sits at the bottom of a panel that may be height-capped, and a plain focus() would
    // scroll the viewport to it while the panel is still sliding in.
    onMount(() => {
        const raf = requestAnimationFrame(() => {
            (selectEl && !selectEl.disabled ? selectEl : backEl)?.focus({preventScroll: true});
        });
        return () => cancelAnimationFrame(raf);
    });

    function focusables(): HTMLElement[] {
        if (!detailEl) return [];
        return Array.from(detailEl.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], [tabindex="0"]'));
    }

    function moveFocus(direction: 1 | -1) {
        const items = focusables();
        if (items.length === 0) return;
        const current = items.indexOf(document.activeElement as HTMLElement);
        const next = (current + direction + items.length) % items.length;
        items[next].focus();
    }

    function onDetailKeydown(event: KeyboardEvent) {
        switch (event.key) {
            case 'Escape':
            case 'ArrowLeft':
                event.preventDefault();
                event.stopPropagation();
                onCloseDetail();
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
  Container-level keydown only delegates focus/back navigation to the child controls,
  which carry their own roles. It is not an interactive widget itself.
-->
<!-- svelte-ignore a11y_no_static_element_interactions -->
<div class="model-detail" bind:this={detailEl} onkeydown={onDetailKeydown}>
    <div class="model-detail-bar">
        <button
            type="button"
            class="model-detail-back"
            bind:this={backEl}
            onpointerdowncapture={(e) => e.stopPropagation()}
            onclick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onCloseDetail();
            }}>
            <ArrowLeft01Icon size={16}/>
            <span>{__('chat.composer.modelPicker.backButton')}</span>
        </button>
        <MenuPinButton
            variant="detail"
            pinned={modelFavorites.has(model.model_id)}
            onToggle={() => modelFavorites.toggle(model.model_id)}/>
    </div>

    <div class="model-detail-body">
        <ModelCard {model} dense/>
        <Button
            bind:ref={selectEl}
            size="xs"
            block
            disabled={selected || offline}
            onpointerdowncapture={(e) => e.stopPropagation()}
            onclick={() => onSelect(model)}>
            {selected ? __('chat.composer.modelPicker.currentModel') : __('chat.composer.modelPicker.useModel')}
        </Button>
    </div>
</div>

<style>
    .model-detail {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        min-width: 0;
        padding-bottom: var(--space-2, calc(0.25rem * 2));
    }

    .model-detail-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2, calc(0.25rem * 2));
    }

    .model-detail-back {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1, 0.25rem);
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

    .model-detail-body {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        padding-inline: var(--space-2, calc(0.25rem * 2));
    }
</style>
