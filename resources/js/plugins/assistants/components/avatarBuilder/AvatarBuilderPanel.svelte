<!--
  @component The builder's avatar picker as a bento grid: the avatar itself
  (tap it to pick another emoji), a tile of round background swatches, and
  two action tiles — an AI suggestion from the builder guide and a shuffle.
  Changes play spring motions: the emoji pops, the picked swatch grows while
  the previous one shrinks back, and the dice rolls. Reduced motion makes them instant.
-->
<script lang="ts">
import {onDestroy} from "svelte";
import {Spring} from "svelte/motion";
import {type AssistantAvatar} from "$plugins/assistants/types/assistant/AssistantAvatar";
import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
import {useReducedMotion} from "$lib/utils/transitions/reducedMotion.svelte.js";
import InputError from "$plugins/assistants/components/inputError/InputError.svelte";
import EmojiPicker from "$plugins/assistants/components/emojiPicker/EmojiPicker.svelte";
import AiFillReveal from "$plugins/assistants/modules/builder/components/AiFillReveal.svelte";
import ShimmerText from "$lib/components/ui/shimmer-text/ShimmerText.svelte";
import SmileIcon from "$lib/components/ui/icons/iconset/SmileIcon.svelte";
import ArrowLeft01Icon from "$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte";
import ArrowRight01Icon from "$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte";
import {ActionIcon} from "$lib/components/ui/icons";
import AiSparklesIcon from "$lib/components/ui/icons/iconset/AiSparklesIcon.svelte";
import DiceIcon from "$lib/components/ui/icons/iconset/DiceIcon.svelte";
import {BACKGROUNDS} from "$plugins/assistants/presets/backgrounds";
import {AVATAR_EMOJIS} from "$plugins/assistants/presets/emojis";
import {requestAvatarSuggestion} from "$plugins/assistants/api/resources/assistantBuilderGuideClient";

const {__} = useTranslator();
const builder = useBuilderContext();
const reducedMotion = useReducedMotion();

const avatar = $derived(builder.draft.avatar);

// Inline server validation error for the avatar save (e.g. the "an avatar
// already exists for this assistant" uniqueness conflict) — same pattern as
// BuilderInput's fieldHeader, just not routed through it since the avatar
// isn't a generic field. A failed AI suggestion shows in the same place.
let aiError = $state<string | undefined>();
let error = $derived(builder.validator.errorFor('avatar') ?? aiError);

function setAvatar(changes: Partial<AssistantAvatar>): void {
    builder.set('avatar', {...$state.snapshot(builder.draft.avatar), ...changes});
}

/* ── Spring motion ─────────────────────────────────────────────────── */

const SNAPPY = {stiffness: 0.2, damping: 0.4};
const emojiScale = new Spring(1, SNAPPY);
const emojiTilt = new Spring(0, SNAPPY);
const diceTurn = new Spring(0, {stiffness: 0.12, damping: 0.5});
/** How much the selected swatch grows. */
const SELECTED_SCALE = 1.35;
/** Room kept around a swatch scrolled into view (px). */
const REVEAL_MARGIN = 12;

/** Kick a spring away from its rest value; it springs back with an overshoot. */
function kick(spring: Spring<number>, from: number, rest: number): void {
    if (reducedMotion.current) return;
    spring.set(from, {instant: true});
    spring.target = rest;
}

// Pop the emoji whenever the avatar changes, whoever changed it (a tap, a
// swatch, the shuffle, the AI or the guide chat) — but not on first render.
let seen: string | null = null;
$effect(() => {
    const key = `${avatar.name}|${avatar.iconCss}`;
    if (seen !== null && seen !== key) kick(emojiScale, 0.55, 1);
    seen = key;
});

let swatchGrid = $state<HTMLElement | null>(null);
let swatchEls = $state<HTMLButtonElement[]>([]);
const activeIndex = $derived(BACKGROUNDS.findIndex((bg) => bg.value === avatar.iconCss));

// The selected swatch springs larger, the one left behind springs back.
const swatchScales = BACKGROUNDS.map(() => new Spring(1, SNAPPY));

// Secondary motion: the others make way for the grown swatch. Everything
// before it slides left, everything after it slides right, by the half-width
// it grew — so moving the selection shifts the row around it.
const swatchShift = BACKGROUNDS.map(() => new Spring(0, SNAPPY));

