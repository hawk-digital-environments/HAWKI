<!--
  @component Experimental AI model selector for the composer (behind the
  `modelPickerV2` experiments flag; the stable counterpart is `ModelPicker`).

  A richer picker in the style of a command palette: a provider tab rail
  (first tab: favorites), a search input that filters across all providers,
  a fixed-height result list and per-row extras (demand bars, status dot,
  favorite star, Ctrl+1..9 quick-select). Desktop renders a two-column
  popover, below the `md` breakpoint a bottom sheet with a horizontal
  provider pill row instead of the rail.

  Reads models from the `ai-models` store, the current selection from
  `composerContext.model.current` and writes changes through
  `composerContext.model.set(modelId)` (same contract as `ModelPicker`).
  Favorites are persisted per browser by the registered `model-favorites` store.

  Takes no props — it is a self-contained composer feature component.

  ## Usage
  Rendered by `ChatComposer.svelte` in the top-left of the composer card when
  the `modelPickerV2` experiment is enabled:
  ```svelte
  {#if experiments.isEnabled('modelPickerV2')}
      <ModelPickerV2/>
  {:else}
      <ModelPicker/>
  {/if}
  ```
-->
<script lang="ts">
    import {mergeProps} from 'bits-ui';
    import Popover from '$lib/components/ui/popover/Popover.svelte';
    import BottomSheet from '$lib/components/ui/sheet/BottomSheet.svelte';
    import Breakpoint from '$lib/components/util/breakpoints/Breakpoint.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import ChevronDownIcon from '$lib/components/ui/icons/iconset/ChevronDownIcon.svelte';
    import SearchIcon from '$lib/components/ui/icons/iconset/SearchIcon.svelte';
    import StarIcon from '$lib/components/ui/icons/iconset/StarIcon.svelte';
    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';
    import ModelDemandBars from '$plugins/core/modules/chat/components/composer/ModelDemandBars.svelte';
    import StatusDotForModel from '$plugins/core/modules/chat/components/composer/StatusDotForModel.svelte';
    import {useComposerContext} from './contexts/ComposerContext.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';

    const composerContext = useComposerContext();
    const aiModelStore = useStore('ai-models');
    const modelFavorites = useStore('model-favorites');
    const {__} = useTranslator();

    const FAVORITES_TAB = '__favorites__';
    const OTHER_TAB = '__other__';

    const uid = $props.id();
    const listId = `mp2-list-${uid}`;

    let open = $state(false);
    let query = $state('');
    let activeTab = $state<string>(FAVORITES_TAB);
    let highlightedId = $state<string | null>(null);
    let searchInputEl = $state<HTMLInputElement | null>(null);

    const disabled = $derived(composerContext.guard.disablesFeature('models'));
    const current = $derived(composerContext.model.current);

    // Unique providers in model order; models without a provider share the "other" tab.
    const providers = $derived.by(() => {
        const map = new Map<string, {id: string; label: string}>();
        for (const model of aiModelStore.models) {
            const id = model.provider?.provider_id ?? OTHER_TAB;
            if (!map.has(id)) {
                map.set(id, {id, label: model.provider?.name ?? __('chat.composer.modelPicker.otherProvider')});
            }
        }
        return [...map.values()];
    });

    const searching = $derived(query.trim().length > 0);

    // A non-empty query searches across ALL providers and overrides the tabs.
    const visibleModels = $derived.by(() => {
        if (searching) {
            const q = query.trim().toLowerCase();
            return aiModelStore.models.filter(model =>
                `${model.label} ${model.provider?.name ?? ''} ${model.model_id}`.toLowerCase().includes(q));
        }
        if (activeTab === FAVORITES_TAB) {
            return aiModelStore.models.filter(model => modelFavorites.has(model.model_id));
        }
        return aiModelStore.models.filter(model => (model.provider?.provider_id ?? OTHER_TAB) === activeTab);
    });

    const selectableModels = $derived(visibleModels.filter(model => model.status !== 'offline'));

    // Ctrl+N quick-select targets: the first 9 selectable visible rows.
    const kbdIndexById = $derived.by(() => {
        const map = new Map<string, number>();
        selectableModels.slice(0, 9).forEach((model, i) => map.set(model.model_id, i + 1));
        return map;
    });

    // Highlight resets to the first selectable row whenever the filtered list changes.
    $effect(() => {
        highlightedId = selectableModels[0]?.model_id ?? null;
    });

    // On open (popover and sheet share `open`): clear the query and land on the
    // favorites tab if any exist, otherwise on the current model's provider.
    let wasOpen = false;
    $effect(() => {
        const isOpen = open;
        if (isOpen && !wasOpen) {
            query = '';
            activeTab = modelFavorites.ids.length > 0
                ? FAVORITES_TAB
                : (current?.provider?.provider_id ?? providers[0]?.id ?? FAVORITES_TAB);
        }
        wasOpen = isOpen;
    });

    function optionDomId(modelId: string): string {
        return `mp2-opt-${uid}-${modelId.replace(/[^a-zA-Z0-9_-]/g, '_')}`;
    }

    function selectTab(id: string): void {
        activeTab = id;
        query = '';
        searchInputEl?.focus();
    }

    function selectModel(model: AiModel): void {
        if (model.status === 'offline') {
            return;
        }
        composerContext.model.set(model.model_id);
        open = false;
    }

    function toggleFavorite(e: Event, model: AiModel): void {
        // Keep the popover open and the row unselected.
        e.preventDefault();
        e.stopPropagation();
        modelFavorites.toggle(model.model_id);
    }

    function moveHighlight(delta: number): void {
        const list = selectableModels;
        if (list.length === 0) {
            return;
        }
        const index = list.findIndex(model => model.model_id === highlightedId);
        const next = index === -1 ? 0 : Math.min(Math.max(index + delta, 0), list.length - 1);
        highlightedId = list[next].model_id;
        requestAnimationFrame(() => {
            if (!highlightedId) {
                return;
            }
            document.getElementById(optionDomId(highlightedId))?.scrollIntoView({block: 'nearest'});
        });
    }

    function onPanelKeydown(e: KeyboardEvent): void {
        // Attached to both the panel and the popover content (in case focus sits on
        // the content itself), so skip keys the inner handler already processed.
        if (e.defaultPrevented) {
            return;
        }
        if (e.ctrlKey && /^[1-9]$/.test(e.key)) {
            const model = selectableModels[Number(e.key) - 1];
            if (model) {
                e.preventDefault();
                selectModel(model);
            }
            return;
        }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            moveHighlight(e.key === 'ArrowDown' ? 1 : -1);
            return;
        }
        if (e.key === 'Enter') {
            const model = selectableModels.find(m => m.model_id === highlightedId);
            if (model) {
                e.preventDefault();
                selectModel(model);
            }
        }
    }

    function handleOpenAutoFocus(e: Event): void {
        // bits-ui would focus the first rail tab; land on the search input instead.
        // Double rAF so the focus lands after bits-ui's own focus management settled.
        e.preventDefault();
        requestAnimationFrame(() => requestAnimationFrame(() => searchInputEl?.focus()));
    }

    function providerInitials(name: string): string {
        const words = name.trim().split(/\s+/).filter(Boolean);
        if (words.length >= 2) {
            return (words[0][0] + words[1][0]).toUpperCase();
        }
        return name.trim().slice(0, 2).toUpperCase();
    }
