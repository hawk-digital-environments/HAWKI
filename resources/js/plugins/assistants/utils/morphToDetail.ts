/**
 * Store card ⇄ detail modal morph, built on the View Transitions API.
 *
 * The detail view opens as a full-screen modal over the list, which stays
 * mounted beneath it (AssistantDetailModal). A card's banner, glyph and title
 * share `view-transition-name`s with the detail cover (`assistant-cover`,
 * `assistant-symbol`, `assistant-title`), and the card as a whole shares
 * `assistant-container` with the detail's content column, so the browser
 * grows the card into the modal with the content scaling inside it (a
 * container transform). The detail view carries its names permanently; a card
 * only holds them while it is one end of the morph — names must be unique per
 * snapshot, and since the card stays in the DOM under the open modal, it
 * gives them up before the modal's snapshot is taken. The list's main area
 * (`assistant-page`) recedes under the modal surface (`assistant-modal`).
 *
 * Direction is exposed as `data-assistant-morph="open" | "back"` on <html>,
 * which morphToDetail.css keys its animations on.
 *
 * Falls back to opening/closing without a morph where the API is missing or the user
 * prefers reduced motion.
 */
import { tick } from 'svelte';
import { useReducedMotion } from '$lib/utils/transitions/reducedMotion.svelte';
import './morphToDetail.css';

const MORPH_COVER = 'assistant-cover';
const MORPH_SYMBOL = 'assistant-symbol';
/** The card's poster title ⇄ the detail cover's name. */
const MORPH_TITLE = 'assistant-title';
/** The detail cover's handle: no counterpart on the card, so it rides along with the title. */
const MORPH_HANDLE = 'assistant-handle';
/** The card as a whole ⇄ the detail page's content column: the container the content scales inside. */
const MORPH_CONTAINER = 'assistant-container';
const MORPH_PAGE = 'assistant-page';

/** The detail page marks its cover with this attribute. */
const DETAIL_COVER_SELECTOR = '[data-assistant-cover]';
/** The detail cover's name (the title morph's detail end) and handle. */
const DETAIL_TITLE_SELECTOR = '.name .overflow-text';
const DETAIL_HANDLE_SELECTOR = '.handle';
/** The card's poster title (the title morph's card end). */
const CARD_TITLE_SELECTOR = '.cover-title .overflow-text';

/**
 * Longest the old view stays frozen while the detail loads its data. Past
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
    return new Promise((resolve) => {
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
        observer.observe(document.body, { childList: true, subtree: true });
        const timer = setTimeout(() => done(null), MAX_WAIT_MS);
    });
}

function canMorph(): boolean {
    return !!document.startViewTransition && !useReducedMotion().current;
}

/** Tags a card, its banner, its glyph and its title; returns the undo. */
function nameCard(card: HTMLElement): () => void {
    const banner = card.querySelector<HTMLElement>('.banner-container');
    const symbol = card.querySelector<HTMLElement>('.symbol');
    const title = card.querySelector<HTMLElement>(CARD_TITLE_SELECTOR);
    card.style.viewTransitionName = MORPH_CONTAINER;
    if (banner) banner.style.viewTransitionName = MORPH_COVER;
    if (symbol) symbol.style.viewTransitionName = MORPH_SYMBOL;
    if (title) title.style.viewTransitionName = MORPH_TITLE;
    return () => {
        card.style.viewTransitionName = '';
        if (banner) banner.style.viewTransitionName = '';
        if (symbol) symbol.style.viewTransitionName = '';
        if (title) title.style.viewTransitionName = '';
    };
}

/**
 * Shared run: names the main area, flags the direction, runs `update` inside
 * the transition and cleans every temporary name up afterwards.
 */
function run(direction: Direction, update: () => Promise<(() => void) | void>): ViewTransition {
    // The landmark persists across both pages, so its old/new snapshots are
    // exactly the two pages.
    const main = document.getElementById('main-content');
    const root = document.documentElement;
    const cleanups: (() => void)[] = [];

    if (main) {
        main.style.viewTransitionName = MORPH_PAGE;
        cleanups.push(() => {
            main.style.viewTransitionName = '';
        });
    }
    root.dataset.assistantMorph = direction;
    cleanups.push(() => {
        delete root.dataset.assistantMorph;
    });

    const transition = document.startViewTransition(async () => {
        const undo = await update();
        if (undo) cleanups.push(undo);
        await tick();
    });

    // Also runs when the transition is skipped (a failed or vetoed
    // navigation) — never leave stale names behind.
    void transition.finished.finally(() => cleanups.forEach((fn) => fn()));
    return transition;
}

