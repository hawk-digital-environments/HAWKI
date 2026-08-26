<!--
  @component Reasoning effort picker of the `SettingsMenu`: a menu row
  (`label · current value ›`) that opens a `SingleSelect` to the side, listing
  only the levels the current model advertises through its
  `feature-reasoning-*` flags (`modelParameters.supportedReasoningLevels`).
  The selected level is marked with a check icon.

  The list opens on hover and closes once the pointer has left both the row
  and the (portaled) option list; a short grace period lets the pointer cross
  the gap between the two. Click, keyboard, Escape and outside-click work as
  usual. Renders nothing when the model supports no adjustable level.
  Takes no props — reads/writes `ComposerContext` directly.
-->
<script lang="ts">
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import type {ReasoningLevel} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModelParameterSlice.svelte.js';
    import SingleSelect, {type ItemSnippetProps, type SelectItemDefinition} from '$lib/components/ui/select/SingleSelect.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import CheckIcon from '$lib/components/ui/icons/iconset/CheckIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const composerContext = useComposerContext();
    const {__} = useTranslator();

    const levelLabels: Record<ReasoningLevel, string> = {
        none: __('chat.composer.settings.reasoningLevelNone'),
        minimal: __('chat.composer.settings.reasoningLevelMinimal'),
        low: __('chat.composer.settings.reasoningLevelLow'),
        medium: __('chat.composer.settings.reasoningLevelMedium'),
        high: __('chat.composer.settings.reasoningLevelHigh'),
        xhigh: __('chat.composer.settings.reasoningLevelXhigh'),
        max: __('chat.composer.settings.reasoningLevelMax')
    };

    const items = $derived<SelectItemDefinition[]>(
        composerContext.modelParameters.supportedReasoningLevels.map(level => ({value: level, label: levelLabels[level]}))
    );
    const effort = $derived.by(() => composerContext.modelParameters.reasoningEffort);

    function handleChange(value: string) {
        if (value) composerContext.modelParameters.set('reasoning_effort', value as ReasoningLevel);
    }

    let open = $state(false);
    let closeTimer: ReturnType<typeof setTimeout> | null = null;

    function openOnHover() {
        cancelClose();
        open = true;
    }

    function scheduleClose() {
        cancelClose();
        closeTimer = setTimeout(() => (open = false), 150);
    }

    function cancelClose() {
        if (closeTimer !== null) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
    }
</script>

{#snippet item({item, selected}: ItemSnippetProps)}
    <span class="reasoning-item">
        <span>{item.label}</span>
        {#if selected}
            <CheckIcon size={14}/>
        {/if}
    </span>
{/snippet}

{#if items.length > 0}
    <SingleSelect
        bind:open
        bind:value={() => effort ?? '', handleChange}
        {items}
        itemSnippet={item}
        placeholder={__('chat.composer.settings.reasoningHeading')}
        contentProps={{
            side: 'right',
            align: 'start',
            sideOffset: 12,
            class: 'reasoning-select-content',
            onpointerenter: cancelClose,
            onpointerleave: scheduleClose
        }}
    >
        {#snippet trigger({props})}
            <button
                type="button"
                class="reasoning-row"
                aria-label={__('chat.composer.settings.reasoningAriaLabel')}
                onpointerenter={openOnHover}
                onpointerleave={scheduleClose}
                {...props}
            >
                <span class="reasoning-row-label">{__('chat.composer.settings.reasoningHeading')}</span>
                <span class="reasoning-row-value">
                    {effort ? levelLabels[effort] : __('chat.composer.settings.reasoningDefault')}
                </span>
                <ArrowRight01Icon size={14} class="reasoning-row-chevron"/>
            </button>
        {/snippet}
    </SingleSelect>
{/if}

<style>
    .reasoning-row {
        display: flex;
        align-items: center;
        gap: var(--space-2, calc(0.25rem * 2));
        width: 100%;
        box-sizing: border-box;
        padding: var(--space-1_5) var(--space-2, calc(0.25rem * 2));
        border: none;
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-text);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        cursor: pointer;
        text-align: left;
        transition: background-color var(--duration-fast, 150ms);
    }

    .reasoning-row:hover,
    .reasoning-row[data-state="open"] {
        background-color: var(--color-hover);
    }

    .reasoning-row-label {
        flex: 1;
    }

    .reasoning-row-value,
    :global(.reasoning-row-chevron) {
        color: var(--color-text-muted);
    }

    :global(.reasoning-select-content) {
        min-width: calc(0.25rem * 40);
    }

    .reasoning-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-4, calc(0.25rem * 4));
        width: 100%;
    }
</style>
