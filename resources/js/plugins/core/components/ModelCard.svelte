<!--
  @component Informational card for a single `AiModel`: label + provider, live
  status/demand indicators, the localized description text, capability chips
  (Vision, web search, …) plus flag badges from the `ai-model-flags` catalog,
  and derived facts — context window / max output (compact "922k" format) and
  1–5 price/intelligence scales (see `modelInsights.ts`).

  Purely presentational — takes the model as a prop; descriptions and the flag
  catalog come off the `ai-models` store (loaded during bootstrap).

  ## Usage
  Rendered as the detail panel of `ModelPickerV2` (next to the model list on
  desktop; `compact` above the list in the mobile sheet) and as the grid cards
  on the `/models` showcase page:
  ```svelte
  <ModelCard model={model}/>            borderless, for embedding
  <ModelCard model={model} bordered/>   self-contained card, for the page
  <ModelCard model={model} compact/>    header + chips + one meta line only
  ```
-->
<script lang="ts">
    import Badge from '$lib/components/ui/badge/Badge.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import ModelDemandBars from '$plugins/core/modules/chat/components/composer/ModelDemandBars.svelte';
    import StatusDotForModel from '$plugins/core/modules/chat/components/composer/StatusDotForModel.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {formatTokenCount, getModelIntelligenceTier, getModelLimits, getModelPriceTier} from '$plugins/core/components/modelInsights.js';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';

    interface Props {
        /** The model to describe. */
        model: AiModel;
        /** Renders the card with its own border/background (e.g. on the models page). */
        bordered?: boolean;
        /** Space-saving variant for the mobile sheet: header, chips and a single meta line — no description, no facts grid. */
        compact?: boolean;
    }

    const {model, bordered = false, compact = false}: Props = $props();

    const app = useApp();
    const aiModelStore = useStore('ai-models');
    const {__} = useTranslator();

    // '@'-prefixed color codes from the flag catalog → CSS color tokens.
    const FLAG_COLORS: Record<string, string> = {
        '@success': 'var(--color-success)',
        '@warning': 'var(--color-warning)',
        '@error': 'var(--color-error)',
        '@highlight': 'var(--color-highlight)'
    };

    // Description in the UI locale, or the closest fallback: same language,
    // then English, then whatever has been authored at all.
    const description = $derived.by(() => {
        const candidates = (model.description ?? []).filter(entry => !!entry.description);
        const lang = app.localization.locale.lang;
        const language = lang.split('_')[0];
        const match = candidates.find(entry => entry.locale === lang)
            ?? candidates.find(entry => entry.locale.startsWith(language))
            ?? candidates.find(entry => entry.locale.startsWith('en'))
            ?? candidates[0];
        return match?.description ?? null;
    });

    // Only flags with a catalog entry become badges; purely technical flags
    // (feature-streaming, feature-reasoning-*, …) have none and stay hidden.
    const flagBadges = $derived(
        (model.flags ?? [])
            .map(flagId => aiModelStore.getFlagById(flagId))
            .filter(flag => flag !== null)
    );

    // Capability chips derived from modalities, native capabilities and settings.
    const capabilityBadges = $derived.by(() => {
        const ids: string[] = [];
        if (model.input.includes('image')) ids.push('vision');
        if (model.input.includes('audio')) ids.push('audio');
        if (model.input.includes('video')) ids.push('video');
        if (model.output.includes('image')) ids.push('imageGeneration');
        const native = model.native_capabilities ?? [];
        if (native.includes('web_search')) ids.push('webSearch');
        if (native.includes('code_execution')) ids.push('codeExecution');
        if (model.settings?.['file_upload'] === true) ids.push('fileUpload');
        return ids.map(id => ({id, label: __(`ai.model.card.capability.${id}`)}));
    });

    const limits = $derived(getModelLimits(model));
    const priceTier = $derived(getModelPriceTier(model));
    const intelligenceTier = $derived(getModelIntelligenceTier(model));
    const htmlLang = $derived(app.localization.locale.htmlLang);

    const contextWindow = $derived(limits?.max_input_tokens ? formatTokenCount(limits.max_input_tokens, htmlLang) : null);
    const maxOutput = $derived(limits?.max_output_tokens ? formatTokenCount(limits.max_output_tokens, htmlLang) : null);
</script>

