<!--
  @component Informational card for a single `AiModel`, laid out like a
  "spec sheet": a provider monogram, the model label and provider name, the
  localized description and a "capabilities" row of icon chips (vision, image
  generation, extended thinking, web search, file upload, …) plus flag badges
  from the `ai-model-flags` catalog. Next to it (below it on narrow cards) a
  tinted facts panel lists context window / max output (compact "922k"
  format), 1–5 cost/intelligence scales (see `modelInsights.ts`) and a link to
  the vendor documentation when the backend knows one.

  Purely presentational — takes the model as a prop; descriptions and the flag
  catalog come off the `ai-models` store (loaded during bootstrap). The
  side-by-side layout is driven by a container query on the card's own width,
  so the same component works in the narrow picker column and on the page.

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
    import type {Component} from 'svelte';
    import type {HugeiconsProps} from '@hugeicons/svelte';
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import Link from '$lib/components/util/link/Link.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import Attachment01Icon from '$lib/components/ui/icons/iconset/Attachment01Icon.svelte';
    import AiBrain01Icon from '$lib/components/ui/icons/iconset/AiBrain01Icon.svelte';
    import EyeIcon from '$lib/components/ui/icons/iconset/EyeIcon.svelte';
    import GlobeIcon from '$lib/components/ui/icons/iconset/GlobeIcon.svelte';
    import HeadphonesIcon from '$lib/components/ui/icons/iconset/HeadphonesIcon.svelte';
    import Image01Icon from '$lib/components/ui/icons/iconset/Image01Icon.svelte';
    import SourceCodeIcon from '$lib/components/ui/icons/iconset/SourceCodeIcon.svelte';
    import Video01Icon from '$lib/components/ui/icons/iconset/Video01Icon.svelte';
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
        /** Space-saving variant for the mobile sheet: header, chips and a single meta line — no description, no facts panel. */
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

    const CAPABILITY_ICONS: Record<string, Component<HugeiconsProps>> = {
        vision: EyeIcon,
        audio: HeadphonesIcon,
        video: Video01Icon,
        imageGeneration: Image01Icon,
        reasoning: AiBrain01Icon,
        webSearch: GlobeIcon,
        codeExecution: SourceCodeIcon,
        fileUpload: Attachment01Icon
    };

    const providerName = $derived(model.provider?.name ?? __('chat.composer.modelPicker.otherProvider'));

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

    // Capability chips derived from modalities, reasoning flags, native
    // capabilities and settings.
    const capabilityBadges = $derived.by(() => {
        const ids: string[] = [];
        const flags = model.flags ?? [];
        if (model.input.includes('image')) ids.push('vision');
        if (model.input.includes('audio')) ids.push('audio');
        if (model.input.includes('video')) ids.push('video');
        if (model.output.includes('image')) ids.push('imageGeneration');
        if (flags.some(flag => flag.startsWith('feature-reasoning-') && flag !== 'feature-reasoning-none')) ids.push('reasoning');
        const native = model.native_capabilities ?? [];
        if (native.includes('web_search')) ids.push('webSearch');
        if (native.includes('code_execution')) ids.push('codeExecution');
        if (model.settings?.['file_upload'] === true) ids.push('fileUpload');
        return ids.map(id => ({id, label: __(`ai.model.card.capability.${id}`), icon: CAPABILITY_ICONS[id]}));
    });

    const limits = $derived(getModelLimits(model));
    const priceTier = $derived(getModelPriceTier(model));
    const intelligenceTier = $derived(getModelIntelligenceTier(model));
    const htmlLang = $derived(app.localization.locale.htmlLang);

    const contextWindow = $derived(limits?.max_input_tokens ? formatTokenCount(limits.max_input_tokens, htmlLang) : null);
    const maxOutput = $derived(limits?.max_output_tokens ? formatTokenCount(limits.max_output_tokens, htmlLang) : null);
    const documentationUrl = $derived(model.documentation_url ?? null);
    const hasChips = $derived(flagBadges.length > 0 || capabilityBadges.length > 0);
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

