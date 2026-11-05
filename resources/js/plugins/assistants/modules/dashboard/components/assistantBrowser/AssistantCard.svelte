<script lang="ts">
    import ReleaseStageStatus from "$plugins/assistants/modules/builder/components/ReleaseStageStatus.svelte";
    import AssistantBanner from "$plugins/assistants/components/avatarBuilder/AssistantBanner.svelte";
    import type {Assistant} from "$plugins/assistants/types/assistant/Assistant";
    import FavButton from "$plugins/assistants/modules/dashboard/components/favButton/FavButton.svelte";
    import {toggleAssistantFavorite} from "$plugins/assistants/api/resources/assistantsClient";
    import {useToastContext} from "$lib/components/ui/toast/ToastContext.svelte.js";
    import SplitIcon from "$lib/components/ui/icons/iconset/SplitIcon.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import {useRouter} from "$lib/components/ui/routing/hooks/useRouter.svelte";
    import OverflowTooltip from "$lib/components/ui/tooltip/OverflowTooltip.svelte";
    import {getAvatar} from "$plugins/assistants/api/resources/userAvatarClient";
    import {rememberDetailReturnPath} from "$plugins/assistants/modules/dashboard/contexts/detailReturn";
    import {morphToDetail} from "$plugins/assistants/utils/morphToDetail";
    import {Spring} from "svelte/motion";
    import {assistantRowAppearance} from "$plugins/assistants/utils/assistantRowAppearance";
    import {useReducedMotion} from "$lib/utils/transitions/reducedMotion.svelte";
    // The handle is kept because `path` is a live getter — the detail page's
    // back button needs it read at click time.
    const router = useRouter();
    const {goToRoute, p} = router;


    const {__} = useTranslator();

    let {
        assistant
    } = $props<{
        assistant: Assistant;
    }>();

    const toast = useToastContext();

    let userAvatarUrl = $state<string | null>(null);
    $effect(() => {
        if(assistant.creator.avatar) {
            getAvatar(assistant.creator.avatar)
                .then(url => {
                    userAvatarUrl = url;
            });

            // Cleanup: revoke the object URL when identifier changes or component unmounts
            return () => {
                if (userAvatarUrl) {
                    URL.revokeObjectURL(userAvatarUrl);
                }
            };
        }
    });


    /** Persist the favourite toggle; the client throws an ApiError on failure,
        which we surface as a toast so the click isn't silently lost. */
    async function onFavoriteChange(active: boolean) {
        try {
            // @todo: Double check assistant Dependency after load function is implemented.
            await toggleAssistantFavorite(assistant, active);
            // Keep the browsed list truthful …
            // assistantListStore.updateAssistant({ ...assistant, isFavorite: active });
            // … and drop the detail page's preloaded data, which SvelteKit already
            // fetched (with the old value) when the pointer entered this card.
            // await invalidate(assistantDependency(assistant.id));
        } catch (err) {
            // toast.error(ApiError.from(err).userMessage);
        }
    }

    // The assistant's own color (its gradient's main stop) for the hover outline.
    const assistantColor = $derived(assistantRowAppearance(assistant).colors.to);

    /* Magnetic hover: the card leans toward the pointer, on a spring so it
       trails the cursor and settles back with a little give. It already
       starts to react when the pointer is near (MAGNET_REACH past its edge),
       fading in as it approaches, so entering a card is a pull rather than a
       switch. `x`/`y` run from -1 to 1 (pointer offset from the card's
       centre); the CSS turns them into pixels (4px/3px, matching
       MAGNET_MAX). */
    const MAGNET_SPRING = {stiffness: 0.2, damping: 0.5};
    const MAGNET_MAX = {x: 4, y: 3};
    const MAGNET_REACH = 32;
    const magnet = new Spring({x: 0, y: 0}, MAGNET_SPRING);
    const reducedMotion = useReducedMotion();
    let cardEl = $state<HTMLElement | null>(null);

    // On the window, because the reach extends past the card's own box.
    function onMagnetMove(event: PointerEvent) {
        if (!cardEl) return;
        // Mouse/pen only — on touch the "hover" is a tap that navigates.
        if (event.pointerType === 'touch' || reducedMotion.current) {
            release();
            return;
        }
        const r = cardEl.getBoundingClientRect();
        // Measured against the untranslated box, so the pull can't feed
        // back into itself.
        const halfW = r.width / 2;
        const halfH = r.height / 2;
        const cx = r.left - magnet.current.x * MAGNET_MAX.x + halfW;
        const cy = r.top - magnet.current.y * MAGNET_MAX.y + halfH;
        const dx = event.clientX - cx;
        const dy = event.clientY - cy;

        // Distance past the card's edge (0 inside); full pull inside, fading
        // to none at MAGNET_REACH.
        const gap = Math.hypot(Math.max(0, Math.abs(dx) - halfW), Math.max(0, Math.abs(dy) - halfH));
        if (gap >= MAGNET_REACH) {
            release();
            return;
        }
        const strength = (1 - gap / MAGNET_REACH) ** 2;
        magnet.target = {x: clamp(dx / halfW) * strength, y: clamp(dy / halfH) * strength};
    }

    function release() {
        if (magnet.target.x !== 0 || magnet.target.y !== 0) magnet.target = {x: 0, y: 0};
    }

    function clamp(v: number) {
        return Math.max(-1, Math.min(1, v));
    }

