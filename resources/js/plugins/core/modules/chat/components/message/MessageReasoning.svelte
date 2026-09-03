<!--
  @component The model's reasoning ("Gedankengang") as a timeline of steps:
  each titled block of thinking text and each web search it ran becomes one row
  on a connecting rail — marker, label, body.

  The whole block is borderless: a `fit-content` pill of a trigger row (brain,
  label, chevron) sits above the collapsible step list, so a collapsed
  reasoning block reads as a quiet line of text rather than a card. The chrome
  — brain, rail, markers, chips, hover — runs on the accent blue; the labels
  and the thinking text itself stay in the plain text colours so the content
  keeps its contrast.

  The trigger row reads the same whether the block is open or closed — it
  reports the model's state, not the panel's — so collapsing only rotates the
  chevron and takes the steps away. Which step is running is the timeline's
  job: while the model is still thinking (`active`) the list is expanded and
  the last step shimmers. The user's last open/closed choice is remembered in
  localStorage and used as the default for finished messages.

  @example
  <MessageReasoning parts={message.reasoning} active={message.isStreaming && !message.content.text} />
-->
<script lang="ts">
    import {backOut, cubicInOut, cubicOut} from 'svelte/easing';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import AiBrain01Icon from '$lib/components/ui/icons/iconset/AiBrain01Icon.svelte';
    import GlobalSearchIcon from '$lib/components/ui/icons/iconset/GlobalSearchIcon.svelte';
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
    import Link from '$lib/components/util/link/Link.svelte';
    import UrlPreviewTooltip from '$lib/components/ui/tooltip/UrlPreviewTooltip.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import type {ReasoningPart} from '$plugins/core/modules/chat/types.js';
    import {useReducedMotion} from '$lib/utils/transitions/reducedMotion.svelte.js';

    const STORAGE_KEY = 'hawkiReasoningOpen';
    /** Sources shown before the rest fold into a nested "+N more" section. */
    const MAX_SOURCES = 6;

    /* Milliseconds of the motion ladder in the stylesheet below, for the two
       transitions that have to run in JS. */
    const MOTION_MODERATE = 160;
    const MOTION_SLOW = 240;
    const MOTION_EXIT = 120;
    /** How far the content of an arriving step lags behind the space made for it. */
    const FADE_DELAY = 80;
    /** Longer than the space-making, so the text arrives instead of popping. */
    const FADE_DURATION = 320;
    /** Gap between one step's fade and the next when a whole list arrives at once. */
    const STEP_STAGGER = 50;
    /** Past this many steps the stagger stops growing, so a long chain still lands promptly. */
    const STAGGER_CAP = 8;

    /** A line that holds nothing but bold text — how models title a thinking step. */
    const STEP_HEADING = /^[ \t]*\*\*([^*\n]+)\*\*[ \t]*$/gm;

    /** One row of the timeline: a titled block of thinking text, or one web search. */
    type Step =
        | {kind: 'text'; key: string; label: string | null; body: string}
        | {kind: 'search'; key: string; label: string; query: string | null; sources: string[]};

    interface Props {
        /** The reasoning steps in the order they happened. */
        parts: ReasoningPart[];
        /** True while the model is still thinking and has not produced an answer yet. */
        active?: boolean;
        /** Heading level markdown headings inside the thinking text start at. Defaults to 4. */
        headingLevel?: number;
    }

    const {parts, active = false, headingLevel = 4}: Props = $props();
    const {__} = useTranslator();
    const app = useApp();
    const reducedMotion = useReducedMotion();

    let userToggled = $state<boolean | null>(null);
    const rememberedOpen = app.localStorage.getItem(STORAGE_KEY) === '1';
    const open = $derived(userToggled ?? (active || rememberedOpen));

    /** Search steps whose folded-away sources the user has opened, by step key. */
    let expandedSources = $state<Record<string, boolean>>({});

    /* Animations are for changes the user watches happen. A panel that is
       already open when the message first renders — a reloaded conversation,
       scrolled-in history — should simply be there. */
    let mounted = $state(false);
    $effect(() => {
        mounted = true;
    });

    /* Set for the flush in which the panel opens. Every step mounts in that
       flush, so without it each one would replay the streaming entrance
       instead of the list staggering in. Only ever read when a step's
       transition is built, so clearing it later changes nothing on screen. */
    let opening = $state(false);

    function toggle() {
        const next = !open;
        userToggled = next;
        if (next) {
            opening = true;
            // Microtasks (and the DOM flush) run first; this clears after.
            setTimeout(() => (opening = false), 0);
        }
        app.localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
    }

    /**
     * Opens or closes a panel on its height alone — no fade, no padding
     * scaling. Pure height is what keeps a collapse from looking mushy. The
     * exit passes a shorter `duration` so dismissing reads as final rather
     * than as the entrance played backwards.
     */
    function collapse(node: Element, {duration = MOTION_MODERATE, enabled = true} = {}) {
        if (!enabled || reducedMotion.current) return {duration: 0};
        const height = node.scrollHeight;
        return {
            duration,
            easing: cubicOut,
            css: (t: number) => `height: ${t * height}px; overflow: hidden;`
        };
    }

    /**
     * A step entering, in one of two situations.
     *
     * `streaming` — the model just produced it, so the row's height opens
     * first (with a touch of overshoot) and the text fades in a beat later and
     * keeps fading after the space is made: the list makes room before
     * anything shows up in it.
     *
     * Otherwise the whole list is arriving at once because the panel was
     * opened. The panel's own height animation already makes the space, so
     * each step only fades — staggered by its position, so the list assembles
     * top to bottom instead of appearing as one block.
     */
    function stepIn(node: Element, {enabled, streaming, index}: {enabled: boolean; streaming: boolean; index: number}) {
        if (!enabled || reducedMotion.current) return {duration: 0};

        if (!streaming) {
            return {
                duration: FADE_DURATION,
                delay: Math.min(index, STAGGER_CAP) * STEP_STAGGER,
                easing: cubicInOut,
                css: (t: number) => `opacity: ${t};`
            };
        }

        const height = node.scrollHeight;
        const total = FADE_DELAY + FADE_DURATION;
        return {
            // Linear time; each phase below applies its own curve.
            duration: total,
            css: (t: number) => {
                const elapsed = t * total;
                const opened = Math.min(1, elapsed / MOTION_SLOW);
                const faded = cubicInOut(Math.max(0, Math.min(1, (elapsed - FADE_DELAY) / FADE_DURATION)));
                /* Once the space is made the row returns to auto height, so
                   text still streaming into it is never clipped against a
                   measurement taken when the step held a single token. */
                const box = opened < 1 ? `height: ${backOut(opened) * height}px; overflow: hidden;` : '';
                return `${box} opacity: ${faded};`;
            }
        };
    }

    /**
     * Splits one block of thinking text into its titled sections. Models stream
     * reasoning as "**Title**\n\nBody", often several sections per part, so each
     * section becomes its own step; text before the first title keeps a `null`
     * label and renders without one.
     */
    function splitSections(text: string): {label: string | null; body: string}[] {
        const sections: {label: string | null; body: string}[] = [];
        let label: string | null = null;
        let start = 0;

        for (const match of text.matchAll(STEP_HEADING)) {
            const body = text.slice(start, match.index).trim();
            if (label !== null || body) sections.push({label, body});
            label = match[1].trim();
            start = match.index + match[0].length;
        }

        const rest = text.slice(start).trim();
        if (label !== null || rest) sections.push({label, body: rest});
        return sections;
    }

    function actionLabel(action: string): string {
        if (action === 'open_page') return __('chat.page.webSearchOpened');
        if (action === 'find_in_page') return __('chat.page.webSearchFind');
        return __('chat.page.webSearch');
    }

    function domain(url: string): string {
        try {
            return new URL(url).hostname.replace(/^www\./, '');
        } catch {
            return url;
        }
    }

    /** One chip per site: the first URL of each domain, in the order they were found. */
    function uniqueSources(urls: string[]): string[] {
        const seen = new Set<string>();
        return urls.filter(url => {
            const key = domain(url);
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    }

    function withEllipsis(label: string): string {
        return label.endsWith('…') ? label : `${label}…`;
    }

    const steps = $derived.by(() => {
        const list: Step[] = [];
        parts.forEach((part, index) => {
            if (part.type === 'web_search') {
                list.push({
                    kind: 'search',
                    key: `s${index}`,
                    label: actionLabel(part.action),
                    query: part.query,
                    sources: uniqueSources(part.sources)
                });
                return;
            }
            splitSections(part.text).forEach((section, sectionIndex) => {
                list.push({kind: 'text', key: `t${index}-${sectionIndex}`, ...section});
            });
        });
        return list;
    });

    const searchCount = $derived(parts.filter(part => part.type === 'web_search').length);

    const triggerLabel = $derived(active ? __('chat.page.thinking') : __('chat.page.reasoning'));

    const panelId = $props.id();
</script>

{#snippet sourceChip(url: string, index: number)}
    <li style="--chip-delay: {index * 50}ms">
        <UrlPreviewTooltip {url}>
            {#snippet children({props})}
                <Link {...props} href={url} target="_blank" title={url} class="source">
                    {#snippet children({favicon})}
                        {@render favicon()}
                        <span class="source-domain">{domain(url)}</span>
                    {/snippet}
                </Link>
            {/snippet}
        </UrlPreviewTooltip>
    </li>
{/snippet}

<!-- The trigger row, shared by the panel and by a step's folded-away sources. -->
{#snippet triggerRow(label: string, isOpen: boolean, controls: string, onclick: () => void, sub = false)}
    <button
        type="button"
        class="trigger"
        class:sub
        class:open={isOpen}
        aria-expanded={isOpen}
        aria-controls={controls}
        {onclick}
    >
        {#if !sub}
            <span class="brain" aria-hidden="true"><AiBrain01Icon size={16} /></span>
        {/if}
        <span class="label" class:shimmer={active && !sub}>{label}</span>
        {#if !sub && searchCount > 0}
            <span class="count"><GlobalSearchIcon size={12} /> {searchCount}</span>
        {/if}
        <span class="chevron" aria-hidden="true"><ArrowRight01Icon size={16} /></span>
    </button>
{/snippet}

<section class="reasoning" aria-label={__('chat.page.reasoning')}>
    {@render triggerRow(triggerLabel, open, panelId, toggle)}

    {#if open}
        <div class="panel" id={panelId} in:collapse={{enabled: mounted}} out:collapse={{duration: MOTION_EXIT}}>
            <ol class="steps">
                {#each steps as step, index (step.key)}
                    {@const isActive = active && index === steps.length - 1}
                    {@const label = step.label ?? (isActive ? __('chat.page.thinking') : null)}
                    <li class="step" class:search={step.kind === 'search'} in:stepIn={{enabled: mounted, streaming: active && !opening, index}}>
                        <span class="rail" aria-hidden="true">
                            <span class="line line-top" class:blank={index === 0}></span>
                            <span class="marker">
                                {#if step.kind === 'search'}
                                    <GlobalSearchIcon size={16} />
                                {:else}
                                    <span class="dot" class:pulse={isActive}></span>
                                {/if}
                            </span>
                            <span class="line line-bottom" class:blank={index === steps.length - 1}></span>
                        </span>

                        <div class="content">
                            {#if label}
                                <span class="step-label" class:shimmer={isActive}>{isActive ? withEllipsis(label) : label}</span>
                            {/if}

                            {#if step.kind === 'search'}
                                {#if step.query}
                                    <span class="query">„{step.query}“</span>
                                {/if}
                                {#if step.sources.length}
                                    <ul class="sources">
                                        {#each step.sources.slice(0, MAX_SOURCES) as url, sourceIndex (url)}
                                            {@render sourceChip(url, sourceIndex)}
                                        {/each}
                                    </ul>
                                {/if}
                                {#if step.sources.length > MAX_SOURCES}
                                    {@const expanded = expandedSources[step.key] ?? false}
                                    {@const moreLabel = __('chat.page.webSearchMore', {count: String(step.sources.length - MAX_SOURCES)})}
                                    <div class="details">
                                        {@render triggerRow(
                                            moreLabel,
                                            expanded,
                                            `${panelId}-${step.key}`,
                                            () => (expandedSources[step.key] = !expanded),
                                            true
                                        )}
                                        {#if expanded}
                                            <div
                                                class="details-panel"
                                                id="{panelId}-{step.key}"
                                                in:collapse
                                                out:collapse={{duration: MOTION_EXIT}}
                                            >
                                                <ul class="sources">
                                                    {#each step.sources.slice(MAX_SOURCES) as url, sourceIndex (url)}
                                                        {@render sourceChip(url, sourceIndex)}
                                                    {/each}
                                                </ul>
                                            </div>
                                        {/if}
                                    </div>
                                {/if}
                            {:else if step.body}
                                <div class="prose"><Markdown message={step.body} headingBaseLevel={headingLevel} /></div>
                            {/if}
                        </div>
                    </li>
                {/each}
            </ol>
        </div>
    {/if}
</section>

<style>
    .reasoning {
        /* Motion ladder. 80ms for anything that must feel instant — hover,
           colour, the chevron; 160ms for small things arriving;
           160ms for a panel opening and 240ms for a step arriving (both tiers
           are driven from the script, see MOTION_MODERATE/MOTION_SLOW).
           Deliberately below
           --duration-extra-fast (200ms), which is a beat too slow to use on a
           micro-interaction. Exits run a tier quicker than their entrance, so
           a dismissal reads as final instead of rewound. */
        --motion-fast: 80ms;
        --motion-fast-exit: 60ms;
        --motion-moderate: 160ms;

        /* The marker column is the width of the trigger's icon, so the markers
           hang under it as one spine and every label starts on the same x. */
        --marker-col: 16px;
        --row-gap: var(--space-2);
        /* Also sets how far a marker sits below the top of its row, so it has
           to leave the taller search markers room for a rail segment above. */
        --step-pad: var(--space-2);

        margin-block: var(--space-1) var(--space-2);
        color: var(--color-text-muted);
        /* No font-size of its own: the block reads at the message's body size.
           The rail measures its offsets in em, so the markers stay centred on
           the first line whatever that size is. Chips and the count badge keep
           their own smaller size below. */
    }

    /* ── Trigger row ──────────────────────────────────────────────────── */

    .trigger {
        /* Pulled left by its own padding so the icon lines up with the message
           text — the pill grows into the margin, not the content out of it. */
        display: inline-flex;
        align-items: center;
        gap: var(--row-gap);
        max-width: 100%;
        margin-inline-start: calc(-1 * var(--space-3));
        padding: var(--space-2) var(--space-3);
        border: none;
        border-radius: var(--corner-full);
        background: transparent;
        /* Accent whether the panel is open or closed — the row is the same
           control either way, so the pill behind it answers the pointer on
           its own. Icons keep the app-wide stroke weight of 2 throughout. */
        color: var(--color-accent-text);
        font: inherit;
        text-align: start;
        cursor: pointer;
        user-select: none;
        transition: background-color var(--motion-fast-exit) var(--easing-out),
                    color var(--motion-fast) var(--easing-out);
    }

    /* A step's folded-away sources: same row, one size down. */
    .trigger.sub {
        gap: var(--space-1_5);
        padding-block: var(--space-1);
    }

    .trigger:hover {
        background: var(--color-hover);
        transition-duration: var(--motion-fast);
    }
    .trigger:focus-visible { outline: 2px solid var(--color-focus-ring); outline-offset: 2px; }

    .brain { display: inline-flex; flex: none; }

    /* The label keeps one weight throughout — opening the panel shifts its
       colour, nothing else. */
    .label {
        min-width: 0;
        overflow: hidden;
        line-height: var(--line-height-tight);
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .count {
        display: inline-flex;
        align-items: center;
        flex: none;
        gap: var(--space-0_5);
        padding-inline: var(--space-1_5);
        border-radius: var(--corner-full);
        background: var(--color-active-surface);
        font-size: var(--font-size-xxs);
        font-variant-numeric: tabular-nums;
    }

    .chevron {
        display: inline-flex;
        flex: none;
        transition: transform var(--motion-fast) var(--easing-spring);
    }

    .open .chevron { transform: rotate(90deg); }

    /* ── Steps ────────────────────────────────────────────────────────── */

    .panel { padding-block: var(--space-1) var(--space-3); }

    .steps {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .step {
        --marker-size: 8px;
        /* Breathing room between a marker and the rail running through it. */
        --marker-gap: var(--space-1_5);
        display: grid;
        grid-template-columns: var(--marker-col) minmax(0, 1fr);
        column-gap: var(--row-gap);
    }

    /* Sized to the icon itself, so the gap around it reads the same as the
       one around a dot. */
    .step.search { --marker-size: 16px; }

    /* Rail: line, marker, line — so the connector runs unbroken from step to
       step and stops short of every marker. */
    .rail {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .line {
        width: var(--divider-width);
        /* Well below the markers it connects: the rail should sit behind the
           steps, not draw a blue stripe down the message. */
        background: color-mix(in oklab, var(--color-accent-text) 35%, transparent);
    }

    /* Down to the marker, which stays centred on the label's first line — the
       gap above the marker comes out of this segment, not out of the text. */
    .line-top { height: max(0px, calc(var(--step-pad) + 0.62em - var(--marker-size) / 2 - var(--marker-gap))); }
    .line-bottom { flex: 1; }
    .line.blank { visibility: hidden; }

    .marker {
        display: grid;
        flex: none;
        place-items: center;
        height: var(--marker-size);
        margin-block: var(--marker-gap);
        color: var(--color-accent-text);
    }

    .dot {
        width: var(--marker-size);
        height: var(--marker-size);
        border-radius: 50%;
        background: var(--color-accent-text);
        opacity: 0.7;
    }

    .dot.pulse {
        opacity: 1;
        animation: pulse 1.5s ease-in-out infinite;
    }

    .content {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        min-width: 0;
        padding-block: var(--step-pad);
    }

    .step-label {
        color: var(--color-accent-text);
        font-weight: var(--font-weight-medium);
        line-height: var(--line-height-tight);
    }

    .query { overflow-wrap: anywhere; }

    /* ── Sources ──────────────────────────────────────────────────────── */

    .sources {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-1_5);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sources > li {
        animation: chip-in var(--motion-moderate) var(--easing-spring) var(--chip-delay, 0ms) backwards;
    }

    .sources :global(.source) {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        max-width: 100%;
        min-height: 1.25rem;
        padding-inline: var(--space-2);
        border-radius: var(--corner-full);
        background: var(--color-active-surface);
        color: var(--color-text);
        font-size: var(--font-size-xxs);
        text-decoration: none;
        line-height: 1.4;
        transition: background-color var(--motion-fast) var(--easing-out);
    }

    .sources :global(.source:hover) { background: var(--color-hover); }

    .source-domain {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .details-panel { padding-top: var(--space-1); }

    /* ── Thinking text ────────────────────────────────────────────────── */

    .prose {
        min-width: 0;
        line-height: var(--line-height-normal);
        overflow-wrap: anywhere;
    }

    .prose :global(.markstream-svelte),
    .prose :global(.markstream-svelte *) {
        font-size: inherit;
        line-height: inherit;
    }

    .prose :global([data-node-type]) { margin: 0 0 var(--space-2); }
    .prose :global([data-node-type]:last-child) { margin-bottom: 0; }
    .prose :global(strong) { color: var(--color-text); font-weight: var(--font-weight-semibold); }

    /* A highlight travelling along the text, rather than the whole label
       blinking: the label stays readable while it runs. */
    .shimmer {
        --shimmer-base: color-mix(in oklab, var(--color-accent-text) 60%, var(--color-text-muted));
        background: linear-gradient(
            90deg,
            var(--shimmer-base) 0%,
            var(--shimmer-base) 35%,
            var(--color-accent-text) 50%,
            var(--shimmer-base) 65%,
            var(--shimmer-base) 100%
        );
        background-size: 300% 100%;
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        animation: shimmer 1.5s ease-in-out infinite;
    }

    @keyframes shimmer {
        from { background-position: 0% 0; }
        to { background-position: 100% 0; }
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.35; }
    }

    @keyframes chip-in {
        from { opacity: 0; transform: scale(0.85); filter: blur(4px); }
        70% { filter: blur(0); }
        to { opacity: 1; transform: none; filter: blur(0); }
    }

    @media (prefers-reduced-motion: reduce) {
        .shimmer {
            animation: none;
            background: none;
            color: var(--color-accent-text);
        }

        .dot.pulse { animation: none; }
        .sources > li { animation: none; }
        .chevron { transition: none; }
    }
</style>