/** Where the title morph's two ends and the handle sit. */
interface HandleRide {
    cardTitle: DOMRect;
    detailTitle: DOMRect;
    handle: DOMRect;
}

/** Measures the detail cover's title and handle; null without either. */
function measureDetail(cover: Element | null): Omit<HandleRide, 'cardTitle'> | null {
    const title = cover?.querySelector(DETAIL_TITLE_SELECTOR);
    const handle = cover?.querySelector(DETAIL_HANDLE_SELECTOR);
    return title && handle ?
            { detailTitle: title.getBoundingClientRect(), handle: handle.getBoundingClientRect() }
        :   null;
}

/**
 * The handle exists on the detail end only, so the browser would just fade
 * it in (or out) in place. Instead it rides along with the title: at the
 * card end it sits where it would under the card's title, scaled by the
 * title's own scale, and travels with it on the same timing.
 */
function rideHandle(transition: ViewTransition, direction: Direction, ride: () => HandleRide | null): void {
    void transition.ready
        .then(() => {
            const at = ride();
            if (!at || !at.detailTitle.width) return;
            const scale = at.cardTitle.width / at.detailTitle.width;
            const atCard =
                `translate(${at.cardTitle.left + (at.handle.left - at.detailTitle.left) * scale}px, ` +
                `${at.cardTitle.top + (at.handle.top - at.detailTitle.top) * scale}px) scale(${scale})`;
            const atDetail = `translate(${at.handle.left}px, ${at.handle.top}px)`;
            const root = getComputedStyle(document.documentElement);
            document.documentElement.animate(
                { transform: direction === 'open' ? [atCard, atDetail] : [atDetail, atCard] },
                {
                    duration: parseFloat(root.getPropertyValue('--assistant-morph-duration')) || 400,
                    easing: root.getPropertyValue('--assistant-morph-ease').trim() || 'ease',
                    fill: 'both',
                    pseudoElement: `::view-transition-group(${MORPH_HANDLE})`
                }
            );
        })
        .catch(() => {});
}

/**
 * List → detail: runs `open` (which shows the detail modal) inside a view
 * transition that morphs `card` into the detail view.
 */
export function morphToDetail(card: HTMLElement, open: () => Promise<void>): void {
    if (!canMorph()) {
        void open();
        return;
    }
    // Measured now, at the old end: the card is under the modal later.
    const cardTitle = card.querySelector(CARD_TITLE_SELECTOR)?.getBoundingClientRect();
    let detail: ReturnType<typeof measureDetail> = null;
    // The card must be named before the old snapshot is taken…
    const unnameCard = nameCard(card);
    const transition = run('open', async () => {
        await open();
        detail = measureDetail(await waitFor(DETAIL_COVER_SELECTOR));
        // …and unnamed before the new one: it is still in the DOM under the
        // modal, whose detail view now holds the same names.
        unnameCard();
    });
    rideHandle(transition, 'open', () => (cardTitle && detail ? { cardTitle, ...detail } : null));
}

/**
 * Detail → list: runs `close` (which removes the detail modal) inside a view
 * transition that morphs the detail view back into the card of `assistantId`.
 */
export function morphFromDetail(assistantId: string | number, close: () => Promise<void>): void {
    if (!canMorph()) {
        void close();
        return;
    }
    // Measured now, at the old end, before the modal leaves the DOM.
    const detail = measureDetail(document.querySelector(DETAIL_COVER_SELECTOR));
    let cardTitle: DOMRect | undefined;
    const transition = run('back', async () => {
        await close();
        const card = await waitFor(`[data-assistant-card="${CSS.escape(String(assistantId))}"]`);
        cardTitle = card?.querySelector(CARD_TITLE_SELECTOR)?.getBoundingClientRect();
        // Named only now, after the detail modal (and its permanent names)
        // has left the DOM — the new snapshot is taken after this callback.
        return card ? nameCard(card) : undefined;
    });
    rideHandle(transition, 'back', () => (cardTitle && detail ? { cardTitle, ...detail } : null));
}