let scalesPlaced = false;
$effect(() => {
    const instant = !scalesPlaced || reducedMotion.current;
    const grownBy = ((swatchEls[activeIndex]?.offsetWidth ?? 0) * (SELECTED_SCALE - 1)) / 2;
    BACKGROUNDS.forEach((_, i) => {
        swatchScales[i].set(i === activeIndex ? SELECTED_SCALE : 1, {instant});
        const side = activeIndex < 0 || i === activeIndex ? 0 : Math.sign(i - activeIndex);
        swatchShift[i].set(side * grownBy, {instant});
    });
    scalesPlaced = true;
});

/**
 * A pick from the shuffle, the AI or the guide may land off-screen in the
 * scrolling row; bring it into view.
 */
function revealActive(instant: boolean): void {
    const el = swatchEls[activeIndex];
    const row = swatchGrid;
    if (!el || !row) return;
    const left = el.offsetLeft - REVEAL_MARGIN;
    const right = el.offsetLeft + el.offsetWidth + REVEAL_MARGIN;
    if (left < row.scrollLeft || right > row.scrollLeft + row.clientWidth) {
        row.scrollTo({
            left: left < row.scrollLeft ? left : right - row.clientWidth,
            behavior: instant || reducedMotion.current ? 'auto' : 'smooth',
        });
    }
}

let revealed = false;
$effect(() => {
    void activeIndex;
    if (!swatchGrid) return;
    revealActive(!revealed);
    revealed = true;
});

/** Whether more swatches hide past either end of the row; drives its edge fades. */
let moreBefore = $state(false);
let moreAfter = $state(false);
function measureOverflow(): void {
    const row = swatchGrid;
    if (!row) return;
    moreBefore = row.scrollLeft > 1;
    moreAfter = row.scrollLeft + row.clientWidth < row.scrollWidth - 1;
}

/** Page the row by most of its visible width. */
function scrollSwatches(direction: -1 | 1): void {
    const row = swatchGrid;
    if (!row) return;
    row.scrollBy({
        left: direction * row.clientWidth * 0.8,
        behavior: reducedMotion.current ? 'auto' : 'smooth',
    });
}

// The row reflows with the panel width; keep the selection in view.
$effect(() => {
    if (!swatchGrid) return;
    const observer = new ResizeObserver(() => {
        revealActive(true);
        measureOverflow();
    });
    observer.observe(swatchGrid);
    return () => observer.disconnect();
});

/* ── Actions ───────────────────────────────────────────────────────── */

function pickOther<T>(options: readonly T[], current: T): T {
    const rest = options.filter((o) => o !== current);
    return rest[Math.floor(Math.random() * rest.length)] ?? current;
}

function shuffle(): void {
    aiError = undefined;
    setAvatar({
        name: pickOther(AVATAR_EMOJIS, avatar.name),
        iconCss: pickOther(BACKGROUNDS.map((bg) => bg.value), avatar.iconCss),
    });
    if (!reducedMotion.current) {
        diceTurn.target = diceTurn.target + 360;
        kick(emojiTilt, Math.random() < 0.5 ? -24 : 24, 0);
    }
}

let suggesting = $state(false);
let abortCtrl: AbortController | null = null;
onDestroy(() => abortCtrl?.abort());

async function suggest(): Promise<void> {
    if (suggesting || builder.draft.id === null) return;
    suggesting = true;
    aiError = undefined;
    const ctrl = new AbortController();
    abortCtrl = ctrl;
    try {
        const pick = await requestAvatarSuggestion(builder.draft, ctrl.signal);
        if (ctrl.signal.aborted) return;
        const background = BACKGROUNDS.find((bg) => bg.id === pick?.background);
        if (!pick?.emoji && !background) {
            aiError = __('assistants.builder.general.avatar_ai_error');
            return;
        }
        setAvatar({
            ...(pick?.emoji ? {name: pick.emoji} : {}),
            ...(background ? {iconCss: background.value} : {}),
        });
        builder.markAiFilled(['avatar']);
        kick(emojiTilt, -16, 0);
    } catch {
        if (!ctrl.signal.aborted) aiError = __('assistants.builder.general.avatar_ai_error');
    } finally {
        if (abortCtrl === ctrl) abortCtrl = null;
        suggesting = false;
    }
}
</script>

