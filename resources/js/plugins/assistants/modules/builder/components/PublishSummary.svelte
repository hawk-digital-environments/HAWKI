<!--
  @component Bento summary of the publish step: three equal tiles (readiness,
  release status, risk) and a full-width checklist cell whose rows jump to the
  builder step that owns each requirement.

  Every tile shares one rhythm — small title and marker on top, value and a
  one-line hint at the bottom. HAWKI indigo carries the surfaces; status
  colour is kept to small marks, and only open items are loud.
-->
<script lang="ts">
    import {fly} from 'svelte/transition';
    import {cubicOut} from 'svelte/easing';
    import {Tween, prefersReducedMotion} from 'svelte/motion';
    import {useBuilderContext} from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import type {CheckItem} from '$plugins/assistants/modules/builder/contexts/builderValidationRules';
    import {ReleaseMode} from '$plugins/assistants/types/assistant/ReleaseMode';
    import {ReviewStage} from '$plugins/assistants/types/assistant/ReviewStage';
    import {ValidationState} from '$plugins/assistants/types/enums/ValidationState';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import InfoPopover from '$lib/components/ui/popover/InfoPopover.svelte';
    import RadialProgress from '$lib/components/ui/radial-progress/RadialProgress.svelte';
    import type {IconComponent} from '$lib/components/ui/icons';
    import TaskEdit01Icon from '$lib/components/ui/icons/iconset/TaskEdit01Icon.svelte';
    import SquareLock02Icon from '$lib/components/ui/icons/iconset/SquareLock02Icon.svelte';
    import CheckmarkBadge01Icon from '$lib/components/ui/icons/iconset/CheckmarkBadge01Icon.svelte';
    import GlobeIcon from '$lib/components/ui/icons/iconset/GlobeIcon.svelte';
    import ShieldCheckIcon from '$lib/components/ui/icons/iconset/ShieldCheckIcon.svelte';
    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';
    import Alert01Icon from '$lib/components/ui/icons/iconset/Alert01Icon.svelte';
    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    import CircleIcon from '$lib/components/ui/icons/iconset/CircleIcon.svelte';
    import CheckListIcon from '$lib/components/ui/icons/iconset/CheckListIcon.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';

    interface Props {
        /** Also list the review triggers (release paths that start a review). */
        showTriggers?: boolean;
    }

    const {showTriggers = false}: Props = $props();
    const {__} = useTranslator();
    const builder = useBuilderContext();
    const router = useRouter();

    const releaseIcons: Record<ReleaseMode, IconComponent> = {
        [ReleaseMode.DRAFT]: TaskEdit01Icon,
        [ReleaseMode.PRIVATE]: SquareLock02Icon,
        [ReleaseMode.ORGANIZATIONAL]: CheckmarkBadge01Icon,
        [ReleaseMode.FEDERATED]: GlobeIcon,
    };
    const checkIcons: Partial<Record<ValidationState, IconComponent>> = {
        [ValidationState.SAFE]: Tick02Icon,
        [ValidationState.WARNING]: Alert01Icon,
        [ValidationState.ERROR]: AlertCircleIcon,
    };

    let checks = $derived(builder.validator.completeness);
    let done = $derived(checks.filter(c => c.ok).length);
    let open = $derived(checks.length - done);

    // A review verdict outranks the chosen release mode. The key doubles as
    // the translation key for label and hint.
    let review = $derived(builder.draft.review ?? null);
    let status = $derived.by(() => {
        if (review?.status === ReviewStage.DENIED) {
            return 'denied';
        }
        if (review?.status === ReviewStage.NEEDS_REVISION) {
            return 'needs_revision';
        }
        return builder.draft.releaseStage as string;
    });
    let StatusIcon = $derived(releaseIcons[builder.draft.releaseStage]);

    // The score counts up on mount and rolls on every change.
    const score = new Tween(0, {easing: cubicOut});
    $effect(() => {
        score.set(done, {duration: prefersReducedMotion.current ? 0 : 700});
    });

    const swap = () => ({y: 6, duration: prefersReducedMotion.current ? 0 : 280, easing: cubicOut});

    function openStep(item: CheckItem) {
        if (item.step) void router.goToRoute(`assistants.builder.${item.step}`);
    }
</script>

