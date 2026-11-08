<script lang="ts">
    import {untrack} from 'svelte';
    import AssistantBrowser from "$plugins/assistants/modules/dashboard/components/assistantBrowser/AssistantBrowser.svelte";
    import {readBrowserUrlState} from "$plugins/assistants/modules/dashboard/components/assistantBrowser/browserUrlState.js";
    import {ReleaseMode} from "$plugins/assistants/types/assistant";
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {createAssistantListContext} from '$plugins/assistants/modules/dashboard/contexts/AssistantListContext.svelte.js';
    import {assistantOptionsStore} from "$plugins/assistants/stores/AssistantOptionsStore.svelte"
    import Page from '$lib/components/ui/page/Page.svelte';
    import type {AssistantAvatar} from '$plugins/assistants/types/assistant/AssistantAvatar';

    /**
     * The kernel's route renderer instantiates page components without passing
     * any props (see core's ChatIndex.svelte), so this interface is intentionally
     * empty.
     */
    interface Props {
    }
    
    const {}: Props = $props();

    const {__} = useTranslator();
    assistantOptionsStore.load();
    // This page owns the list: it is created here, published to the subtree
    // (AssistantBrowser picks it up via useAssistantListContext), and released
    // when the page unmounts.
    const list = createAssistantListContext(useApp(), useToastContext());

    // Search text and category filters are event-driven: the search bar and
    // category bar report commits via callbacks, and each handler applies the
    // (page-specific) filter to the list. Seeding from the URL happens before
    // the single initial request below.
    const initial = readBrowserUrlState();
    let searchQuery = $state(initial.query);
    let activeFilters = $state(initial.categories);

    function applyFilter() {
        list.setFilter({
            name: searchQuery,
            assistant_category: [...activeFilters],
            release_stage: [
                ReleaseMode.ORGANIZATIONAL,
                ReleaseMode.FEDERATED
            ]
        });
    }

    function handleSearchChange(query: string) {
        searchQuery = query;
        applyFilter();
    }

    function handleFilterChange(filters: Set<string>) {
        activeFilters = filters;
        applyFilter();
    }

    applyFilter();

    // Hero title: hovering it opens a gap between the words and pops in a
    // few random avatars from the assistants currently listed.
    const title = $derived(__('assistants.store.title'));
    const titleSplit = $derived(title.indexOf(' '));
    const titleStart = $derived(titleSplit < 0 ? title : title.slice(0, titleSplit));
    const titleEnd = $derived(titleSplit < 0 ? '' : title.slice(titleSplit + 1));

    const MAX_TITLE_AVATARS = 3;
    /** Hover intent: a cursor merely passing over the title doesn't open it. */
    const TITLE_OPEN_DELAY_MS = 80;
    /** Covers the close transition (gap 250ms), so the set is only swapped while hidden. */
    const TITLE_CLOSE_MS = 260;

    type TitleAvatar = {key: string; avatar: AssistantAvatar; tilt: number; fromTilt: number};

    let titleOpen = $state(false);
    /** Between leaving and the close finishing: avatars play their own exit
     *  pose instead of reversing the entrance. */
    let titleClosing = $state(false);
    let titleAvatars = $state<TitleAvatar[]>([]);
    let repickTimer: ReturnType<typeof setTimeout> | undefined;
    let openTimer: ReturnType<typeof setTimeout> | undefined;
    let introTimer: ReturnType<typeof setTimeout> | undefined;
    let titleHovered = false;
    let introPlayed = false;

    function pickTitleAvatars(): TitleAvatar[] {
        const unique = new Map<string, AssistantAvatar>();
        for (const assistant of list.assistants) {
            const avatar = assistant.avatar;
            if (avatar) unique.set(`${avatar.name}|${avatar.iconCss}`, avatar);
        }
        const pool = [...unique.entries()];
        for (let i = pool.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [pool[i], pool[j]] = [pool[j], pool[i]];
        }
        return pool.slice(0, MAX_TITLE_AVATARS).map(([key, avatar], i) => {
            // Alternate the lean so the row reads as playful, not crooked.
            const side = i % 2 === 0 ? -1 : 1;
            const tilt = side * (2 + Math.random() * 7);
            return {key: `${key}|${Math.random()}`, avatar, tilt, fromTilt: -side * (18 + Math.random() * 16)};
        });
    }

    // Seed (and reseed) while closed, so the avatars already sit in their
    // hidden pose when the hover opens them.
    $effect(() => {
        const next = pickTitleAvatars();
        untrack(() => {
            if (!titleOpen) titleAvatars = next;
        });
    });

    /** Intro: once avatars are available, open the title once on page load
     *  and close it again after a short hold (unless the pointer is on it). */
    const TITLE_INTRO_DELAY_MS = 400;
    const TITLE_INTRO_HOLD_MS = 1600;

    $effect(() => {
        if (introPlayed || titleAvatars.length === 0) return;
        introPlayed = true;
        introTimer = setTimeout(() => {
            openTitle();
            introTimer = setTimeout(() => {
                if (!titleHovered) closeTitle();
            }, TITLE_INTRO_HOLD_MS);
        }, TITLE_INTRO_DELAY_MS);
    });

    function handleTitleEnter() {
        titleHovered = true;
        clearTimeout(introTimer);
        openTitle();
    }

    function handleTitleLeave() {
        titleHovered = false;
        clearTimeout(introTimer);
        closeTitle();
    }

    function openTitle() {
        clearTimeout(openTimer);
        openTimer = setTimeout(() => {
            clearTimeout(repickTimer);
            titleClosing = false;
            titleOpen = true;
        }, TITLE_OPEN_DELAY_MS);
    }

    function closeTitle() {
        clearTimeout(openTimer);
        if (!titleOpen) return;
        titleOpen = false;
        titleClosing = true;
        clearTimeout(repickTimer);
        // Fresh keys mount new elements straight into the hidden entrance pose.
        repickTimer = setTimeout(() => {
            titleClosing = false;
            titleAvatars = pickTitleAvatars();
        }, TITLE_CLOSE_MS);
    }

    $effect(() => () => {
        clearTimeout(repickTimer);
        clearTimeout(openTimer);
        clearTimeout(introTimer);
    });
