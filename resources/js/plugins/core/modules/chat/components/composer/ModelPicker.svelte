<!--
  @component AI model selector for the composer, built from the same `DropdownMenu` family as
  its neighbours `AssistantMenu` and `ToolMenu`: a `layout="panel"` dropdown holding a
  `MenuSearchField` above a `DropdownMenuDetailView`, whose list panel is one
  `DropdownMenuRadioGroup` sectioned per provider into collapsible `DropdownMenuSection`s.
  The trigger is a `surface` `Button` in its `disclosure`/`truncate` shape — a pill naming
  the current model, with a chevron that flips while the menu is open.

  Picking a model is a single choice, hence radio rows rather than the checkbox rows the
  other two pickers use, with `indicator="check"` so the selected row is marked the way the
  rest of the menus mark theirs. Every row carries the model's `ModelDemandBars` load
  indicator and its `StatusDotForModel`; offline models are listed but not selectable.

  Pinned models (see the `composer-pins` store, toggled per row by `MenuPinButton`) are
  lifted into a "Pinned" section above the per-provider ones.

  Each row's info icon (or ArrowRight) drills into `ModelMenuDetail` in place of the list — a
  two-panel `DropdownMenuDetailView`, same as the assistant and tool pickers, and the same
  place a keyboard user reaches the pin.

  Each section is a `DropdownMenuSection`, so its header folds the rows away behind the
  family's grow transition and a long list of providers can be reduced to the one being
  used. Sections start expanded, the collapsed set lives as long as the composer, and a
  search overrides it — a hit inside a collapsed section would otherwise read as no hit at
  all.

  Reads the current selection from `composerContext.model.current.model_id` and writes
  changes through `composerContext.model.set(newModelId)`, which resets sampling parameters
  to the new model's defaults unless the user had already customised them (see
  `ModelSlice.set`). Disabled whenever `composerContext.guard.disablesFeature('models')`
  is true (e.g. during edit/regen mode or while a message is sending).

  Takes no props — it is a self-contained composer feature component, not a reusable primitive.

  ## Usage
  Rendered once by `ChatComposer.svelte` in the top-left of the composer card:
  ```svelte
  <div class="chat-composer-left">
      <ModelPicker/>
  </div>
  ```
-->
<script module lang="ts">
    /** Key of the pinned section in `collapsedSections`, kept out of the way of any
     *  provider name that could otherwise collide with it. */
    const PINNED_SECTION_KEY = '\u0000pinned';
