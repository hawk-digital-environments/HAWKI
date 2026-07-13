<script lang="ts">
    import ReleaseStageStatus from "$plugins/assistants/modules/builder/components/ReleaseStageStatus.svelte";
    import AssistantBanner from "$plugins/assistants/components/avatarBuilder/AssistantBanner.svelte";
    import AssistantAvatarIcon from "$plugins/assistants/components/avatarBuilder/AssistantAvatarIcon.svelte";
    import type {Assistant} from "$plugins/assistants/types/assistant/Assistant";
    import type {AssistantAvatar} from "$plugins/assistants/types/assistant/AssistantAvatar";
    import FavButton from "$plugins/assistants/modules/dashboard/components/favButton/FavButton.svelte";
    import {toggleAssistantFavorite} from "$plugins/assistants/api/resources/assistantsClient";
    import {useToastContext} from "$lib/components/ui/toast/ToastContext.svelte.js";
    import {resolveAssistantAvatar} from "$plugins/assistants/utils/resolveAssistantAvatar";
    import SplitIcon from "$lib/components/ui/icons/iconset/SplitIcon.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import {useRouter} from "$lib/components/ui/routing/hooks/useRouter.svelte";
    import OverflowTooltip from "$lib/components/ui/tooltip/OverflowTooltip.svelte";
    import {getAvatar} from "$plugins/assistants/api/resources/userAvatarClient";
    const {goToRoute, p} = useRouter();


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

    // Fall back to a neutral appearance for legacy assistants that predate
    // the avatar builder's Erscheinungsbild (see resolveAssistantAvatar).
    const avatar = $derived<AssistantAvatar>(
        resolveAssistantAvatar(assistant.avatar, assistant.name)
    );


</script>
<!--@todo: Link truned to button because style didn't budge-->
<button
    class="assistant-card"
    onclick={()=>{goToRoute(
       p('assistants.dashboard.details', { id: assistant.id })
    )

    }}
>
    <div class="cover">
        <AssistantBanner assistantAvatar={assistant.avatar} />
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
        <div class="category tag">
            <span class="text">{assistant.category? __(assistant.category.text) : __('assistants.card.category_unknown')}</span>
        </div>
    </div>
    <div class="avatar-wrap">
        <AssistantAvatarIcon size="small" assistantAvatar={avatar} />
    </div>
    <div class="content">
        <div class="info">
            <div class="header">
                <OverflowTooltip value={assistant.name} focusable={false} />
                <span class="handle">@ {assistant.handle}</span>
            </div>
            <div class="tags">
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
        border-radius: var(--corner-md);
        background-color: var(--color-surface-raised);
        transition:
            transform var(--duration-fast) var(--easing-spring),
            box-shadow var(--duration-fast) var(--easing-spring),
            border-color var(--duration-fast) var(--easing-spring);
        color: inherit;
        text-decoration: none;
    }
    .assistant-card:hover{
        transform: translateY(-4px);
        border-color: var(--color-accent-200);
        box-shadow: var(--elevation-2);
    }

    .cover {
        position: relative;
        overflow: hidden;
    }

    /* Avatar icon straddles the banner / content boundary, lifted by a surface
       ring — mirroring the builder's Erscheinungsbild preview. */
    .avatar-wrap {
        position: absolute;
        top: calc(6rem - 1.5rem);
        left: var(--space-4);
        z-index: 2;
        padding: 3px;
        background: var(--color-surface-raised);
        border-radius: calc(var(--corner-md) + 3px);
        box-shadow: var(--elevation-1);
    }
    .favourite-btn{
        position: absolute;
        top: var(--space-3);
        left: var(--space-3);
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
        top: var(--space-3);
        right: var(--space-3);
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

    .category{
        position: absolute;
        bottom: var(--space-3);
        right: var(--space-3);
        z-index: 1;
    }



    .content{
        /* Absorb extra height when the stretched card is taller than the
           content, keeping the creator footer pinned to the card's bottom. */
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        min-height: 50%;
        background-color: var(--color-surface-raised);
    }
    .info{
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        row-gap: var(--space-2);
        /* Extra top padding clears the avatar overlapping from the banner. */
        padding: var(--space-6) var(--space-4) var(--space-4);
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
    }
    .header .handle{
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
