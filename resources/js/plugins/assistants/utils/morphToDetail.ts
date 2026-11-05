/**
 * Store card ⇄ detail page morph, built on the View Transitions API.
 *
 * A card's banner and glyph share `view-transition-name`s with the detail
 * page's cover (`assistant-cover`, `assistant-symbol`), and the card as a
 * whole shares `assistant-container` with the detail page's content column,
 * so the browser grows the card into the page with the content scaling
 * inside it (a container transform). The detail page carries its names permanently; a card
 * only gets them for the duration of a transition (names must be unique per
 * snapshot, and a store shows many cards). The main content area is named
 * too, so the pages swap inside it while the sidebar stays put.
 *
 * Direction is exposed as `data-assistant-morph="open" | "back"` on <html>,
 * which morphToDetail.css keys its animations on.
 *
 * Falls back to a plain navigation where the API is missing or the user
 * prefers reduced motion.
 */
import {tick} from 'svelte';
import {useReducedMotion} from '$lib/utils/transitions/reducedMotion.svelte';
import './morphToDetail.css';

const MORPH_COVER = 'assistant-cover';
const MORPH_SYMBOL = 'assistant-symbol';
/** The card as a whole ⇄ the detail page's content column: the container the content scales inside. */
const MORPH_CONTAINER = 'assistant-container';
const MORPH_PAGE = 'assistant-page';

/** The detail page marks its cover with this attribute. */
const DETAIL_COVER_SELECTOR = '[data-assistant-cover]';

/**
 * Longest the old page stays frozen while the next page loads its data. Past
 * that the transition runs anyway: the cover then cross-fades instead of
 * morphing, which beats a stalled screen.
 */
const MAX_WAIT_MS = 800;

type Direction = 'open' | 'back';

/**
 * Resolves with the first element matching `selector` once it is in the DOM,
 * or with null after MAX_WAIT_MS.
 *
 * Must not poll with requestAnimationFrame: rendering — rAF included — is
 * suppressed while a view transition's update callback is pending, so a rAF
 * loop would never tick, the callback would hang until the browser's own
 * timeout, and the whole transition would be skipped.
 */
function waitFor(selector: string): Promise<HTMLElement | null> {
    return new Promise(resolve => {
        const found = document.querySelector<HTMLElement>(selector);
        if (found) {
            resolve(found);
            return;
        }
        const done = (el: HTMLElement | null) => {
            observer.disconnect();
            clearTimeout(timer);
            resolve(el);
        };
        const observer = new MutationObserver(() => {
            const el = document.querySelector<HTMLElement>(selector);
            if (el) done(el);
        });
        observer.observe(document.body, {childList: true, subtree: true});
        const timer = setTimeout(() => done(null), MAX_WAIT_MS);
    });
}

function canMorph(): boolean {
    return !!document.startViewTransition && !useReducedMotion().current;
}

/** Tags a card, its banner and its glyph; returns the undo. */
function nameCard(card: HTMLElement): () => void {
    const banner = card.querySelector<HTMLElement>('.banner-container');
    const symbol = card.querySelector<HTMLElement>('.symbol');
    card.style.viewTransitionName = MORPH_CONTAINER;
    if (banner) banner.style.viewTransitionName = MORPH_COVER;
    if (symbol) symbol.style.viewTransitionName = MORPH_SYMBOL;
    return () => {
        card.style.viewTransitionName = '';
        if (banner) banner.style.viewTransitionName = '';
        if (symbol) symbol.style.viewTransitionName = '';
    };
}

/**
 * Shared run: names the main area, flags the direction, runs `update` inside
 * the transition and cleans every temporary name up afterwards.
 */
function run(direction: Direction, update: () => Promise<(() => void) | void>): void {
    // The landmark persists across both pages, so its old/new snapshots are
    // exactly the two pages.
    const main = document.getElementById('main-content');
    const root = document.documentElement;
    const cleanups: (() => void)[] = [];

    if (main) {
        main.style.viewTransitionName = MORPH_PAGE;
        cleanups.push(() => { main.style.viewTransitionName = ''; });
    }
    root.dataset.assistantMorph = direction;
    cleanups.push(() => { delete root.dataset.assistantMorph; });

    const transition = document.startViewTransition(async () => {
        const undo = await update();
        if (undo) cleanups.push(undo);
        await tick();
    });

    // Also runs when the transition is skipped (a failed or vetoed
    // navigation) — never leave stale names behind.
    void transition.finished.finally(() => cleanups.forEach(fn => fn()));
}

/**
 * Store → detail: runs `navigate` inside a view transition that morphs
 * `card`'s banner into the detail page's cover.
 */
export function morphToDetail(card: HTMLElement, navigate: () => Promise<void>): void {
    if (!canMorph()) {
        void navigate();
        return;
    }
    // The card must be named before the old snapshot is taken.
    const unnameCard = nameCard(card);
    run('open', async () => {
        await navigate();
        await waitFor(DETAIL_COVER_SELECTOR);
        return unnameCard;
    });
}

/**
 * Detail → store (or whichever list the assistant was opened from): runs
 * `navigate` inside a view transition that morphs the detail cover back into
 * the card of `assistantId`, once that card has rendered.
 */
export function morphFromDetail(assistantId: string | number, navigate: () => Promise<void>): void {
    if (!canMorph()) {
        void navigate();
        return;
    }
    run('back', async () => {
        await navigate();
        const card = await waitFor(`[data-assistant-card="${CSS.escape(String(assistantId))}"]`);
        // Named only now, after the detail page (and its permanent names)
        // has left the DOM — the new snapshot is taken after this callback.
        return card ? nameCard(card) : undefined;
    });
}