</script>
<script lang="ts">

    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuRadioGroup from '$lib/components/ui/dropdown-menu/DropdownMenuRadioGroup.svelte';
    import DropdownMenuRadioItem from '$lib/components/ui/dropdown-menu/DropdownMenuRadioItem.svelte';
    import DropdownMenuDetailView from '$lib/components/ui/dropdown-menu/DropdownMenuDetailView.svelte';
    import DropdownMenuEmpty from '$lib/components/ui/dropdown-menu/DropdownMenuEmpty.svelte';
    import DropdownMenuSection from '$lib/components/ui/dropdown-menu/DropdownMenuSection.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import ChevronDownIcon from '$lib/components/ui/icons/iconset/ChevronDownIcon.svelte';
    import {useComposerContext} from './contexts/ComposerContext.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import MenuPinButton from '$plugins/core/modules/chat/components/composer/MenuPinButton.svelte';
    import ModelMenuDetail from '$plugins/core/modules/chat/components/composer/ModelMenuDetail.svelte';
    import MenuSearchField from '$plugins/core/modules/chat/components/composer/MenuSearchField.svelte';
    import ModelDemandBars from '$plugins/core/modules/chat/components/composer/ModelDemandBars.svelte';
    import StatusDotForModel from '$plugins/core/modules/chat/components/composer/StatusDotForModel.svelte';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';

    const composerContext = useComposerContext();
    const {__} = useTranslator();
    const aiModelStore = useStore('ai-models');
    const pinStore = useStore('composer-pins');

    // Bound so the query can be cleared on close; nothing outside opens this picker.
    let open = $state(false);

    // When set, the picker shows the detail view for this model instead of the list.
    let detailModelId = $state<string | null>(null);

    // Free-text filter over the list, cleared whenever the menu closes so it never reopens
    // on a stale query — same as the tool and assistant pickers.
    let query = $state('');

    // Rows are matched on what the user can see: the model's label and its provider.
    function matchesQuery(model: AiModel, needle: string): boolean {
        return [model.label, model.provider?.name]
            .some(text => text?.toLowerCase().includes(needle));
    }

    // The models the search field leaves standing, in store order.
    const shownModels = $derived.by(() => {
        const needle = query.trim().toLowerCase();
        return needle
            ? aiModelStore.models.filter(model => matchesQuery(model, needle))
            : [...aiModelStore.models];
    });

    // Pinned models first in their own section, then one section per provider — sections and
    // rows both sorted alphabetically, the grouping `SingleSelect` used to do internally.
    // Pinned models are dropped from their provider section, so each model is listed once.
    const sections = $derived.by(() => {
        const {pinned, rest} = pinStore.partition('model', shownModels, model => model.model_id);
        const byProvider = new Map<string, AiModel[]>();

        for (const model of rest) {
            const providerName = model.provider?.name ?? '?';
            if (!byProvider.has(providerName)) {
                byProvider.set(providerName, []);
            }
            byProvider.get(providerName)!.push(model);
        }

        const providers = Array.from(byProvider.entries())
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([label, models]) => ({
                label,
                models: models.sort((a, b) => a.label.localeCompare(b.label))
            }));

        return {pinned, providers};
    });

    const currentModelId = $derived(composerContext.model.current.model_id);

    function handleModelChange(newModelId: string) {
        // Re-picking the row that is already selected is a no-op: `set()` would otherwise
        // notify the legacy UI and re-run the parameter reset for no change at all.
        if (newModelId === currentModelId) {
            return;
        }
        composerContext.model.set(newModelId);
    }

    // Section keys the user has collapsed. A view preference rather than chat state, so it
    // lives with the component (surviving open/close) and not in a store.
    let collapsedSections = $state<string[]>([]);

    // While the search field is filtering, every section renders open regardless: a hit
    // hidden inside a collapsed section would look like no hit at all.
    function isExpanded(key: string): boolean {
        return query.trim().length > 0 || !collapsedSections.includes(key);
    }

    function toggleSection(key: string) {
        collapsedSections = collapsedSections.includes(key)
            ? collapsedSections.filter(entry => entry !== key)
            : [...collapsedSections, key];
    }

    // Read back out of the store so the panel follows a model whose status or demand
    // changes while it is open.
    const detailModel = $derived(
        detailModelId ? aiModelStore.models.find(model => model.model_id === detailModelId) ?? null : null
    );

    function openModelDetail(model: AiModel) {
        detailModelId = model.model_id;
    }

    function closeModelDetail() {
        const modelId = detailModelId;
        detailModelId = null;
        if (!modelId) {
            return;
        }
        // Return focus to the row that opened the detail once the list re-renders.
        requestAnimationFrame(() => {
            document
                .querySelector<HTMLElement>(`.model-menu-content [data-model-id="${modelId}"]`)
                ?.focus();
        });
    }

    function onRowKeydown(event: KeyboardEvent, model: AiModel) {
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            event.stopPropagation();
            openModelDetail(model);
        }
    }

    $effect(() => {
        if (!open) {
            query = '';
        }
    });

    // When closing the menu, the detail view is kept for a short delay to avoid flickering
    // mid-close; matches the tool and assistant pickers.
    $effect(() => {
        if (!open && detailModelId) {
            const t = setTimeout(() => {
                detailModelId = null;
            }, 200);
            return () => clearTimeout(t);
        }
    });

</script>