<div class="input-container renderBlock">
    <div class="field-header">
        <p class="u-label">{__('assistants.builder.general.avatar_title')}</p>
        <InputError message={error} />
    </div>

    <div class="bento-host">
    <div class="bento">
        <!-- Blue reveal on the avatar alone when the AI fills it. -->
        <div class="avatar-cell">
        <AiFillReveal field="avatar">
        <div class="tile avatar-tile" style={avatar.iconCss}>
            <EmojiPicker
                onSelect={(emoji) => { aiError = undefined; setAvatar({name: emoji}); }}
                ariaLabel={__('assistants.builder.general.avatar_symbol_change')}
                align="center"
                side="bottom"
            >
                {#snippet trigger()}
                    <span
                        class="emoji"
                        style:transform="scale({emojiScale.current}) rotate({emojiTilt.current}deg)"
                    >{avatar.name}</span>
                    <span class="edit-hint" aria-hidden="true"><SmileIcon size="1em"/></span>
                {/snippet}
            </EmojiPicker>
        </div>
        </AiFillReveal>
        </div>

        <div class="tile swatch-tile" class:scrollable={moreBefore || moreAfter}>
            <ActionIcon
                icon={ArrowLeft01Icon}
                size="sm"
                class="scroll-btn"
                label={__('assistants.builder.general.avatar_backgrounds_prev')}
                disabled={!moreBefore}
                onclick={() => scrollSwatches(-1)}
            />
            <div class="swatches" bind:this={swatchGrid} role="radiogroup"
                 class:more-before={moreBefore}
                 class:more-after={moreAfter}
                 onscroll={measureOverflow}
                 aria-label={__('assistants.builder.general.avatar_background')}>
                {#each BACKGROUNDS as bg, i (bg.id)}
                    <button
                        bind:this={swatchEls[i]}
                        type="button"
                        class="swatch"
                        role="radio"
                        aria-checked={i === activeIndex}
                        aria-label={bg.label}
                        title={bg.label}
                        style={bg.value}
                        style:transform="translateX({swatchShift[i].current}px) scale({swatchScales[i].current})"
                        onclick={() => { aiError = undefined; setAvatar({iconCss: bg.value}); }}
                    ></button>
                {/each}
            </div>
            <ActionIcon
                icon={ArrowRight01Icon}
                size="sm"
                class="scroll-btn"
                label={__('assistants.builder.general.avatar_backgrounds_next')}
                disabled={!moreAfter}
                onclick={() => scrollSwatches(1)}
            />
        </div>

        <button
            type="button"
            class="tile action-tile ai-tile"
            disabled={suggesting || builder.draft.id === null}
            aria-busy={suggesting}
            onclick={suggest}
        >
            <span class="action-icon"><AiSparklesIcon size="1rem"/></span>
            <span class="action-label">
                <ShimmerText active={suggesting}>
                    {suggesting ? __('assistants.builder.general.avatar_ai_loading') : __('assistants.builder.general.avatar_ai')}
                </ShimmerText>
            </span>
        </button>

        <button type="button" class="tile action-tile shuffle-tile" onclick={shuffle}>
            <span class="action-icon" style:transform="rotate({diceTurn.current}deg)">
                <DiceIcon size="1rem"/>
            </span>
            <span class="action-label">{__('assistants.builder.general.avatar_random')}</span>
        </button>
    </div>
    </div>
</div>

<style>
    .field-header {
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    /* Query the panel's own width, so the grid adapts to the column it sits
       in rather than the viewport. */
    .bento-host {
        container-type: inline-size;
    }

    .bento {
        display: grid;
        grid-template-columns: 9rem 1fr 1fr;
        grid-template-rows: 1fr auto;
        grid-template-areas:
            "avatar swatches swatches"
            "avatar ai shuffle";
        gap: var(--space-2);
    }

    /* Flat, borderless tiles lifted only by a soft fill. */
    .tile {
        border: none;
        border-radius: var(--corner-lg);
        background: var(--color-surface-light);
        padding: var(--space-3);
    }

    /* ── Avatar ──────────────────────────────────────────────────────── */

    /* The grid cell; the AI-fill reveal wrapper and the tile fill it. */
    .avatar-cell {
        grid-area: avatar;
        aspect-ratio: 1;
    }
    .avatar-cell :global(.ai-fill),
    .avatar-cell :global(.ai-fill > .content) {
        height: 100%;
    }
    .avatar-cell :global(.sheen) {
        border-radius: var(--corner-lg);
    }

    /* The tile is the avatar: its background fills it edge to edge. */
    .avatar-tile {
        display: flex;
        height: 100%;
        padding: 0;
        overflow: hidden;
    }
    .avatar-tile :global(.emoji-picker) {
        display: flex;
        flex: 1 1 auto;
    }
    .avatar-tile :global(.custom-trigger) {
        position: relative;
        width: 100%;
        height: 100%;
        border-radius: inherit;
        transition: transform var(--duration-fast) var(--easing-spring);
    }
    .avatar-tile :global(.custom-trigger:active) {
        transform: scale(0.96);
    }
    .avatar-tile :global(.custom-trigger:focus-visible) {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: -4px;
    }

    .emoji {
        display: inline-block;
        font-size: 3.5rem;
        line-height: 1;
        will-change: transform;
    }

    .edit-hint {
        position: absolute;
        right: var(--space-2);
        bottom: var(--space-2);
        display: inline-flex;
        padding: var(--space-2);
        border-radius: var(--corner-full);
        font-size: 1.25rem;
        color: oklch(100% 0 0);
        background: oklch(100% 0 0 / 0.22);
        backdrop-filter: blur(6px);
        opacity: 0;
        transition: opacity var(--duration-extra-fast) var(--easing-default);
    }
    .avatar-tile:hover .edit-hint,
    .avatar-tile:has(:focus-visible) .edit-hint {
        opacity: 1;
    }

    /* ── Swatches ────────────────────────────────────────────────────── */

    .swatch-tile {
        grid-area: swatches;
        display: flex;
        align-items: center;
        min-width: 0;
        padding-inline: 0;
    }

    /* The arrows only appear when the row overflows. */
    .swatch-tile :global(.scroll-btn) {
        display: none;
    }
    .swatch-tile.scrollable :global(.scroll-btn) {
        display: inline-flex;
    }
    .swatch-tile.scrollable :global(.scroll-btn:disabled) {
        opacity: 0.3;
    }

    /* One row; it only scrolls when the panel is too narrow for all
       swatches. The others make way for the grown selection (see
       `swatchShift`), the padding leaves it room at the ends, and an edge
       fades only where more swatches are hidden. */
    .swatches {
        --fade-start: 0px;
        --fade-end: 0px;
        position: relative;
        display: flex;
        gap: var(--space-2);
        width: 100%;
        padding: var(--space-2) var(--space-3);
        overflow-x: auto;
        overscroll-behavior-x: contain;
        scroll-snap-type: x proximity;
        scrollbar-width: none;
        mask-image: linear-gradient(90deg, transparent, #000 var(--fade-start), #000 calc(100% - var(--fade-end)), transparent);
    }
    .swatches.more-before {
        --fade-start: var(--space-8);
    }
    .swatches.more-after {
        --fade-end: var(--space-8);
    }
    .swatches::-webkit-scrollbar {
        display: none;
    }

    /* Centred while the row fits; the auto margins give way once it
       scrolls, so the first swatch is never cut off. */
    .swatch:first-child {
        margin-inline-start: auto;
    }
    .swatch:last-child {
        margin-inline-end: auto;
    }

    /* `transform` carries the selection spring; hover and press use the
       separate `scale` property so they stack on top of it. */
    .swatch {
        flex: 0 0 auto;
        width: 2rem;
        aspect-ratio: 1;
        scroll-snap-align: center;
        padding: 0;
        border: none;
        border-radius: var(--corner-full);
        cursor: pointer;
        transition: scale var(--duration-fast) var(--easing-spring);
    }
    .swatch:hover {
        scale: 1.1;
    }
    .swatch:active {
        scale: 0.88;
    }
    .swatch:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
    }

    /* ── Actions ─────────────────────────────────────────────────────── */

    .action-tile {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        padding: var(--space-2_5) var(--space-3);
        border-radius: var(--corner-full);
        font: inherit;
        font-size: var(--font-size-xs);
        color: var(--color-text);
        white-space: nowrap;
        cursor: pointer;
        transition: background-color var(--duration-fast) var(--easing-default),
                    transform var(--duration-fast) var(--easing-spring);
    }
    .ai-tile { grid-area: ai; }
    .shuffle-tile { grid-area: shuffle; }
    .action-tile:hover:not(:disabled) {
        background: var(--color-hover);
    }
    .action-tile:active:not(:disabled) {
        transform: scale(0.96);
    }
    .action-tile:disabled {
        cursor: default;
        color: var(--color-text-disabled);
    }
    .action-tile:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
    }

    .action-icon {
        display: inline-flex;
        flex-shrink: 0;
    }
    .action-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Narrow column: avatar and swatches side by side get too tight, so the
       swatches drop below the avatar and the actions share the last row. */
    @container (max-width: 30rem) {
        .bento {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: auto;
            grid-template-areas:
                "avatar avatar"
                "swatches swatches"
                "ai shuffle";
        }
        .avatar-cell {
            aspect-ratio: auto;
            height: 8rem;
        }
    }
</style>