{#snippet checklist(title: string, items: CheckItem[])}
    <div class="cell checks">
        <p class="cell-title checks-title">{title}</p>
        <ul class="rows">
            {#each items as item (item.id)}
                {@const Icon = checkIcons[item.status] ?? CircleIcon}
                {@const meta = item.step ? item.group : item.description}
                <li data-tone={item.status} class:open={!item.ok}>
                    <svelte:element
                        this={item.step ? 'button' : 'div'}
                        role={item.step ? undefined : 'group'}
                        type={item.step ? 'button' : undefined}
                        class="row"
                        onclick={item.step ? () => openStep(item) : undefined}
                    >
                        <span class="mark" aria-hidden="true"><Icon size="1em"/></span>
                        <span class="row-label">{item.label}</span>
                        {#if meta}<span class="row-meta">{meta}</span>{/if}
                        {#if item.step}
                            <span class="go" aria-hidden="true"><ArrowRight01Icon size="1em"/></span>
                        {/if}
                    </svelte:element>
                </li>
            {/each}
        </ul>
    </div>
{/snippet}

<section class="bento" aria-label={__('assistants.builder.publish.risk.title')}>
    <!-- Readiness -->
    <div class="cell tile readiness">
        <span class="glyph" aria-hidden="true"><CheckListIcon size="100%"/></span>
        <div class="tile-head">
            <span class="cell-title">{__('assistants.builder.publish.completeness.label')}</span>
            <RadialProgress
                value={checks.length ? (score.current / checks.length) * 100 : 0}
                size={20}
                strokeWidth={2.5}
                aria-hidden="true"/>
        </div>
        <div class="tile-body">
            <p class="tile-value score" aria-label="{done}/{checks.length}">
                {Math.round(score.current)}<span class="score-total">/{checks.length}</span>
            </p>
            <p class="tile-hint">
                {open === 0
                    ? __('assistants.builder.publish.completeness.ready')
                    : __('assistants.builder.publish.completeness.open', {count: String(open)})}
            </p>
        </div>
    </div>

    <!-- Status -->
    <div class="cell tile">
        {#key builder.draft.releaseStage}
            <span class="glyph" aria-hidden="true" in:fly={swap()}><StatusIcon size="100%"/></span>
        {/key}
        <div class="tile-head">
            <span class="cell-title">{__('assistants.builder.publish.risk.label_status')}</span>
        </div>
        <div class="tile-body stack">
            {#key status}
                <div in:fly={swap()}>
                    <p class="tile-value">{__(`assistants.builder.publish.status.${status}`)}</p>
                    <p class="tile-hint">{__(`assistants.builder.publish.status_hint.${status}`)}</p>
                </div>
            {/key}
        </div>
    </div>

    <!-- Risk -->
    <div class="cell tile">
        <span class="glyph" aria-hidden="true"><ShieldCheckIcon size="100%"/></span>
        <div class="tile-head">
            <span class="cell-title">
                {__('assistants.builder.publish.risk.label_risk_level')}
                <InfoPopover
                    label={__('assistants.builder.publish.risk.label_risk_level')}
                    info={__('assistants.builder.publish.risk.description')}/>
            </span>
        </div>
        <div class="tile-body">
            <p class="tile-value">{__('assistants.builder.publish.risk.risk_level_low')}</p>
            <p class="tile-hint">{__('assistants.builder.publish.risk.hint')}</p>
        </div>
    </div>

    {@render checklist(__('assistants.builder.publish.completeness.title'), checks)}

    {#if showTriggers}
        {@render checklist(__('assistants.builder.publish.triggers.title'), builder.validator.triggers)}
    {/if}
</section>

<style>
    /* Fresh, luminous blues: the brand indigo tipped toward sky blue.
       The readiness tile carries the only gradient; everything else is flat. */
    .bento {
        --hero-from: oklch(56% 0.23 268);
        --hero-to: oklch(70% 0.16 238);
        --wash: oklch(96.5% 0.025 255);
        --wash-glyph: oklch(92% 0.045 252);
        --wash-ink: oklch(32% 0.13 262);
        --wash-ink-soft: oklch(50% 0.11 258);
        --inset: var(--space-2);

        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: var(--space-2);
    }

    :global(html.darkMode) .bento {
        --hero-from: oklch(46% 0.2 268);
        --hero-to: oklch(58% 0.15 238);
        --wash: oklch(26% 0.04 262);
        --wash-glyph: oklch(31% 0.06 258);
        --wash-ink: oklch(93% 0.04 255);
        --wash-ink-soft: oklch(76% 0.07 255);
    }

    [data-tone] { --tone: var(--color-text-muted); }
    [data-tone='info'] { --tone: var(--color-accent-text); }
    [data-tone='safe'] { --tone: var(--color-success); }
    [data-tone='warning'] { --tone: var(--color-warning); }
    [data-tone='error'] { --tone: var(--color-error); }

    .cell {
        min-width: 0;
        border-radius: var(--corner-lg);
    }
    .cell-title {
        display: flex;
        align-items: center;
        gap: var(--space-1);
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
    p { margin: 0; }

    /* ── Tiles ────────────────────────────────────────────────────────── */
    .tile {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: var(--space-1);
        position: relative;
        overflow: hidden;
        padding: var(--space-4);
        background: var(--wash);
    }
    .tile-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2);
    }
    .tile-body {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }
    /* Status value and hint swap together in one grid slot. */
    .stack { display: grid; }
    .stack > :global(*) { grid-area: 1 / 1; }

    .tile-value {
        font-size: var(--font-size-xl);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.035em;
        line-height: 1.1;
        overflow-wrap: anywhere;
    }
    .tile-hint {
        display: flex;
        align-items: center;
        gap: var(--space-1);
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }

    /* Background glyph: the tile's icon, oversized, cropped and neutral. */
    .glyph {
        position: absolute;
        right: -0.75rem;
        bottom: -1.25rem;
        width: 6rem;
        height: 6rem;
        /* Tone-on-tone: a slightly deeper step of the tile's own blue. */
        color: var(--wash-glyph);
        pointer-events: none;
    }
    /* Status and risk tiles: text tinted in the tile's own blue. */
    .tile:not(.readiness) {
        color: var(--wash-ink);
    }
    .tile:not(.readiness) .cell-title,
    .tile:not(.readiness) .tile-hint {
        color: var(--wash-ink-soft);
    }
    .tile > :not(.glyph) {
        position: relative;
    }

    /* Readiness: the fresh brand gradient. */
    .readiness {
        background: linear-gradient(150deg, var(--hero-from), var(--hero-to));
        color: var(--color-on-accent-fill);
    }
    .readiness .cell-title,
    .readiness .tile-hint {
        color: color-mix(in oklab, var(--color-on-accent-fill) 78%, transparent);
    }
    .readiness .glyph {
        color: color-mix(in oklab, white 16%, transparent);
    }
    /* The one oversized number: the count, with its total on the baseline. */
    .score {
        display: flex;
        align-items: baseline;
        font-size: calc(var(--font-size-2xl) * 1.5);
        font-weight: var(--font-weight-normal);
        letter-spacing: -0.07em;
        line-height: 0.8;
        font-variant-numeric: tabular-nums;
    }
    .score-total {
        margin-inline-start: 0.2em;
        font-size: var(--font-size-xl);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.035em;
        line-height: 1.1;
        color: color-mix(in oklab, var(--color-on-accent-fill) 60%, transparent);
    }
    /* ── Checklist cell ───────────────────────────────────────────────── */
    .checks {
        grid-column: 1 / -1;
        padding: var(--inset);
        background: var(--color-surface-light);
    }
    .checks-title {
        padding: var(--space-2) var(--space-3) var(--space-1);
    }
    .rows {
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .row {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        width: 100%;
        min-height: 2.75rem;
        padding: 0 var(--space-3);
        border: none;
        /* Concentric with the cell: outer radius minus the inset. */
        border-radius: calc(var(--corner-lg) - var(--inset));
        background: none;
        color: inherit;
        font: inherit;
        text-align: start;
    }
    button.row {
        cursor: pointer;
        transition: background-color var(--duration-fast) var(--easing-default);
    }
    button.row:hover {
        background: var(--color-bg);
    }
    button.row:active { background: var(--color-surface); }
    button.row:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: -2px;
    }

    /* Done items stay quiet (a neutral tick); open items get a filled disc. */
    .mark {
        display: grid;
        place-items: center;
        flex-shrink: 0;
        width: 1.25rem;
        height: 1.25rem;
        border-radius: var(--corner-full);
        font-size: var(--font-size-sm);
        color: var(--color-text);
    }
    .open .mark {
        background: var(--tone);
        color: white;
        font-size: var(--font-size-xxs);
    }
    .row-label {
        flex: 1;
        min-width: 0;
        font-size: var(--font-size-sm);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .open .row-label { font-weight: var(--font-weight-medium); }
    .row-meta {
        flex-shrink: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        transition: transform var(--duration-fast) var(--easing-spring);
    }
    /* The chevron slides in on hover and pushes the meta aside. */
    .go {
        display: flex;
        flex-shrink: 0;
        width: 1rem;
        margin-inline-start: -1rem;
        font-size: var(--font-size-sm);
        color: var(--color-accent-text);
        opacity: 0;
        transform: translateX(-0.375rem);
        transition:
            opacity var(--duration-fast) var(--easing-default),
            transform var(--duration-fast) var(--easing-spring),
            margin var(--duration-fast) var(--easing-spring);
    }
    button.row:hover .go,
    button.row:focus-visible .go {
        margin-inline-start: 0;
        opacity: 1;
        transform: none;
    }

    @media (--bp-xs-and-smaller) {
        .bento { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .readiness { grid-column: 1 / -1; }
        .row-meta { display: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        button.row, .go, .row-meta { transition: none; }
    }
</style>