</script>

<Page fade="short">
    <div class="page-content">

        <AssistantBrowser
                searchQuery={searchQuery}
                activeFilters={activeFilters}
                onSearchChange={handleSearchChange}
                onFilterChange={handleFilterChange}
                emptyTitle={__('assistants.store.empty_title')}
                emptyDescription={__('assistants.store.empty_description')}
        >
            {#snippet hero()}
                <h1
                        class="store-title"
                        class:open={titleOpen && titleAvatars.length > 0}
                        class:closing={titleClosing}
                        style:--count={titleAvatars.length}
                        onpointerenter={handleTitleEnter}
                        onpointerleave={handleTitleLeave}
                >
                    <span>{titleStart}</span>
                    <span class="title-gap" aria-hidden="true">
                        {#each titleAvatars as item, i (item.key)}
                            <span
                                    class="title-avatar"
                                    style:--i={i}
                                    style:--tilt="{item.tilt}deg"
                                    style:--from-tilt="{item.fromTilt}deg"
                            >
                                <span class="title-avatar-face" style={item.avatar.iconCss}>
                                    <span class="title-avatar-glyph">{item.avatar.name}</span>
                                </span>
                            </span>
                        {/each}
                    </span>
                    <span>{titleEnd}</span>
                </h1>
            {/snippet}
        </AssistantBrowser>
    </div>
</Page>

<style>
    .page-content {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        max-width: 80rem;
        width: 100%;
        margin: 0 auto;
        padding: var(--space-6);
    }

    /* Spring (damping ≈ 0.5): overshoots ~16 % and settles with one soft wobble. */
    .store-title {
        --spring: linear(0, 0.053, 0.185, 0.36, 0.547, 0.725, 0.879, 1, 1.085, 1.137, 1.16, 1.161,
            1.145, 1.119, 1.089, 1.059, 1.032, 1.009, 0.992, 0.981, 0.975, 0.973, 0.975, 0.978,
            0.983, 0.988, 0.993, 0.997, 1, 1.002, 1.004, 1.004, 1);
        --ease-smooth-out: cubic-bezier(0.22, 1, 0.36, 1);
        --avatar-size: 1.1em;
        --avatar-space: 0.24em;
        --word-space: 0.28em;

        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        font-size: calc(var(--font-size-2xl) * 1.5);
        line-height: var(--line-height-tight);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.02em;
        color: var(--color-text);
        text-align: center;
        user-select: none;
    }

    /* Closed it is just the word space; open it makes room for every avatar.
       Width is real layout, so both words glide outward from the centre. */
    .title-gap {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: var(--avatar-space);
        width: var(--word-space);
        height: var(--avatar-size);
        transition: width 250ms var(--ease-smooth-out);
    }

    .open .title-gap {
        width: calc(var(--count) * (var(--avatar-size) + var(--avatar-space)) + var(--avatar-space));
        transition: width 400ms var(--ease-smooth-out);
    }

    /* Hidden entrance pose: low, small and steeply leaning. */
    .title-avatar {
        flex: none;
        width: var(--avatar-size);
        height: var(--avatar-size);
        opacity: 0;
        filter: blur(2px);
        transform: translateY(0.18em) rotate(var(--from-tilt)) scale(0.4);
    }

    /* Exit: quick, together, no delay — shrink in place on its own tilt and
       fade, instead of swinging back out along the entrance path. */
    .closing .title-avatar {
        opacity: 0;
        filter: blur(2px);
        transform: rotate(var(--tilt)) scale(0.6);
        transition:
            transform 150ms var(--ease-smooth-out),
            opacity 150ms var(--ease-smooth-out),
            filter 150ms var(--ease-smooth-out);
    }

    /* Enter: staggered spring pop that swings in from a steep lean and
       settles on its own small tilt. */
    .open .title-avatar {
        --delay: calc(var(--i) * 40ms);
        opacity: 1;
        filter: blur(0);
        transform: translateY(0) rotate(var(--tilt)) scale(1);
        transition:
            transform 650ms var(--spring) var(--delay),
            opacity 160ms ease-out var(--delay),
            filter 240ms ease-out var(--delay);
    }

    .title-avatar-face {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        border-radius: 28%;
        transition: transform 450ms cubic-bezier(0.34, 3.85, 0.64, 1);
    }

    /* Direct hover on one avatar: a quick nudge up, a springy settle back. */
    .open .title-avatar-face:hover {
        transform: translateY(-0.08em) rotate(calc(var(--tilt) * -1)) scale(1.1);
        transition: transform 220ms var(--ease-smooth-out);
    }

    .title-avatar-glyph {
        font-size: 0.6em;
        line-height: 1;
        color: oklch(100% 0 0);
    }

    @media (--bp-md-and-smaller) {
        .store-title {
            font-size: var(--font-size-2xl);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .title-gap,
        .open .title-gap,
        .title-avatar-face {
            transition: none;
        }

        .title-avatar,
        .open .title-avatar,
        .closing .title-avatar {
            filter: none;
            transform: rotate(var(--tilt));
            transition: opacity 150ms ease-out;
        }
    }
</style>