<DropdownMenu
    bind:open
    disabled={composerContext.guard.disablesFeature('models')}
    layout="panel"
    width="calc(0.25rem * 76)"
    maxHeight="24rem"
    contentProps={{class: 'model-menu-content'}}>
    {#snippet trigger({props})}
        <ButtonWithTooltip
            variant="surface"
            size="xs"
            iconLeft={ChevronDownIcon}
            iconSize={18}
            disclosure
            truncate
            tooltip={__('chat.composer.modelPicker.switchModel')}
            {...props}>
            {composerContext.model.current.label}
        </ButtonWithTooltip>
    {/snippet}

    {#if !detailModel}
        <MenuSearchField
            bind:value={query}
            placeholder={__('chat.composer.modelPicker.searchPlaceholder')}/>
    {/if}

    <DropdownMenuDetailView open={!!detailModel}>
        {#snippet details()}
            {#if detailModel}
                <ModelMenuDetail model={detailModel} onCloseDetail={closeModelDetail}/>
            {/if}
        {/snippet}

        {#if shownModels.length === 0}
            <DropdownMenuEmpty>{__('chat.composer.modelPicker.noResults')}</DropdownMenuEmpty>
        {:else}
            <!--
              One group across all sections: a pinned model is lifted out of its provider
              section rather than repeated, so every row is a distinct choice.
            -->
            <DropdownMenuRadioGroup
                bind:value={
                    () => currentModelId,
                    (newValue) => handleModelChange(newValue)
                    }>
                {#if sections.pinned.length > 0}
                    <DropdownMenuSection
                        label={__('chat.composer.pin.pinnedLabel')}
                        expanded={isExpanded(PINNED_SECTION_KEY)}
                        onToggle={() => toggleSection(PINNED_SECTION_KEY)}>
                        {#each sections.pinned as model (model.model_id)}
                            {@render modelRow(model)}
                        {/each}
                    </DropdownMenuSection>
                {/if}

                {#each sections.providers as provider (provider.label)}
                    <DropdownMenuSection
                        label={provider.label}
                        expanded={isExpanded(provider.label)}
                        onToggle={() => toggleSection(provider.label)}>
                        {#each provider.models as model (model.model_id)}
                            {@render modelRow(model)}
                        {/each}
                    </DropdownMenuSection>
                {/each}
            </DropdownMenuRadioGroup>
        {/if}
    </DropdownMenuDetailView>
</DropdownMenu>

{#snippet modelRow(model: AiModel)}
    {@const selected = model.model_id === currentModelId}
    <DropdownMenuRadioItem
        value={model.model_id}
        disabled={model.status === 'offline'}
        onkeydown={(event: KeyboardEvent) => onRowKeydown(event, model)}
        data-model-id={model.model_id}
        aria-keyshortcuts="ArrowRight"
        indicator="check">
        <span class={{'model-label': true, 'model-label--selected': selected}}>
            {model.label}
        </span>
        <MenuPinButton kind="model" id={model.model_id}/>
        <span class="model-load">
            {#if model.status !== 'offline'}
                <ModelDemandBars model={model}/>
            {/if}
            <StatusDotForModel model={model}/>
        </span>

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
            onclick={(event) => {
                event.preventDefault();
                event.stopPropagation();
                openModelDetail(model);
            }}>
            <ArrowRight01Icon size={16}/>
        </button>
    </DropdownMenuRadioItem>
{/snippet}

<style>
    /* Takes the row's free space, so the pin and the load indicators sit at the right edge
       and a long model name truncates rather than pushing them out. */
    .model-label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .model-label--selected {
        font-weight: var(--font-weight-medium, 500);
    }

    .model-load {
        display: flex;
        gap: var(--space-2_5);
        align-items: center;
    }

    /* Mirrors `.assistant-item-info`: a chevron that only opens the detail view. Rendered at
       full strength, with no hover state, so it reads as a plain affordance. */
    .model-item-info {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        padding: 6px 0;
        margin: 0;
        border: none;
        background: none;
        pointer-events: all;
        line-height: 0;
        color: var(--color-text);
        cursor: pointer;
    }
</style>
