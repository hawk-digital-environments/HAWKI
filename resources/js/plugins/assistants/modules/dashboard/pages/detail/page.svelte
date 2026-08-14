<script lang="ts">

    import type {AssistantAvatar} from "$lib/plugins/assistants/types/assistant/AssistantAvatar";
    import FavButton from "$lib/plugins/assistants/components/favButton/FavButton.svelte";
    import Button from "$lib/components/ui/button/Button.svelte";
    // import ReleaseStageStatus from "$lib/components/assistant/assistantBuilderComponents/ReleaseStageStatus.svelte";
    // import RiskStatus from "$lib/components/assistant/assistantBuilderComponents/RiskStatus.svelte";
    import StatusCard from "$lib/plugins/assistants/components/report/StatusCard.svelte";
    import FeedbackPanel from "$lib/plugins/assistants/components/feedbackPanel/FeedbackPanel.svelte";
    import ReceivedFeedbackList from "$lib/plugins/assistants/components/feedbackPanel/ReceivedFeedbackList.svelte";
    import type { AssistantFeedback } from "$lib/plugins/assistants/types/assistant/AssistantFeedback"
    import VersionTimeline from "$lib/plugins/assistants/components/versionTimeline/VersionTimeline.svelte";
    import VersionCard from "$lib/plugins/assistants/components/versionTimeline/VersionCard.svelte";
    import AssistantBanner from "$lib/plugins/assistants/components/avatarBuilder/AssistantBanner.svelte";
    import AssistantAvatarIcon from "$lib/plugins/assistants/components/avatarBuilder/AssistantAvatarIcon.svelte";
    // import {toggleAssistantFavorite, assistantDependency} from "$lib/data/api/resources/assistant/assistantsClient";
    // import {assistantListStore} from "$lib/stores/assistants/AssistantListStore.svelte";
    // import {submitAssistantFeedbacks} from "$lib/data/api/resources/assistant/AssistantFeedbackClient";
    // import {useToastContext} from "$lib/components/ui/toast/ToastContext.svelte";
    // import {ApiError} from "$lib/data/api/errors";
    import {ValidationState} from "$lib/plugins/assistants/types/enums/ValidationState";
    // import {assistantOptionsStore} from "$lib/stores/assistants/AssistantOptionsStore.svelte";
    // import {assistantBuilderStore} from "$lib/stores/assistants/AssistantBuilderStore.svelte";
    // import {goto, invalidate} from "$app/navigation";
    import {BACKGROUNDS} from "$lib/plugins/assistants/presets/backgrounds";
    import SplitIcon from "$lib/components/ui/icons/iconset/SplitIcon.svelte";
    import LinkSquare01Icon from "$lib/components/ui/icons/iconset/LinkSquare01Icon.svelte";
    import UserIcon from "$lib/components/ui/icons/iconset/UserIcon.svelte";
    import HashtagIcon from "$lib/components/ui/icons/iconset/HashtagIcon.svelte";
    import ViewIcon from "$lib/components/ui/icons/iconset/ViewIcon.svelte";
    import Clock01Icon from "$lib/components/ui/icons/iconset/Clock01Icon.svelte";
    import ArrowLeft01Icon from "$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import {
        ASSISTANT_DETAIL_INCLUDES,
        getAssistant,
        toggleAssistantFavorite
    } from "$plugins/assistants/api/resources/assistantsClient";
    import type {Assistant} from "$lib/plugins/assistants/types/assistant/Assistant";
    import RemixDetails from "$lib/plugins/assistants/components/assistantBrowser/RemixDetails.svelte";

    import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte.js';
    const {goToRoute, route, path, debug, params} = useRouter();



    const {__} = useTranslator();
    let assistant = $state<Assistant | undefined>(undefined);
    let loading = $state(true);
    let error = $state<Error | null>(null);

    // CHECK AWAIT Syntax from Svelte
    $effect(() => {
        console.log(params)

        loading = true;
        getAssistant(params.id, {
            include: [...new Set([...ASSISTANT_DETAIL_INCLUDES])],
        })
        .then(result => { assistant = result; })
        .catch(err => { error = err; })
        .finally(() => { loading = false; });
    });

    // const toast = useToastContext();

    /** Persist the favourite toggle, surfacing any failure as a toast. */
    async function onFavoriteChange(active: boolean) {
        try {
            await toggleAssistantFavorite(assistant, active);
            // assistantListStore.updateAssistant({ ...assistant, isFavorite: active });
            // await invalidate(assistantDependency(assistant.id));
        } catch (err) {
            // toast.error(ApiError.from(err).userMessage);
        }
    }

    // Fall back to a neutral appearance for legacy assistants that predate the
    // avatar builder's Erscheinungsbild (background + symbol).
    const avatar = $derived<AssistantAvatar>(
        {
            iconCss: BACKGROUNDS[0].value,
            name: assistant.name?.slice(0, 1) ?? '?'
        }
    );

    const lastUpdateLabel = $derived(
        assistant.versions[0]?.createdAt ?
            new Date(assistant.versions[0]?.createdAt).toLocaleDateString('de-DE'):
            '—',
    );

    const startRemix = async () => {
        // try {
        //     await assistantOptionsStore.load();
        //     await assistantBuilderStore.remix(assistant);
        //     await goto("/builder/advanced/general");
        // } catch {
        //     // The failure is already reported to the user by the store that threw
        //     // (assistantOptionsStore.load / assistantBuilderStore.startNew each push
        //     // their own toast). Just swallow here so we don't navigate into a
        //     // half-created builder or show a duplicate toast.
        // }
    };

    const startEdit = async () => {
    //     await assistantOptionsStore.load();
    //      await assistantBuilderStore.edit(assistant);
    //     await goto("/builder/advanced/general");
    }

    async function onFeedbackSend(value: string) {
        // try {
        //     const feedback = await submitAssistantFeedbacks(value, assistant);
        //     feedbacks = [...feedbacks, feedback];
        //     toast.success(__('assistants.detail.feedback_sent'));
        // } catch (err) {
        //     toast.error(ApiError.from(err).userMessage);
        // }
    }


    const usageLabel = $derived(
        assistant.usageCount != null
            ? `${assistant.usageCount.toLocaleString('de-DE')} ${__('assistants.detail.meta_usage_unit')}`
            : '—',
    );

