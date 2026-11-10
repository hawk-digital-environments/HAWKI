<script lang="ts">
    import ReleaseStageStatus from "$plugins/assistants/modules/builder/components/ReleaseStageStatus.svelte";
    import AssistantBanner from "$plugins/assistants/components/avatarBuilder/AssistantBanner.svelte";
    import type {Assistant} from "$plugins/assistants/types/assistant/Assistant";
    import FavButton from "$plugins/assistants/modules/dashboard/components/favButton/FavButton.svelte";
    import {toggleAssistantFavorite} from "$plugins/assistants/api/resources/assistantsClient";
    import {useToastContext} from "$lib/components/ui/toast/ToastContext.svelte.js";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import OverflowTooltip from "$lib/components/ui/tooltip/OverflowTooltip.svelte";
    import {getAvatar} from "$plugins/assistants/api/resources/userAvatarClient";
    import {magnetic} from "$lib/components/ui/magnetic/magnetic.svelte";
    import {assistantRowAppearance} from "$plugins/assistants/utils/assistantRowAppearance";

    const {__} = useTranslator();

    let {
        assistant,
        onOpen
    } = $props<{
        assistant: Assistant;
        /** Open the assistant's detail view; receives the card for the morph. */
        onOpen: (card: HTMLElement) => void;
    }>();

    const toast = useToastContext();

    let userAvatarUrl = $state<string | null>(null);
    let card = $state<HTMLElement>();
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

</script>
<!-- data-assistant-card: the detail modal's closing morph finds its card by it (morphToDetail). -->
<article
    class="assistant-card"
    data-assistant-card={assistant.id}
    bind:this={card}
    style:--assistant-color={assistantColor}
    {@attach magnetic()}
>
    <!-- Stretched over the whole card: the card's single primary action, a
         real button in its own right. The favourite sits as a sibling above
         it — interactive elements must never nest. -->
    <button class="open" onclick={() => card && onOpen(card)}>
        <span class="u-sr-only">{__('assistants.card.open_aria', {name: assistant.name})}</span>
    </button>
    <!-- The cover carries the avatar's CSS too, so its foreground shade
         (`color`) is inherited by the title set on the flat background. -->
    <div class="cover" style={assistant.avatar.iconCss}>
        <AssistantBanner assistantAvatar={assistant.avatar} />
        <div class="cover-title">
            <OverflowTooltip value={assistant.name} truncate="clamp" lines={2} focusable={false} />
        </div>
    </div>
    <div class="favourite-btn">
        <!-- Tinted in the cover's own foreground shade instead of a white pill. -->
        <FavButton
            isActive={assistant.isFavorite}
            color="currentColor"
            background="color-mix(in oklch, currentColor 14%, transparent)"
            onchange={onFavoriteChange}
        />
    </div>
    <div class="content">
        <div class="info">
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
</article>


<style>
    .assistant-card {
        position: relative;
        display: flex;
        flex-direction: column;
        /* No height:fit-content — the card fills its grid track so all cards
           in a row share the (clamped, uniform) height. */
        width: 100%;
        /* Fill the grid track; the grid's minmax controls the effective size, so
           the card no longer needs its own min/max width. */
        min-width: 0;
        overflow: hidden;
        border: var(--border);
        /* Concentric with the round 2rem corner controls (favourite, remix):
           their 1rem radius + their --space-2 inset = --corner-lg. */
        border-radius: var(--corner-lg);
        background-color: var(--color-surface-raised);
        /* Position comes from the magnetic attachment's spring, so no
           transform/translate transition here — it would fight it. */
        transition:
            box-shadow var(--duration-fast) var(--easing-spring),
            border-color var(--duration-fast) var(--easing-spring);
    }
    /* The card's primary action, stretched over the whole card. */
    .assistant-card .open {
        position: absolute;
        inset: 0;
        z-index: 0;
        padding: 0;
        border: none;
        border-radius: var(--corner-lg);
        background: transparent;
        cursor: pointer;
    }
    .assistant-card .open:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
    }
    /* Depth: the glyph follows the pointer a little further than the card
       (on top of the card's own pull; --magnet-x/y come from magnetic()). */
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
        /* Never squeezed: a cropped banner would be shorter than the one the
           detail morph lands on (morphToDetail), and snap at the end. */
        flex-shrink: 0;
        overflow: hidden;
    }
    /* Flat two-tone poster: a sticker-like glyph in the top corner, the name
       set big and heavy in the avatar's foreground shade across the bottom. */
    .cover :global(.banner-container) {
        height: 10.5rem;
        align-items: flex-start;
        justify-content: flex-start;
        padding: var(--space-4);
        box-sizing: border-box;
    }
    /* White sticker disc: every emoji reads on every ground (a grey ➗ on
       ink would vanish). */
    .cover :global(.symbol) {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3.5rem;
        height: 3.5rem;
        border-radius: var(--corner-full);
        background: oklch(100% 0 0);
        font-size: 1.9rem;
    }
    .cover-title {
        position: absolute;
        right: var(--space-4);
        bottom: var(--space-4);
        left: var(--space-4);
        /* Typography for the OverflowTooltip'd name, consumed through that
           component's custom properties. */
        --overflow-text-font-size: var(--font-size-2xl);
        --overflow-text-font-weight: 700;
        --overflow-text-color: currentColor;
        --overflow-text-line-height: 1;
        letter-spacing: -0.035em;
        text-wrap: balance;
    }
    /* The 2-line clamp clips at the line box, and at this tight leading the
       last line's descenders (g, y) hang below it: extend the clip box with
       padding, cancelled by a negative margin so the layout doesn't move.
       Keep it small: at this leading the third line's cap tops start just
       below the descenders, and a larger extension lets them peek through. */
    .cover-title :global(.overflow-text) {
        padding-bottom: 0.08em;
        margin-bottom: -0.08em;
        /* Hug the text, so the title morph (morphToDetail) scales the words
           rather than a box of empty space beside them. */
        width: fit-content;
    }
    .favourite-btn{
        position: absolute;
        top: var(--space-3);
        right: var(--space-3);
        z-index: 1;
    }

    .tags{
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--space-1_5) var(--space-2);
    }
    .category{
        display: inline-flex;
        align-items: center;
        height: 1.375rem;
        padding: 0 var(--space-2);
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
    }
    .info{
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        row-gap: var(--space-2);
        padding: var(--space-4);
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
        gap: var(--space-2);
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
        gap: .25em;
    }
</style>