{#snippet scale(tier: number, glyph: string, label: string)}
    <span
        class="model-card__scale"
        role="img"
        aria-label={`${label}: ${__('ai.model.card.outOfFive', {value: String(tier)})}`}
    >
        {#each Array(5) as _, i (i)}
            <span class="model-card__scale-step" class:model-card__scale-step--active={i < tier} aria-hidden="true">{glyph}</span>
        {/each}
    </span>
{/snippet}

<div class="model-card" class:model-card--bordered={bordered}>
    <header class="model-card__header">
        <div class="model-card__title">
            <h3>{model.label}</h3>
            <span class="model-card__provider">{model.provider?.name ?? __('chat.composer.modelPicker.otherProvider')}</span>
        </div>
        <div class="model-card__live">
            {#if model.status !== 'offline'}
                <ModelDemandBars {model} focusable={false}/>
            {/if}
            <StatusDotForModel {model} focusable={false}/>
        </div>
    </header>

    {#if description && !compact}
        <p class="model-card__description">{description}</p>
    {/if}

    {#if flagBadges.length > 0 || capabilityBadges.length > 0}
        <ul class="model-card__flags">
            {#each flagBadges as flag (flag.id)}
                <li>
                    <Tooltip tooltip={__(flag.description_label)}>
                        {#snippet children(t)}
                            <Badge
                                variant="outline"
                                style={flag.color_code && FLAG_COLORS[flag.color_code]
                                    ? `color: ${FLAG_COLORS[flag.color_code]}; border-color: currentColor;`
                                    : undefined}
                                {...t.props}
                            >
                                {__(flag.title_label)}
                            </Badge>
                        {/snippet}
                    </Tooltip>
                </li>
            {/each}
            {#each capabilityBadges as capability (capability.id)}
                <li><Badge variant="outline">{capability.label}</Badge></li>
            {/each}
        </ul>
    {/if}

    {#if compact}
        <p class="model-card__meta">
            {#if contextWindow}
                <span>{__('ai.model.card.contextShort', {count: contextWindow})}</span>
            {/if}
            {#if priceTier !== null}
                <span>{@render scale(priceTier, '€', __('ai.model.card.price'))}</span>
            {/if}
            <span>{@render scale(intelligenceTier, '●', __('ai.model.card.intelligence'))}</span>
        </p>
    {:else}
        <dl class="model-card__facts">
            {#if contextWindow}
                <dt>{__('ai.model.card.contextWindow')}</dt>
                <dd>{__('ai.model.card.tokens', {count: contextWindow})}</dd>
            {/if}
            {#if maxOutput}
                <dt>{__('ai.model.card.maxOutput')}</dt>
                <dd>{__('ai.model.card.tokens', {count: maxOutput})}</dd>
            {/if}
            {#if priceTier !== null}
                <dt>{__('ai.model.card.price')}</dt>
                <dd>{@render scale(priceTier, '€', __('ai.model.card.price'))}</dd>
            {/if}
            <dt>{__('ai.model.card.intelligence')}</dt>
            <dd>{@render scale(intelligenceTier, '●', __('ai.model.card.intelligence'))}</dd>
        </dl>
    {/if}
</div>

<style>
    .model-card {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        min-width: 0;
    }

    .model-card--bordered {
        border: var(--border);
        border-radius: var(--corner-md);
        background: var(--color-surface-raised);
        padding: var(--space-4);
    }

    .model-card__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: var(--space-2);
    }

    .model-card__title {
        display: flex;
        flex-direction: column;
        gap: var(--space-0_5);
        min-width: 0;

        h3 {
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium, 500);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    }

    .model-card__provider {
        font-size: var(--font-size-xxs);
        color: var(--color-text-muted);
    }

    .model-card__live {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        flex-shrink: 0;
        padding-top: var(--space-0_5);
    }

    .model-card__description {
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text);
    }

    .model-card__flags {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-1_5);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .model-card__meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--space-1) var(--space-3);
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }

    .model-card__facts {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: var(--space-1) var(--space-3);
        font-size: var(--font-size-xs);

        dt {
            color: var(--color-text-muted);
        }

        dd {
            margin: 0;
            min-width: 0;
        }
    }

    .model-card__scale {
        display: inline-flex;
        gap: var(--space-0_5);
        letter-spacing: 0.05em;
    }

    .model-card__scale-step {
        color: color-mix(in oklch, var(--color-text-muted) 40%, transparent);
    }

    .model-card__scale-step--active {
        color: var(--color-text);
    }
</style>
