<!--
  @component Experimental AI model selector for the composer (behind the
  `modelPickerV2` experiments flag; the stable counterpart is `ModelPicker`).

  Built from the same dropdown pattern as `ToolMenu` and `AssistantMenu`: a
  `DropdownMenu` (a bottom sheet below the `md` breakpoint) with a `MenuSearchField`
  on top and a two-panel `DropdownMenuDetailView` below. The list panel shows the
  models as one-line `ModelMenuListItem` radio rows — favourites lifted into their own
  section first, then one section per provider in store order; offline models are
  listed but disabled. Sections (favourites included) collapse from their header (a menu item, so the
  keyboard reaches it too); a search shows every match regardless. Each row's info icon (or ArrowRight) drills into `ModelMenuDetail`,
  which shows the model's `ModelCard`. A footer item opens the `/models` showcase.

  Reads models from the `ai-models` store, the current selection from
  `composerContext.model.current` and writes changes through
  `composerContext.model.set(modelId)` (same contract as `ModelPicker`).
  Favourite models are persisted per browser by the `model-favorites` store.

  Without props it is the self-contained composer feature. Passing `onSelect`
  detaches it from the composer context: the selection then comes from `model`
  (may be empty) and changes are reported to the callback — used by the
  assistant builder's `ModelSelector`, with `variant="field"`.

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
    import ProviderSwatch from '$plugins/core/modules/chat/components/composer/utils/ProviderSwatch.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuDetailView from '$lib/components/ui/dropdown-menu/DropdownMenuDetailView.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuRadioGroup from '$lib/components/ui/dropdown-menu/DropdownMenuRadioGroup.svelte';
    import MenuList from '$lib/components/ui/menu-list/MenuList.svelte';
    import ChevronDownIcon from '$lib/components/ui/icons/iconset/ChevronDownIcon.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import MenuSearchField from '$plugins/core/modules/chat/components/composer/MenuSearchField.svelte';
    import ModelMenuListItem from '$plugins/core/modules/chat/components/composer/ModelMenuListItem.svelte';
    import ModelMenuDetail from '$plugins/core/modules/chat/components/composer/ModelMenuDetail.svelte';
    import {useComposerContext} from './contexts/ComposerContext.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';

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

    const {model, onSelect, disabled: disabledProp = false, id, variant = 'pill'}: Props = $props();

    // svelte-ignore state_referenced_locally
    const composerContext = onSelect ? null : useComposerContext();
    const aiModelStore = useStore('ai-models');
    const modelFavorites = useStore('model-favorites');
    const app = useApp();
    const {__} = useTranslator();

    let open = $state(false);

    // Free-text filter over the list panel; cleared whenever the menu closes.
    let query = $state('');

    // When set, the picker shows the detail view for this model instead of the list.
    let detailModelId = $state<string | null>(null);

    // Sections the user folded away (provider ids, or `FAVORITES_GROUP`); kept while the
    // composer lives, so the menu reopens the way it was left.
    const FAVORITES_GROUP = '__favorites__';
    let collapsedGroups = $state<string[]>([]);

    const disabled = $derived(disabledProp || (composerContext?.guard.disablesFeature('models') ?? false));
    const current = $derived<AiModel | null>(composerContext ? composerContext.model.current : (model ?? null));

    const triggerLabel = $derived(current
        ? __('chat.composer.modelPicker.switchModelCurrent', {model: current.label})
        : __('chat.composer.modelPicker.placeholder'));

    // Rows are matched on what the user can see, plus the technical id people search by.
    const needle = $derived(query.trim().toLowerCase());
    const shownModels = $derived(needle
        ? aiModelStore.models.filter(m => [m.label, m.provider?.name, m.model_id]
            .some(text => text?.toLowerCase().includes(needle)))
        : aiModelStore.models);

    // Favourites in the order they were set — and dropped from their provider section,
    // so each model appears exactly once.
    const favoriteModels = $derived(modelFavorites.ids
        .map(modelId => shownModels.find(m => m.model_id === modelId))
        .filter((m): m is AiModel => !!m));

    interface ProviderGroup {
        id: string;
        label: string;
        models: AiModel[];
    }

    // The remaining models grouped by provider, in first-appearance (store) order.
    const providerGroups = $derived.by(() => {
        const groups = new Map<string, ProviderGroup>();
        for (const m of shownModels) {
            if (modelFavorites.has(m.model_id)) {
                continue;
            }
            const groupId = m.provider?.provider_id ?? '_other';
            let group = groups.get(groupId);
            if (!group) {
                group = {id: groupId, label: m.provider?.name ?? __('chat.composer.modelPicker.otherProvider'), models: []};
                groups.set(groupId, group);
            }
            group.models.push(m);
        }
        return [...groups.values()];
    });

    // A search shows every match, folded section or not.
    function isCollapsed(groupId: string): boolean {
        return !needle && collapsedGroups.includes(groupId);
    }

    function toggleGroup(groupId: string): void {
        collapsedGroups = collapsedGroups.includes(groupId)
            ? collapsedGroups.filter(id => id !== groupId)
            : [...collapsedGroups, groupId];
    }

    const detailModel = $derived(detailModelId ? aiModelStore.models.find(m => m.model_id === detailModelId) ?? null : null);

    function selectModel(modelId: string): void {
        const picked = aiModelStore.models.find(m => m.model_id === modelId);
        if (!picked || picked.status === 'offline') {
            return;
        }
        if (onSelect) {
            onSelect(picked.model_id);
        } else {
            composerContext?.model.set(picked.model_id);
        }
        open = false;
    }

    function openModelDetail(m: AiModel): void {
        detailModelId = m.model_id;
    }

    function closeModelDetail(): void {
        const modelId = detailModelId;
        detailModelId = null;
        if (!modelId) {
            return;
        }
        // Return focus to the row that opened the detail once the list re-renders.
        requestAnimationFrame(() => {
            document
                .querySelector<HTMLElement>(`.model-menu-content [data-model-id="${CSS.escape(modelId)}"]`)
                ?.focus();
        });
    }

    function openModelsDialog(): void {
        open = false;
        app.events.sync.triggerVoid('modelsRequested');
    }

    function currentRow(container: ParentNode): HTMLElement | null {
        return current
            ? container.querySelector<HTMLElement>(`[data-model-id="${CSS.escape(current.model_id)}"]:not([data-disabled])`)
            : null;
    }

    // The list opens on the current model: its row takes focus, centred in the scrolling
    // viewport. Two frames, so this lands after `MenuSearchField` claims focus on mount.
    // Falls back to the search field when the row is folded away or offline.
    $effect(() => {
        if (!open) {
            return;
        }
        let frame = requestAnimationFrame(() => {
            frame = requestAnimationFrame(() => {
                const row = currentRow(document.querySelector('.model-menu-content') ?? document);
                if (!row) {
                    return;
                }
                row.focus({preventScroll: true});
                const viewport = row.closest<HTMLElement>('.viewport');
                if (viewport) {
                    viewport.scrollTop += row.getBoundingClientRect().top - viewport.getBoundingClientRect().top
                        - (viewport.clientHeight - row.offsetHeight) / 2;
                }
            });
        });
        return () => cancelAnimationFrame(frame);
    });

    // Keyboard handling ahead of bits-ui's own (capture phase):
    // - Typing while a row has focus goes to the search field instead of bits-ui's
    //   typeahead, so the list still filters as you type.
    // - Arrowing out of the search field lands on the current model rather than the first
    //   row; falls through when that row is filtered out.
    function onContentKeydownCapture(event: KeyboardEvent): void {
        const content = event.currentTarget as HTMLElement;
        const inSearch = event.target instanceof HTMLInputElement;
        if (!inSearch && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey && event.key !== ' ') {
            const search = content.querySelector<HTMLInputElement>('.menu-search input');
            if (search) {
                // Focus moves before the key's default action, so the character lands in the field.
                event.stopPropagation();
                search.focus();
            }
            return;
        }
        if (event.key !== 'ArrowDown' || !inSearch) {
            return;
        }
        const row = currentRow(content);
        if (!row) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        row.focus();
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

{#snippet groupHeader(groupId: string, label: string, count: number)}
    {@const collapsed = isCollapsed(groupId)}
    <DropdownMenuItem
        class="mp2-group"
        closeOnSelect={false}
        aria-expanded={!collapsed}
        onSelect={() => toggleGroup(groupId)}>
        <ArrowRight01Icon size={14} class={`mp2-group-chevron${collapsed ? '' : ' mp2-group-chevron--open'}`}/>
        <span class="mp2-group-label">{label}</span>
        {#if collapsed}
            <span class="mp2-group-count">{count}</span>
        {/if}
    </DropdownMenuItem>
{/snippet}

{#if aiModelStore.models.length === 0}
    <span class="mp2-no-models">{__('chat.composer.modelPicker.placeholder')}</span>
{:else}
    <DropdownMenu
        {disabled}
        bind:open
        align="start"
        magneticHover={false}
        contentProps={{
            class: ['model-menu-content', variant === 'field' && 'model-menu-content--field'],
            onkeydowncapture: onContentKeydownCapture
        }}>
        {#snippet trigger({props})}
            <Tooltip tooltip={__('chat.composer.modelPicker.switchModel')}>
                {#snippet children(t)}
                    <button
                        type="button"
                        class="mp2-trigger chat-model-trigger"
                        class:mp2-trigger--field={variant === 'field'}
                        {id}
                        {disabled}
                        aria-label={triggerLabel}
                        {...mergeProps(props, t.props)}>
                        {#if current}
                            <ProviderSwatch model={current} size="sm" round/>
                        {/if}
                        <span class="mp2-trigger-label">{current?.label ?? __('chat.composer.modelPicker.placeholder')}</span>
                        <ChevronDownIcon size={14} class="mp2-trigger-chevron"/>
                    </button>
                {/snippet}
            </Tooltip>
        {/snippet}

        {#if !detailModel}
            <MenuSearchField
                bind:value={query}
                placeholder={__('chat.composer.modelPicker.searchPlaceholder')}/>
        {/if}

        <DropdownMenuDetailView open={!!detailModel}>
            {#snippet details()}
                {#if detailModel}
                    <ModelMenuDetail
                        model={detailModel}
                        selected={detailModel.model_id === current?.model_id}
                        onSelect={(m) => selectModel(m.model_id)}
                        onCloseDetail={closeModelDetail}/>
                {/if}
            {/snippet}

            {#if shownModels.length === 0}
                <p class="no-results">{__('chat.composer.modelPicker.noResults')}</p>
            {:else}
                <!-- Inside the scrolling viewport, so the sliding hover scrolls with the rows. -->
                <MenuList class="dropdown-menu-list" bridgeSections>
                    <DropdownMenuRadioGroup value={current?.model_id ?? ''} onValueChange={selectModel}>
                        {#if favoriteModels.length > 0}
                            {@render groupHeader(FAVORITES_GROUP, __('chat.composer.favorite.label'), favoriteModels.length)}
                            {#if !isCollapsed(FAVORITES_GROUP)}
                                {#each favoriteModels as m (m.model_id)}
                                    <ModelMenuListItem model={m} selected={m.model_id === current?.model_id} showProvider onOpenDetail={openModelDetail}/>
                                {/each}
                            {/if}
                        {/if}

                        {#each providerGroups as group (group.id)}
                            {@const collapsed = isCollapsed(group.id)}
                            {@render groupHeader(group.id, group.label, group.models.length)}
                            {#if !collapsed}
                                {#each group.models as m (m.model_id)}
                                    <ModelMenuListItem model={m} selected={m.model_id === current?.model_id} onOpenDetail={openModelDetail}/>
                                {/each}
                            {/if}
                        {/each}
                    </DropdownMenuRadioGroup>
                </MenuList>
            {/if}
        </DropdownMenuDetailView>

        {#if !detailModel}
            <div class="mp2-footer">
                <DropdownMenuItem class="mp2-all-models" onSelect={openModelsDialog}>
                    {__('chat.composer.modelPicker.allModelsLink')}
                </DropdownMenuItem>
            </div>
        {/if}
    </DropdownMenu>
{/if}

<style>
    /* ── Trigger ──────────────────────────────────────────────────────── */

    .mp2-trigger {
        display: flex;
        align-items: center;
        gap: var(--space-1_5);
        max-width: 13rem;
        min-width: 0;
        /* Pinned to the height the assistant tags and tool chips in the same row share. */
        height: var(--chat-composer-control-height, 2rem);
        font-size: var(--font-size-xs);
        color: var(--color-text);
        background: var(--color-surface-light);
        border: 1px solid transparent;
        border-radius: var(--corner-full);
        /* The provider tile sits close to the rounded start, the chevron gets more room. */
        padding-inline: var(--space-1) var(--space-2_5);
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

    .mp2-trigger[data-state='open'] :global(.mp2-trigger-chevron) {
        rotate: 180deg;
    }

    /* `field` variant: sized and outlined like the form inputs/selects it sits
       next to, chevron pushed to the far end. */
    .mp2-trigger--field {
        width: 100%;
        max-width: none;
        height: auto;
        min-height: 2.5rem;
        padding: var(--space-2) var(--space-2_5);
        border: var(--border);
        border-radius: var(--corner-md);
        background: transparent;
        font-size: var(--font-size-sm);

        &:hover:not([disabled]),
        &[data-state='open'] {
            background: transparent;
        }

        &:focus-visible {
            border-color: var(--color-focus-ring);
            outline: 1px solid var(--color-focus-ring);
            outline-offset: 0;
        }

        &[disabled] {
            opacity: 0.5;
        }
    }

    .mp2-trigger--field :global(.mp2-trigger-chevron) {
        margin-left: auto;
    }

    .mp2-no-models {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        padding-inline: var(--space-2);
    }

    /* ── Menu ─────────────────────────────────────────────────────────── */

    /*
      Match the tool and assistant pickers: one fixed width, and scrolling inside the
      animated detail-view viewport so the dropdown chrome stays anchored while the
      panels slide.
    */
    :global(.dropdown-content.dropdown-content--dropdown.model-menu-content) {
        width: calc(0.25rem * 80);
        max-height: min(28rem, calc(var(--bits-dropdown-menu-content-available-height, 999px) - var(--space-4)));
        max-width: calc(100vw - var(--space-8, calc(0.25rem * 8)));
        display: flex;
        flex-direction: column;
        overflow: hidden;

        /* The padding collides with the inner view container (overflow: hidden), so the
           inner view handles its own padding instead. */
        padding: 0;

        :global(.view) {
            padding: var(--space-1);
        }
    }

    /* Next to a form field the menu spans the field instead. */
    :global(.dropdown-content.dropdown-content--dropdown.model-menu-content.model-menu-content--field) {
        width: var(--bits-dropdown-menu-anchor-width, calc(0.25rem * 80));
    }

    /* Pinned below the scrolling list, set off by a divider so rows never run into it. */
    .mp2-footer {
        flex-shrink: 0;
        padding: var(--space-1);
        border-top: var(--border);
    }

    :global(.dropdown-item.mp2-all-models) {
        justify-content: center;
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
    }

    /* Section headers read like the menu's section labels (`DropdownMenuLabel`), with a
       chevron that turns down while the section is open. */
    :global(.dropdown-item.mp2-group) {
        gap: var(--space-1);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium, 500);
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--color-text-muted);
    }

    :global(.dropdown-item.mp2-group .mp2-group-chevron) {
        flex-shrink: 0;
        transition: rotate var(--duration-fast, 150ms) var(--easing-default);
    }

    :global(.dropdown-item.mp2-group .mp2-group-chevron--open) {
        rotate: 90deg;
    }

    .mp2-group-label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mp2-group-count {
        flex-shrink: 0;
        letter-spacing: normal;
    }

    .no-results {
        padding: var(--space-3);
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        text-align: center;
    }
</style>