</script>

{#snippet triggerContent()}
    <span class="mp2-provider-chip" aria-hidden="true">
        {providerInitials(current.provider?.name ?? current.label)}
    </span>
    <span class="mp2-trigger-label">{current.label}</span>
    <ChevronDownIcon size={14} class="mp2-trigger-chevron"/>
{/snippet}

{#snippet searchBox()}
    <div class="mp2-search">
        <SearchIcon size={16} aria-hidden="true"/>
        <input
            bind:this={searchInputEl}
            bind:value={query}
            type="text"
            class="mp2-search-input"
            placeholder={__('chat.composer.modelPicker.searchPlaceholder')}
            aria-label={__('chat.composer.modelPicker.searchPlaceholder')}
            role="combobox"
            aria-expanded="true"
            aria-controls={listId}
            aria-activedescendant={highlightedId ? optionDomId(highlightedId) : undefined}
            autocomplete="off"
            spellcheck="false"
        />
    </div>
{/snippet}

{#snippet modelList(layout: 'popover' | 'sheet')}
    <div
        id={listId}
        class="mp2-list"
        role="listbox"
        aria-label={__('chat.composer.modelPicker.listAriaLabel')}
    >
        {#if visibleModels.length === 0}
            <div class="mp2-empty">
                {#if !searching && activeTab === FAVORITES_TAB}
                    {__('chat.composer.modelPicker.noFavorites')}
                {:else}
                    {__('chat.composer.modelPicker.noResults')}
                {/if}
            </div>
        {:else}
            {#each visibleModels as model (model.model_id)}
                {@const offline = model.status === 'offline'}
                {@const selected = model.model_id === current.model_id}
                {@const favorite = modelFavorites.has(model.model_id)}
                {@const kbdIndex = layout === 'popover' ? kbdIndexById.get(model.model_id) : undefined}
                <!-- Keyboard interaction lives on the panel (aria-activedescendant pattern),
                     so the option row itself only needs a click handler. -->
                <!-- svelte-ignore a11y_click_events_have_key_events -->
                <div
                    id={optionDomId(model.model_id)}
                    data-model-id={model.model_id}
                    class="mp2-row"
                    class:mp2-row--highlighted={model.model_id === highlightedId}
                    class:mp2-row--offline={offline}
                    role="option"
                    tabindex={-1}
                    aria-selected={selected}
                    aria-disabled={offline || undefined}
                    onclick={() => selectModel(model)}
                    onmouseenter={() => {
                        if (!offline) highlightedId = model.model_id;
                    }}
                >
                    <span class="mp2-row-text">
                        <span class="mp2-row-label">{model.label}</span>
                        <span class="mp2-row-provider">{model.provider?.name ?? __('chat.composer.modelPicker.otherProvider')}</span>
                    </span>
                    <span class="mp2-row-side">
                        {#if !offline}
                            <ModelDemandBars model={model} focusable={false}/>
                        {/if}
                        <StatusDotForModel model={model} focusable={false}/>
                        {#if selected}
                            <Tick02Icon size={16} class="mp2-row-check"/>
                        {/if}
                        {#if kbdIndex}
                            <kbd class="mp2-kbd">Ctrl+{kbdIndex}</kbd>
                        {/if}
                        <button
                            type="button"
                            class="mp2-star"
                            class:mp2-star--active={favorite}
                            aria-label={__(
                                favorite
                                    ? 'chat.composer.modelPicker.removeFavorite'
                                    : 'chat.composer.modelPicker.addFavorite',
                                {model: model.label}
                            )}
                            aria-pressed={favorite}
                            onmousedown={(e) => e.preventDefault()}
                            onclick={(e) => toggleFavorite(e, model)}
                        >
                            <StarIcon size={16}/>
                        </button>
                    </span>
                </div>
            {/each}
        {/if}
    </div>
{/snippet}

{#snippet panel(layout: 'popover' | 'sheet')}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div class={`mp2-panel mp2-panel--${layout}`} onkeydown={onPanelKeydown}>
        {#if layout === 'popover'}
            <div class="mp2-rail">
                <Tooltip tooltip={__('chat.composer.modelPicker.favoritesTab')} side="right" delayDuration={300}>
                    {#snippet children(t)}
                        <button
                            type="button"
                            class="mp2-tab"
                            class:mp2-tab--active={!searching && activeTab === FAVORITES_TAB}
                            aria-label={__('chat.composer.modelPicker.favoritesTab')}
                            aria-pressed={!searching && activeTab === FAVORITES_TAB}
                            {...mergeProps(t.props, {onclick: () => selectTab(FAVORITES_TAB)})}
                        >
                            <StarIcon size={18}/>
                        </button>
                    {/snippet}
                </Tooltip>
                {#each providers as provider (provider.id)}
                    <Tooltip tooltip={provider.label} side="right" delayDuration={300}>
                        {#snippet children(t)}
                            <button
                                type="button"
                                class="mp2-tab"
                                class:mp2-tab--active={!searching && activeTab === provider.id}
                                aria-label={__('chat.composer.modelPicker.providerTab', {provider: provider.label})}
                                aria-pressed={!searching && activeTab === provider.id}
                                {...mergeProps(t.props, {onclick: () => selectTab(provider.id)})}
                            >
                                <span class="mp2-provider-chip mp2-provider-chip--tab" aria-hidden="true">
                                    {providerInitials(provider.label)}
                                </span>
                            </button>
                        {/snippet}
                    </Tooltip>
                {/each}
            </div>
            <div class="mp2-main">
                {@render searchBox()}
                {@render modelList(layout)}
            </div>
        {:else}
            {@render searchBox()}
            <div class="mp2-pills">
                <button
                    type="button"
                    class="mp2-pill"
                    class:mp2-pill--active={!searching && activeTab === FAVORITES_TAB}
                    aria-pressed={!searching && activeTab === FAVORITES_TAB}
                    onclick={() => selectTab(FAVORITES_TAB)}
                >
                    <StarIcon size={14}/>
                    {__('chat.composer.modelPicker.favoritesTab')}
                </button>
                {#each providers as provider (provider.id)}
                    <button
                        type="button"
                        class="mp2-pill"
                        class:mp2-pill--active={!searching && activeTab === provider.id}
                        aria-pressed={!searching && activeTab === provider.id}
                        onclick={() => selectTab(provider.id)}
                    >
                        {provider.label}
                    </button>
                {/each}
            </div>
            {@render modelList(layout)}
        {/if}
    </div>
{/snippet}

{#if aiModelStore.models.length === 0}
    <span class="mp2-no-models">{__('chat.composer.modelPicker.placeholder')}</span>
{:else}
    <Breakpoint>
        {#snippet bpSmallerThanMd()}
            <button
                type="button"
                class="mp2-trigger chat-model-trigger"
                {disabled}
                aria-label={__('chat.composer.modelPicker.switchModel')}
                onclick={() => (open = true)}
            >
                {@render triggerContent()}
            </button>
            <BottomSheet bind:open title={__('chat.composer.modelPicker.switchModel')}>
                {@render panel('sheet')}
            </BottomSheet>
        {/snippet}
        {#snippet children()}
            <Popover
                bind:open
                side="top"
                align="start"
                contentProps={{class: 'mp2-content', onOpenAutoFocus: handleOpenAutoFocus, onkeydown: onPanelKeydown}}
            >
                {#snippet children({props})}
                    <Tooltip tooltip={__('chat.composer.modelPicker.switchModel')}>
                        {#snippet children(t)}
                            <button
                                type="button"
                                class="mp2-trigger chat-model-trigger"
                                class:mp2-trigger--open={open}
                                {disabled}
                                {...mergeProps(props, t.props)}
                            >
                                {@render triggerContent()}
                            </button>
                        {/snippet}
                    </Tooltip>
                {/snippet}
                {#snippet popover()}
                    {@render panel('popover')}
                {/snippet}
            </Popover>
        {/snippet}
    </Breakpoint>
{/if}

<style>
    /* ── Trigger ──────────────────────────────────────────────────────── */

    .mp2-trigger {
        display: flex;
        align-items: center;
        gap: var(--space-1_5);
        max-width: 13rem;
        min-width: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text);
        background: var(--color-surface-light);
        border: 1px solid transparent;
        border-radius: var(--corner-full);
        padding: var(--space-1) var(--space-2_5);
        white-space: nowrap;
        transition: background-color var(--duration-fast, 150ms) var(--easing-default);

        &:not([disabled]) {
            cursor: pointer;
        }

        &[disabled] {
            cursor: not-allowed;
            color: var(--color-text-muted);
        }

        &:hover:not([disabled]),
        &[data-state='open'] {
            background: var(--color-hover);
        }
    }

    .mp2-trigger-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mp2-trigger :global(.mp2-trigger-chevron) {
        flex-shrink: 0;
        color: var(--color-text-muted);
        transition: rotate var(--duration-fast, 150ms) var(--easing-default);
    }

    .mp2-trigger--open :global(.mp2-trigger-chevron) {
        rotate: 180deg;
    }

    .mp2-provider-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 1.25rem;
        height: 1.25rem;
        border-radius: var(--corner-sm);
        background: var(--color-bg-secondary);
        color: var(--color-text-muted);
        font-size: 0.55rem;
        font-weight: var(--font-weight-medium, 500);
        letter-spacing: 0.03em;
    }

    .mp2-no-models {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        padding-inline: var(--space-2);
    }

    /* ── Popover shell ────────────────────────────────────────────────── */

    /* Combined selector so this wins over the .popover-content defaults. */
    :global(.popover-content.mp2-content) {
        width: auto;
        padding: 0;
        overflow: hidden;
    }

    /* ── Panel ────────────────────────────────────────────────────────── */

    .mp2-panel--popover {
        display: flex;
        width: 24rem;
        max-width: calc(100vw - var(--space-8, calc(0.25rem * 8)));
    }

    .mp2-panel--sheet {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .mp2-rail {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        padding: var(--space-1_5);
        border-right: var(--border);
        overflow-y: auto;
    }

    .mp2-tab {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        flex-shrink: 0;
        border: none;
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-text-muted);
        cursor: pointer;
        transition: background-color var(--duration-fast, 150ms) var(--easing-default),
            color var(--duration-fast, 150ms) var(--easing-default);

        &:hover {
            color: var(--color-text);
        }
    }

    .mp2-tab--active {
        background: var(--color-hover);
        color: var(--color-text);
    }

    .mp2-provider-chip--tab {
        width: 1.5rem;
        height: 1.5rem;
        font-size: 0.625rem;
        background: var(--color-bg-secondary);
    }

    .mp2-tab--active .mp2-provider-chip--tab {
        color: var(--color-text);
    }

    .mp2-main {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-width: 0;
    }

    /* ── Search ───────────────────────────────────────────────────────── */

    .mp2-search {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding-inline: var(--space-3);
        border-bottom: var(--border);
        color: var(--color-text-muted);
    }

    .mp2-panel--sheet .mp2-search {
        border: var(--border);
        border-radius: var(--corner-md);
    }

    .mp2-search-input {
        flex: 1;
        min-width: 0;
        height: 2.25rem;
        border: none;
        background: transparent;
        color: var(--color-text);
        font-size: var(--font-size-xs);
        outline: none;

        &::placeholder {
            color: var(--color-text-muted);
        }
    }

    /* ── List ─────────────────────────────────────────────────────────── */

    .mp2-list {
        padding: var(--space-1);
        overflow-y: auto;
    }

    /* Fixed height on desktop so the popover does not resize while filtering. */
    .mp2-panel--popover .mp2-list {
        height: 18rem;
    }

    .mp2-panel--sheet .mp2-list {
        max-height: 50vh;
    }

    .mp2-empty {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        min-height: 6rem;
        padding: var(--space-4);
        text-align: center;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    .mp2-row {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-1_5) var(--space-2_5);
        border-radius: var(--corner-sm);
        cursor: pointer;
    }

    .mp2-row--highlighted {
        background: var(--color-hover);
    }

    .mp2-row--offline {
        cursor: not-allowed;

        .mp2-row-label,
        .mp2-row-provider {
            color: var(--color-text-muted);
        }
    }

    .mp2-row-text {
        display: flex;
        flex-direction: column;
        gap: var(--space-0_5);
        flex: 1;
        min-width: 0;
    }

    .mp2-row-label {
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium, 500);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mp2-row-provider {
        font-size: var(--font-size-xxs);
        color: var(--color-text-muted);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mp2-row-side {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        flex-shrink: 0;
    }

    .mp2-row-side :global(.mp2-row-check) {
        color: var(--color-text-muted);
    }

    .mp2-kbd {
        font-family: inherit;
        font-size: var(--font-size-xxs);
        color: var(--color-text-muted);
        border: var(--border);
        border-radius: var(--corner-sm);
        padding: 0 var(--space-1);
        line-height: 1.4;
        pointer-events: none;
        user-select: none;
    }

    .mp2-star {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: var(--space-0_5);
        border: none;
        border-radius: var(--corner-sm);
        background: transparent;
        color: color-mix(in oklch, var(--color-text-muted) 55%, transparent);
        cursor: pointer;
        transition: color var(--duration-fast, 150ms) var(--easing-default);

        &:hover {
            color: var(--color-text-muted);
        }
    }

    .mp2-star--active {
        color: var(--color-warning);

        &:hover {
            color: var(--color-warning);
        }

        :global(svg path) {
            fill: currentColor;
        }
    }

    /* ── Sheet provider pills ─────────────────────────────────────────── */

    .mp2-pills {
        display: flex;
        gap: var(--space-1_5);
        overflow-x: auto;
        padding-block: var(--space-0_5);
        scrollbar-width: none;
    }

    .mp2-pill {
        display: flex;
        align-items: center;
        gap: var(--space-1);
        flex-shrink: 0;
        border: var(--border);
        border-radius: var(--corner-full);
        background: transparent;
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
        padding: var(--space-1) var(--space-2_5);
        cursor: pointer;
    }

    .mp2-pill--active {
        background: var(--color-hover);
        color: var(--color-text);
    }
</style>