</script>
{#if loading}
    <p>Loading...</p>
{:else if error}
    <p>Error: {error.message}</p>
{:else if assistant}

<div class="page-wrapper">
    <div class="page-content">

        <div class="cover">
            <button
                class="back"
                aria-label={__('assistants.detail.back')}
                style:background="oklch(100% 0 0 / 0.9)"
            >
                <span class="icon" style:color="oklch(20% 0 0)">
                    <ArrowLeft01Icon size="1em" />
                </span>
            </button>
            <!--{#if assistant.actionPermissions?.update === true}-->
            <!--    <button-->
            <!--        class="edit"-->
            <!--        aria-label={__('assistants.detail.edit_aria')}-->
            <!--        style:background="oklch(100% 0 0 / 0.9)"-->
            <!--        onclick={startEdit}-->
            <!--    >-->
            <!--        <span class="icon" style:color="oklch(20% 0 0)">-->
            <!--            <Settings03Icon size={18} />-->
            <!--        </span>-->
            <!--        <span class="label" style:color="oklch(20% 0 0)">{__('assistants.detail.edit')}</span>-->
            <!--    </button>-->
            <!--{/if}-->
<!--            <AssistantBanner assistantAvatar={avatar} />-->
        </div>

        <div class="overview">
            <div class="avatar">
                <AssistantAvatarIcon size="large" assistantAvatar={avatar} />
            </div>

            <div class="head">
                <div class="title-row">
                    <div class="name-wrapper">
                        <h2 class="name">{assistant.name}</h2>
                        <p class="handle">@{assistant.handle}</p>
                    </div>

                    <div class="controls">
                        <FavButton
                            id="favBtn"
                            isActive={assistant.isFavorite}
                            color="var(--color-text)"
                            background="transparent"
                            onchange={onFavoriteChange}
                        />

                        <!--{#if assistant.actionPermissions?.remix}-->
                        <Button
                            variant="stroke"
                            size="md"
                            iconLeft={SplitIcon}
                            onclick={startRemix}
                        >{__('assistants.detail.remix')}</Button>
                        <!--{/if}-->
                        <Button
                            variant="fill"
                            size="md"
                            iconLeft={LinkSquare01Icon}
                            onclick={() => { console.log('test'); }}
                        >{__('assistants.detail.try_out')}</Button>
                    </div>
                </div>

                <p class="description">{assistant.description}</p>
            </div>
        </div>

        <div class="badges">
<!--            <ReleaseStageStatus stage={assistant.releaseStage} />-->
            {#if assistant.riskLevel}
<!--                <RiskStatus level={assistant.riskLevel} />-->
            {/if}
        </div>

        <div class="metadata">
            <StatusCard
                label={__('assistants.detail.meta_creator')}
                status={assistant.creator.displayName}
                size="large"
                icon={UserIcon}
                type={ValidationState.UNKNOWN} />
            <StatusCard
                label={__('assistants.detail.meta_version')}
                status={assistant.versions[0]?.version ?? '—'}
                size="large"
                icon={HashtagIcon}
                type={ValidationState.UNKNOWN} />
            <StatusCard
                label={__('assistants.detail.meta_usage')}
                status={usageLabel}
                size="large"
                icon={ViewIcon}
                type={ValidationState.UNKNOWN} />
            <StatusCard
                label={__('assistants.detail.meta_updated')}
                status={lastUpdateLabel}
                size="large"
                icon={Clock01Icon}
                type={ValidationState.UNKNOWN} />
        </div>

        <div class="tags">
            {#if assistant.category}
                <span class="tag tag-accent">{__(assistant.category.text)}</span>
            {/if}
            {#each assistant.tags as tag}
                <span class="tag">{tag.text}</span>
            {/each}
        </div>


        {#if assistant.remixedAssistant}
            <RemixDetails
                    remixedAssistant={assistant.remixedAssistant}
                    creator={assistant.remixCreator ?? null}
            />
        {/if}

        {#if assistant.riskLevel}
            <hr>

            <section class="section">
                <div class="section-head">
                    <h3 class="section-title">{__('assistants.detail.trust_title')}</h3>
<!--                    <RiskStatus level={assistant.riskLevel} />-->
                </div>
                {#if assistant.riskNote}
                    <p class="section-text">{assistant.riskNote}</p>
                {/if}
            </section>
        {/if}

        <hr>

        <FeedbackPanel
            assistant={assistant}
            onsend={onFeedbackSend}
        />

        <hr>
        <!--{#if assistant.actionPermissions?.viewAssistantFeedback && feedbacks.length > 0}-->
        <!--    <ReceivedFeedbackList-->
        <!--        feedback={feedbacks}-->
        <!--    />-->
        <!--{/if}-->

        {#if assistant.versions.length > 0}
            <hr>

            <section class="section">
                <h3 class="section-title">{__('assistants.detail.history_title')}</h3>
                <VersionTimeline>
                    {#each [...assistant.versions].reverse() as version}
                        <VersionCard
                            version={version.version}
                            date={new Date(version.createdAt).toLocaleDateString('de-DE')}
                            note={version.changedKeys ?? version.text}
                        />
                    {/each}
                </VersionTimeline>
            </section>
        {/if}

    </div>
</div>

{/if}

<style>
    /* Page shell — mirrors the store/builder page conventions: a single
       vertical scroll region with a centred, padded content column. */
    .page-wrapper {
        height: 100%;
        overflow-y: auto;
        overflow-x: hidden;
    }
    .page-content {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        width: 100%;
        margin: 0 auto;
        padding: var(--space-6);
    }

    /* Cover: the avatar banner (gradient + symbol) as a wide hero. Height is
       lifted from the card default via :global so the shared component still
       drives the look. */
    .cover {
        position: relative;
        width: 100%;
        border-radius: var(--corner-md);
        overflow: hidden;
        border: var(--border);
    }
    .cover :global(.banner-container) {
        height: 13rem;
    }
    .cover :global(.banner-container .symbol) {
        font-size: 5rem;
    }
    .back,
    .edit {
        position: absolute;
        top: var(--space-3);
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        padding: 0;
        border: none;
        border-radius: var(--corner-full);
        backdrop-filter: blur(6px);
        cursor: pointer;
        transition: background-color var(--transition-fast), transform var(--transition-fast);
    }
    .back {
        left: var(--space-3);
    }
    .edit {
        right: var(--space-3);
        width: auto;
        gap: var(--space-1_5);
        padding: 0 var(--space-3);
    }
    .edit .icon {
        font-size: var(--font-size-sm);
    }
    .edit .label {
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        white-space: nowrap;
    }
    .back:hover,
    .edit:hover {
        transform: scale(1.06);
    }
    .back .icon,
    .edit .icon {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--font-size-lg);
    }
    .back .icon :global(svg),
    .edit .icon :global(svg) {
        display: block;
    }

    /* Overview: avatar tile beside the name/handle, controls, description. */
    .overview {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: var(--space-4);
        align-items: start;
    }
    .overview .avatar :global(.icon-container) {
        border: var(--border);
    }
    .head {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        min-width: 0;
    }
    .title-row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: var(--space-4);
    }
    .name-wrapper {
        display: flex;
        flex-direction: column;
        gap: var(--space-0_5);
        min-width: 0;
    }
    .name {
        margin: 0;
        font-size: var(--font-size-xl);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.01em;
        color: var(--color-text);
    }
    .handle {
        margin: 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }
    .controls {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        flex-shrink: 0;
    }
    .description {
        margin: 0;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }

    /* Badge row (release stage + risk pill). */
    .badges {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--space-2);
    }

    /* Metadata cards. */
    .metadata {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
        gap: var(--space-3);
    }
    /* Accent-blue leading icons — same label colour as the filled "Ausprobieren"
       button. The cards themselves stay neutral (ValidationState.UNKNOWN), so
       this is a page-level override rather than a StatusCard variant. */
    .metadata :global(.status-card .icon) {
        color: var(--color-accent-text);
    }
    /* Tag pills — neutral surface pills with an accent-filled category, echoing
       the sidebar's accent-100 highlight language. */
    .tags {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
    }
    .tag {
        display: inline-flex;
        align-items: center;
        height: 1.75rem;
        padding: 0 var(--space-3);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium);
        color: var(--color-text-muted);
        background: var(--color-surface-raised);
        border: var(--border);
        border-radius: var(--corner-full);
        white-space: nowrap;
    }
    .tag-accent {
        color: var(--color-accent-text);
        background: var(--color-accent-100);
        border-color: transparent;
    }

    /* Sections. */
    .section {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
    }
    .section-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--space-3);
    }
    .section-title {
        margin: 0;
        font-size: var(--font-size-lg);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.01em;
        color: var(--color-text);
    }
    .section-text {
        margin: 0;
        max-width: 60ch;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }

    hr {
        width: 100%;
        border: none;
        border-top: var(--divider);
        margin: 0;
    }

    /* Narrow screens: reclaim horizontal space and let the header adapt instead
       of staying locked to the desktop two-column / inline-controls layout. */
    @media (max-width: 40rem) {
        .page-content {
            gap: var(--space-4);
            padding: var(--space-4);
        }
        .cover :global(.banner-container) {
            height: 9rem;
        }
        .cover :global(.banner-container .symbol) {
            font-size: 3.5rem;
        }
        /* Stack the avatar tile above the name/handle. */
        .overview {
            grid-template-columns: 1fr;
        }
        /* Controls drop below the name and stretch to fill the row. */
        .controls {
            width: 100%;
            flex-wrap: wrap;
        }
        .controls :global(.button) {
            flex: 1 1 8rem;
        }
    }
</style>