{#snippet fact(label: string)}
    <dt class="model-card__eyebrow">{label}</dt>
{/snippet}

<article class="model-card" class:model-card--bordered={bordered} class:model-card--compact={compact}>
    <div class="model-card__body">
        <div class="model-card__main">
            <header class="model-card__header">
                <Avatar name={providerName} label={providerName} size={compact ? 32 : 40} variant="neutral" aria-hidden="true"/>
                <div class="model-card__title">
                    <h3>{model.label}</h3>
                    <span class="model-card__provider">{providerName}</span>
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

            {#if hasChips}
                <div class="model-card__capabilities">
                    {#if !compact}
                        <span class="model-card__eyebrow">{__('ai.model.card.capabilities')}</span>
                    {/if}
                    <ul class="model-card__chips">
                        {#each capabilityBadges as capability (capability.id)}
                            {@const Icon = capability.icon}
                            <li class="model-card__chip">
                                {#if Icon}
                                    <Icon size={14} aria-hidden="true"/>
                                {/if}
                                {capability.label}
                            </li>
                        {/each}
                        {#each flagBadges as flag (flag.id)}
                            <li>
                                <Tooltip tooltip={__(flag.description_label)}>
                                    {#snippet children(t)}
                                        <span
                                            class="model-card__chip"
                                            style={flag.color_code && FLAG_COLORS[flag.color_code]
                                                ? `color: ${FLAG_COLORS[flag.color_code]};`
                                                : undefined}
                                            {...t.props}
                                        >
                                            {__(flag.title_label)}
                                        </span>
                                    {/snippet}
                                </Tooltip>
                            </li>
                        {/each}
                    </ul>
                </div>
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
            {/if}
        </div>

        {#if !compact}
            <aside class="model-card__facts">
                <dl>
                    {#if contextWindow}
                        <div class="model-card__fact">
                            {@render fact(__('ai.model.card.contextWindow'))}
                            <dd>{contextWindow}</dd>
                        </div>
                    {/if}
                    {#if maxOutput}
                        <div class="model-card__fact">
                            {@render fact(__('ai.model.card.maxOutput'))}
                            <dd>{maxOutput}</dd>
                        </div>
                    {/if}
                    {#if priceTier !== null}
                        <div class="model-card__fact">
                            {@render fact(__('ai.model.card.price'))}
                            <dd>{@render scale(priceTier, '€', __('ai.model.card.price'))}</dd>
                        </div>
                    {/if}
                    <div class="model-card__fact">
                        {@render fact(__('ai.model.card.intelligence'))}
                        <dd>{@render scale(intelligenceTier, '●', __('ai.model.card.intelligence'))}</dd>
                    </div>
                </dl>
                {#if documentationUrl}
                    <Link href={documentationUrl} target="_blank" class="model-card__docs">
                        <span>{__('ai.model.card.documentation')}</span>
                        <ArrowRight01Icon size={16} aria-hidden="true"/>
                    </Link>
                {/if}
            </aside>
        {/if}
    </div>
</article>

<style>
    .model-card {
        /* The card queries its own width to decide between the stacked and
           the side-by-side layout (see `.model-card__body` below). */
        container-type: inline-size;
        display: flex;
        flex-direction: column;
        min-width: 0;
        /* Fill a height-constrained host (the picker's detail column) so the
           facts panel can sit on the bottom edge. */
        min-height: 100%;
    }

    .model-card--bordered {
        border: var(--border);
        border-radius: var(--corner-md);
        background: var(--color-surface-raised);
        padding: var(--space-5);
    }

    .model-card__body {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
        flex: 1;
        min-width: 0;
    }

    .model-card--compact .model-card__body {
        gap: var(--space-3);
    }

    .model-card__main {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
        flex: 1;
        min-width: 0;
    }

    .model-card--compact .model-card__main {
        gap: var(--space-2_5);
    }

    /* ── Header ───────────────────────────────────────────────────────── */

    .model-card__header {
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    .model-card__title {
        display: flex;
        flex-direction: column;
        gap: var(--space-0_5);
        flex: 1;
        min-width: 0;

        h3 {
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-bold, 700);
            line-height: var(--line-height-tight);
            letter-spacing: -0.01em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    }

    .model-card--compact .model-card__title h3 {
        font-size: var(--font-size-sm);
    }

    .model-card__provider {
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-semibold, 600);
        color: var(--color-accent-text);
    }

    .model-card__live {
        display: flex;
        align-items: center;
        align-self: flex-start;
        gap: var(--space-2);
        flex-shrink: 0;
    }

    /* ── Description & chips ──────────────────────────────────────────── */

    .model-card__description {
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }

    .model-card__eyebrow {
        display: block;
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-semibold, 600);
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--color-text-muted);
    }

    .model-card__capabilities {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .model-card__chips {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-1_5);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .model-card__chip {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1_5);
        padding: var(--space-1) var(--space-2_5);
        border-radius: var(--corner-xs);
        background: var(--color-highlight);
        color: var(--color-text);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium, 500);
        line-height: var(--line-height-tight);
        white-space: nowrap;

        :global(svg) {
            flex-shrink: 0;
            color: var(--color-text-muted);
        }
    }

    /* ── Compact meta line (mobile sheet) ─────────────────────────────── */

    .model-card__meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--space-1) var(--space-3);
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }

    /* ── Facts panel ──────────────────────────────────────────────────── */

    .model-card__facts {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
        /* Stacked layout: always on the card's bottom edge, whatever the
           amount of description/chips above. */
        margin-top: auto;
        padding: var(--space-4);
        border-radius: var(--corner-sm);
        background: var(--color-highlight);

        dl {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--space-4) var(--space-3);
            margin: 0;
        }

        dd {
            margin: 0;
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-bold, 700);
            line-height: var(--line-height-tight);
            color: var(--color-text);
        }
    }

    .model-card__fact {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        min-width: 0;
    }

    .model-card__facts :global(.model-card__docs) {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        margin-top: auto;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-semibold, 600);
        color: var(--color-accent-text);
        text-decoration: none;

        :global(svg) {
            flex-shrink: 0;
            transition: translate var(--duration-fast, 150ms) var(--easing-default);
        }

        &:hover :global(svg) {
            translate: var(--space-0_5) 0;
        }
    }

    /* ── Scales (€€€○○ / ●●●○○) ───────────────────────────────────────── */

    .model-card__scale {
        display: inline-flex;
        letter-spacing: 0.02em;
    }

    .model-card__scale-step {
        color: color-mix(in oklch, var(--color-text-muted) 40%, transparent);
    }

    .model-card__scale-step--active {
        color: var(--color-text);
    }

    /* ── Wide cards: facts panel beside the main column ───────────────── */

    @container (min-width: 26rem) {
        .model-card:not(.model-card--compact) .model-card__body {
            flex-direction: row;
            align-items: stretch;
        }

        .model-card:not(.model-card--compact) .model-card__facts {
            width: 11rem;
            flex-shrink: 0;
            margin-top: 0;

            dl {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    }
</style>