</script>
<!-- Release when the pointer leaves the window (no element to move to). -->
<svelte:window
    onpointermove={onMagnetMove}
    onpointerout={(event) => { if (!event.relatedTarget) release(); }}
/>
<!--@todo: Link truned to button because style didn't budge-->
<!-- data-assistant-card: the detail page's back morph finds its card by it (morphToDetail). -->
<button
    class="assistant-card"
    data-assistant-card={assistant.id}
    style:--assistant-color={assistantColor}
    style:--magnet-x={magnet.current.x}
    style:--magnet-y={magnet.current.y}
    bind:this={cardEl}
    onclick={(event)=>{
        rememberDetailReturnPath(router.path);
        // Banner morphs into the detail page's cover (see morphToDetail).
        morphToDetail(event.currentTarget, () => goToRoute(
           p('assistants.dashboard.details', { id: assistant.id })
        ));
    }}
>
    <div class="cover">
        <AssistantBanner assistantAvatar={assistant.avatar} fade />
        <div
            class="favourite-btn"
            role="presentation"
        >
            <FavButton
                    id="favBtn"
                    isActive={assistant.isFavorite}
                    color="oklch(20% 0 0)"
                    background="oklch(100% 0 0 / 0.9)"
   					onchange={onFavoriteChange}
            />
        </div>
        {#if assistant.allowRemix}
            <div class="remix-tag tag">
                <span class="icon"><SplitIcon size={16} /></span>
                <span class="text">{__('assistants.card.remix')}</span>
            </div>
        {/if}
    </div>
    <div class="content">
        <div class="info">
            <div class="header">
                <OverflowTooltip value={assistant.name} focusable={false} />
                <span class="handle">@{assistant.handle}</span>
            </div>
            <div class="tags">
                <span class="category">
                    {assistant.category? __(assistant.category.text) : __('assistants.card.category_unknown')}
                </span>
                {#if assistant.release_stage}
                    <ReleaseStageStatus
                        stage={assistant.releaseStage}
                    />
                {/if}


            </div>
            <div class="description">
                {assistant.description}
            </div>
        </div>
        <div class="footer">
            <div class="creator-info">
                <div class="avatar">
                    {#if userAvatarUrl}
                        <img src={userAvatarUrl} alt="">
                    {:else}
                        <span class="creator-initials">
                            {assistant.creator.displayName.slice(0,2)}
                        </span>
                    {/if}

                </div>
                    <div class="creator-text">
                    <span class="title">{assistant.creator.displayName}</span>
                    {#if assistant.remixedAssistant}
                        <div class="remix-from">
                            <span>{__('assistants.card.remixed_via')}</span>
                            <span>
                            {assistant.remixedAssistant.name}
                        </span>
                        </div>
                    {/if}
                </div>

            </div>
        </div>

    </div>
</button>


<style>
    .assistant-card {
        position: relative;
        display: flex;
        flex-direction: column;
        /* No height:fit-content — the card fills its grid track so all cards
           in a row share the (clamped, uniform) height. */
        width: 100%;
        padding: 0;
        text-align: start;
        /* Fill the grid track; the grid's minmax controls the effective size, so
           the card no longer needs its own min/max width. */
        min-width: 0;
        overflow: hidden;
        border: var(--border);
        /* Concentric with the round 2rem corner controls (favourite, remix):
           their 1rem radius + their --space-2 inset = --corner-lg. */
        border-radius: var(--corner-lg);
        background-color: var(--color-surface-raised);
        /* Position comes from the magnet spring (script), so no transform
           transition here — it would fight the spring. 4px/3px must match
           MAGNET_MAX. */
        translate:
            calc(var(--magnet-x, 0) * 4px)
            calc(var(--magnet-y, 0) * 3px);
        transition:
            box-shadow var(--duration-fast) var(--easing-spring),
            border-color var(--duration-fast) var(--easing-spring);
        color: inherit;
        text-decoration: none;
    }
    /* Depth: the glyph follows the pointer a little further than the card
       (on top of the card's own 4px/3px pull). */
    .assistant-card :global(.symbol) {
        translate:
            calc(var(--magnet-x, 0) * 4px)
            calc(var(--magnet-y, 0) * 2.5px);
    }
    .assistant-card:hover{
        border-color: var(--assistant-color, var(--color-accent-200));
        box-shadow: var(--elevation-2);
    }

    .cover {
        position: relative;
        overflow: hidden;
    }
    /* The banner is the card's only avatar: taller than the default and
       dissolving into the card surface toward the bottom, so the cover and
       the content read as one continuous plane. */
    .cover :global(.banner-container) {
        /* 8rem of visible cover + the 2.5rem tail the content overlaps
           (see .content's negative margin). */
        height: 10.5rem;
        /* Keep the glyph centred in the visible 8rem, clear of the title. */
        align-items: flex-start;
        padding-top: 2.25rem;
        box-sizing: border-box;
    }
    .favourite-btn{
        position: absolute;
        top: var(--space-2);
        left: var(--space-2);
        z-index: 1;
    }
    .tag{
        display: flex;
        flex-direction: row;
        height: 1.5rem;
        align-items: center;
        padding: 0 var(--space-2_5);
        column-gap: var(--space-1_5);
        overflow: hidden;
        /* Tags keep a fixed white fill in both themes, so the text is always
           dark rather than following the theme's text color. */
        color: oklch(20% 0 0);
        border-radius: var(--corner-full);
        background-color: oklch(100% 0 0 / 0.9);
        backdrop-filter: blur(6px);
    }
    .tag .text{
        font-size: var(--font-size-xxs);
        line-height: 1;
        font-weight: var(--font-weight-medium);
    }

    .remix-tag{
        position: absolute;
        top: var(--space-2);
        right: var(--space-2);
        /* Match the favourite button's height. */
        height: 2rem;
        cursor: pointer;
        transition:
            background-color var(--duration-fast),
            transform var(--duration-fast);
    }
    /* Same hover feedback as the favourite button. */
    .remix-tag:hover {
        background-color: oklch(100% 0 0 / 1);
        transform: scale(1.06);
    }
    .remix-tag .icon {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--font-size-sm);
    }
    .remix-tag .icon :global(svg) {
        display: block;
    }

    .tags{
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--space-1_5);
    }
    .category{
        display: inline-flex;
        align-items: center;
        height: 1.5rem;
        padding: 0 var(--space-2_5);
        border-radius: var(--corner-full);
        background-color: var(--color-surface-light);
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium);
        line-height: 1;
    }



    .content{
        /* Absorb extra height when the stretched card is taller than the
           content, keeping the creator footer pinned to the card's bottom. */
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        min-height: 50%;
        /* Pulled up over the banner's fading tail, so the cover's colour
           runs on behind the name and handle. Transparent so the fade shows
           through; the card itself provides the surface below it. */
        position: relative;
        margin-top: -2.5rem;
    }
    .info{
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        row-gap: var(--space-2);
        /* The banner's fade already separates cover and text. */
        padding: var(--space-1) var(--space-4) var(--space-4);
    }
    .header{
        display: flex;
        flex-direction: column;
        gap: var(--space-0_5);
        /* Typography for the OverflowTooltip'd name, consumed through that
           component's custom properties so the style stays scoped here
           instead of reaching into the child with :global(). */
        --overflow-text-font-size: var(--font-size-base);
        --overflow-text-font-weight: var(--font-weight-medium);
        --overflow-text-color: var(--color-text);
        /* Tight lines so name and handle read as one block. */
        --overflow-text-line-height: var(--line-height-tight);
    }
    .header .handle{
        line-height: var(--line-height-tight);
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
    /* Fixed number of lines before truncating (same pattern as VersionCard)
       so long descriptions can't grow one card taller than its neighbours;
       the full text stays available on the assistant's detail page. */
    .description{
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        line-clamp: 3;
        overflow: hidden;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }


    .footer{
        display: flex;
        flex-direction: row;
        border-top: var(--divider);
        height: 3.25rem;
        align-items: center;
        padding: 0 var(--space-4);
    }
    .creator-info{
        display: flex;
        gap: var(--space-2);
        align-items: center;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
    .creator-info .avatar{
        display: flex;
        width: 1.75rem;
        height: 1.75rem;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: var(--corner-full);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium);
        color: var(--color-accent-text);
        background: var(--color-accent-100);
    }
    .creator-info .avatar img{
        width: 100%;
        height: 100%;
    }
    .creator-info .remix-from{
        opacity: .75;
    }
    .creator-info .creator-text{
        display: flex;
        flex-direction: row;
    }
</style>
