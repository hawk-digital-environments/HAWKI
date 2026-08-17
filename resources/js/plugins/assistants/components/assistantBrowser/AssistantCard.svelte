<script lang="ts">
    import ReleaseStageStatus from "$lib/plugins/assistants/components/assistantBuilderComponents/ReleaseStageStatus.svelte";
    import AssistantBanner from "$lib/plugins/assistants/components/avatarBuilder/AssistantBanner.svelte";
    import AssistantAvatarIcon from "$lib/plugins/assistants/components/avatarBuilder/AssistantAvatarIcon.svelte";
    import type {Assistant} from "$lib/plugins/assistants/types/assistant/Assistant";
    import type {AssistantAvatar} from "$lib/plugins/assistants/types/assistant/AssistantAvatar";
    import FavButton from "$lib/plugins/assistants/components/favButton/FavButton.svelte";
    // import {toggleAssistantFavorite, assistantDependency} from "$lib/data/api/resources/assistant/assistantsClient";
    // import {assistantListStore} from "$lib/stores/assistants/AssistantListStore.svelte";
    // import {invalidate} from "$app/navigation";
    import {useToastContext} from "$lib/components/ui/toast/ToastContext.svelte.js";
    // import {ApiError} from "$lib/data/api/errors";
    import {BACKGROUNDS} from "$lib/plugins/assistants/presets/backgrounds";
    import SplitIcon from "$lib/components/ui/icons/iconset/SplitIcon.svelte";
    // import {getAvatar} from "$lib/data/api/resources/userAvatarClient";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import {useRouter} from "$lib/components/ui/routing/hooks/useRouter.svelte";
    import Link from "$lib/components/util/link/Link.svelte";
    import {getAvatar} from "$plugins/assistants/api/resources/userAvatarClient";
    const {goToRoute, route, path, debug, params, p} = useRouter();


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
            console.log('fetch')
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
        // try {
        //     // @todo: Double check assistant Dependency after integration in HAWKI.
        //     await toggleAssistantFavorite(assistant, active);
        //     // Keep the browsed list truthful …
        //     assistantListStore.updateAssistant({ ...assistant, isFavorite: active });
        //     // … and drop the detail page's preloaded data, which SvelteKit already
        //     // fetched (with the old value) when the pointer entered this card.
        //     await invalidate(assistantDependency(assistant.id));
        // } catch (err) {
        //     toast.error(ApiError.from(err).userMessage);
        // }
    }

    // Fall back to a neutral appearance for legacy assistants that predate the
    // avatar builder's Erscheinungsbild (background + symbol).
    const avatar = $derived<AssistantAvatar>(
        assistant.avatar ?? { iconCss: BACKGROUNDS[0].value, name: assistant.name?.slice(0, 1) ?? '?' }
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
                <span class="label">{assistant.name}</span>
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
        height: fit-content;
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
            background-color var(--transition-fast),
            transform var(--transition-fast);
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
    }
    .header .label{
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }
    .header .handle{
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
    .description{
        height: 100%;
        text-overflow: ellipsis;
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
