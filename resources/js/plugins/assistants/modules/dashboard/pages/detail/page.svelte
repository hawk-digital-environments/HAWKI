<script lang="ts">
    import {untrack} from "svelte";

    import type {AssistantAvatar} from "$plugins/assistants/types/assistant/AssistantAvatar";
    import FavButton from "$plugins/assistants/modules/dashboard/components/favButton/FavButton.svelte";
    import ButtonWithTooltip from "$lib/components/ui/button/ButtonWithTooltip.svelte";
    import FeedbackPanel from "$plugins/assistants/modules/dashboard/components/feedbackPanel/FeedbackPanel.svelte";
    import ReceivedFeedbackList from "$plugins/assistants/modules/dashboard/components/feedbackPanel/ReceivedFeedbackList.svelte";
    import type { AssistantFeedback } from "$plugins/assistants/types/assistant/AssistantFeedback"
    import VersionTimeline from "$plugins/assistants/modules/dashboard/components/versionTimeline/VersionTimeline.svelte";
    import VersionCard from "$plugins/assistants/modules/dashboard/components/versionTimeline/VersionCard.svelte";
    import StatusCard from "$plugins/assistants/components/report/StatusCard.svelte";
    import AssistantBanner from "$plugins/assistants/components/avatarBuilder/AssistantBanner.svelte";
    import AssistantAvatarIcon from "$plugins/assistants/components/avatarBuilder/AssistantAvatarIcon.svelte";
    import {submitAssistantFeedbacks} from "$plugins/assistants/api/resources/assistantFeedbackClient";
    import {ApiError} from "$plugins/assistants/api/errors";
    import {ValidationState} from "$plugins/assistants/types/enums/ValidationState";
    import {resolveAssistantAvatar} from "$plugins/assistants/utils/resolveAssistantAvatar";
    import SplitIcon from "$lib/components/ui/icons/iconset/SplitIcon.svelte";
    import UserIcon from "$lib/components/ui/icons/iconset/UserIcon.svelte";
    import HashtagIcon from "$lib/components/ui/icons/iconset/HashtagIcon.svelte";
    import ViewIcon from "$lib/components/ui/icons/iconset/ViewIcon.svelte";
    import Clock01Icon from "$lib/components/ui/icons/iconset/Clock01Icon.svelte";
    import ArrowLeft01Icon from "$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte";
    import Page from "$lib/components/ui/page/Page.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import {
        ASSISTANT_DETAIL_INCLUDES,
        deleteAssistant,
        getAssistant,
        requestAssistantRelease,
        toggleAssistantFavorite
    } from "$plugins/assistants/api/resources/assistantsClient";
    import type {Assistant} from "$plugins/assistants/types/assistant/Assistant";
    import {ReleaseMode} from "$plugins/assistants/types/assistant/ReleaseMode";
    import RemixDetails from "$plugins/assistants/modules/dashboard/components/assistantBrowser/RemixDetails.svelte";

    import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte.js';
    import {detailReturnPath} from '$plugins/assistants/modules/dashboard/contexts/detailReturn.js';
    import type {RouteParams} from '$lib/components/ui/routing/index.js';
    import {useToastContext} from "$lib/components/ui/toast/ToastContext.svelte";
    import {requestBuilderIntent} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte";
    import Settings03Icon from "$lib/components/ui/icons/iconset/Settings03Icon.svelte";
    import WindowsOldIcon from "$lib/components/ui/icons/iconset/WindowsOldIcon.svelte";
    import Chatbox from "$plugins/assistants/components/testChat";
    import {growTransition} from "$lib/utils/transitions/growTransition";
    import {breakpointsQueries} from "$lib/components/util/breakpoints/breakpoints.js";
    import ChatDock from "$plugins/assistants/components/testChat/ChatDock.svelte";
    import {createChatStore} from "$plugins/assistants/components/testChat/stream/chatStore.svelte.js";
    import {createChatConfig} from "$plugins/assistants/components/testChat/stream/chatConfig.svelte.js";
    import OverflowTooltip from "$lib/components/ui/tooltip/OverflowTooltip.svelte";
    import FadeText from "$lib/components/ui/text/FadeText.svelte";
    import DropdownMenu from "$lib/components/ui/dropdown-menu/DropdownMenu.svelte";
    import DropdownMenuItem from "$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte";
    import ArrowDown01Icon from "$lib/components/ui/icons/iconset/ArrowDown01Icon.svelte";
    import ArrowUp01Icon from "$lib/components/ui/icons/iconset/ArrowUp01Icon.svelte";
    import {useBreakpoint} from "$lib/components/util/breakpoints/useBreakpoint.svelte.js";
    import DropdownMenuSub from "$lib/components/ui/dropdown-menu/DropdownMenuSub.svelte";
    import DropdownMenuRadioGroup from "$lib/components/ui/dropdown-menu/DropdownMenuRadioGroup.svelte";
    import DropdownMenuRadioItem from "$lib/components/ui/dropdown-menu/DropdownMenuRadioItem.svelte";
    import DropdownMenuSeparator from "$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte";
    import ConfirmDialog from "$lib/components/ui/dialog/ConfirmDialog.svelte";
    import Edit02Icon from "$lib/components/ui/icons/iconset/Edit02Icon.svelte";
    import Delete02Icon from "$lib/components/ui/icons/iconset/Delete02Icon.svelte";
    import SentIcon from "$lib/components/ui/icons/iconset/SentIcon.svelte";
    import TaskEdit01Icon from "$lib/components/ui/icons/iconset/TaskEdit01Icon.svelte";
    import SquareLock02Icon from "$lib/components/ui/icons/iconset/SquareLock02Icon.svelte";
    import CheckmarkBadge01Icon from "$lib/components/ui/icons/iconset/CheckmarkBadge01Icon.svelte";
    import GlobeIcon from "$lib/components/ui/icons/iconset/GlobeIcon.svelte";

    /**
     * The kernel's route renderer hands each matched page its route params
     * (see core's ChatConversation.svelte), hence the optional `params` prop.
     */
    interface Props {
        params?: RouteParams;
    }

    const {params = {}}: Props = $props();
    // The handle is kept (not just `goToRoute` destructured off it) because
    // `path` is a live getter — the builder needs it read at click time.
    const router = useRouter();
    const {goToRoute} = router;

    const toast = useToastContext();
    const breakpoint = useBreakpoint();

    const {__} = useTranslator();
    let assistant = $state<Assistant | undefined>(undefined);
    let loading = $state(true);
    let error = $state<Error | null>(null);
    let feedbacks = $state<AssistantFeedback[]>([]);

    // The detailed description is clamped to DESCRIPTION_COLLAPSED_LINES with
    // a bottom fade; FadeText expands it to the full text (one-way).
    const DESCRIPTION_COLLAPSED_LINES = 7;

    // CHECK AWAIT Syntax from Svelte
    $effect(() => {
        // `params` can be null before the first resolution, and the router
        // types route params loosely (string | string[]) even though `:id`
        // routes always deliver a plain string — hence the array-tolerant
        // extraction (resolves the former `@todo` about `params.id[0]`).
        const rawId = params?.id;
        const id = Array.isArray(rawId) ? rawId[0] : rawId;
        if (!id) return;

        // A conversation belongs to the assistant it was held with.
        untrack(() => testChat.clear());
        loading = true;
        getAssistant(id, {
            include: [...new Set([...ASSISTANT_DETAIL_INCLUDES])],
        })
        .then(result => {
            assistant = result;
            // `assistant_feedback` is a permission-gated relationship
            // (`viewAssistantFeedback`) — requesting it as part of the main
            // include list would 403 the *whole* fetch when denied, so it's
            // only loaded once the initial response confirms it's allowed.
            if (result.actionPermissions?.viewAssistantFeedback) {
                loadFeedbacks(result.id);
            }
        })
        .catch(err => { error = err; })
        .finally(() => { loading = false; });
    });

    async function loadFeedbacks(id: string | null): Promise<void> {
        if (!id) return;
        try {
            const withFeedback = await getAssistant(id, {
                include: ['assistant_feedback', 'assistant_feedback.user'],
            });
            feedbacks = withFeedback.feedbacks ?? [];
        } catch (err) {
            // Feedback is a secondary detail; don't fail the whole page over it.
            console.error('Failed to load assistant feedback:', err);
        }
    }


    /** Persist the favourite toggle, surfacing any failure as a toast. */
    async function onFavoriteChange(active: boolean) {
        if (!assistant) return;
        try {
            await toggleAssistantFavorite(assistant, active);
            // assistantListStore.updateAssistant({ ...assistant, isFavorite: active });
            // await invalidate(assistantDependency(assistant.id));
        } catch (err) {
            // toast.error(ApiError.from(err).userMessage);
        }
    }

    // Fall back to a neutral appearance only for legacy assistants that
    // predate the avatar builder's Erscheinungsbild (see resolveAssistantAvatar);
    // assistants with a persisted avatar render their real emoji + gradient.
    const avatar = $derived<AssistantAvatar>(
        resolveAssistantAvatar(assistant?.avatar, assistant?.name ?? '?')
    );

    const lastUpdateLabel = $derived(
        assistant?.versions[0]?.createdAt ?
            new Date(assistant.versions[0]?.createdAt).toLocaleDateString('de-DE'):
            '—',
    );

    /**
     * `BuilderContext` can only be created during its owning layout's
     * component initialization (`/builder/advanced/layout.svelte`) — never
     * from here, a click handler on an unrelated page. Calling
     * `createBuilderContext()` in this handler used to throw Svelte's
     * `set_context_after_init` error, which the surrounding `try`/`catch`
     * silently swallowed — that was the "silent failure". The fix is to not
     * create a builder session here at all: stash the intent and let the
     * builder layout's own `init()` (a valid place to create one) pick it up.
     */
    const startRemix = async () => {
        if (!assistant?.id) return;
        requestBuilderIntent({type: "remix", id: assistant.id}, router.path);
        await goToRoute("assistants.builder.general");
    };

    const startEdit = async () => {
        if (!assistant?.id) return;
        requestBuilderIntent({type: "edit", id: assistant.id}, router.path);
        await goToRoute("assistants.builder.general");
    };

    /** Delete flow: the menu's destructive entry opens a `ConfirmDialog`. A
     *  failed delete rethrows so the dialog stays open (`ConfirmDialog` only
     *  closes on a resolving confirmation); success returns to the store. */
    let deleteConfirmOpen = $state(false);

    async function onDeleteConfirm(): Promise<void> {
        if (!assistant?.id) return;
        try {
            await deleteAssistant(assistant.id);
        } catch (err) {
            toast.error(`${__('assistants.detail.delete_failed')} ${ApiError.from(err).userMessage}`);
            throw err;
        }
        toast.success(__('assistants.detail.deleted'));
        goBack();
    }

    /** The menu's publish submenu: the release stages, iconed like the
     *  builder's `ReleaseStage` picker. */
    const releaseOptions = [
        {stage: ReleaseMode.DRAFT, icon: TaskEdit01Icon, label: __('assistants.detail.release_draft')},
        {stage: ReleaseMode.PRIVATE, icon: SquareLock02Icon, label: __('assistants.detail.release_private')},
        {stage: ReleaseMode.ORGANIZATIONAL, icon: CheckmarkBadge01Icon, label: __('assistants.detail.release_organizational')},
        {stage: ReleaseMode.FEDERATED, icon: GlobeIcon, label: __('assistants.detail.release_federated')},
    ];

    /** A requested public stage that review hasn't granted yet. */
    const pendingStage = $derived(
        assistant?.requested_release_stage && assistant.requested_release_stage !== assistant.releaseStage
            ? assistant.requested_release_stage
            : null,
    );

    const releaseValueLabel = $derived(
        pendingStage
            ? __('assistants.detail.release_pending')
            : releaseOptions.find(option => option.stage === assistant?.releaseStage)?.label,
    );

    /** Mobile sheet only: whether the publish row's options are unfolded.
     *  Folds back whenever the menu closes, so it always opens collapsed. */
    let menuOpen = $state(false);
    let publishOpen = $state(false);
    $effect(() => {
        if (!menuOpen) publishOpen = false;
    });

    async function onReleaseStageChange(value: string): Promise<void> {
        if (!assistant?.id || value === assistant.releaseStage) return;
        const id = assistant.id;
        const stage = value as ReleaseMode;
        try {
            await requestAssistantRelease({...assistant, releaseStage: stage});
        } catch (err) {
            toast.error(`${__('assistants.detail.release_failed')} ${ApiError.from(err).userMessage}`);
            return;
        }
        // The server decides the outcome: a move up to a public stage only
        // opens a review (stage unchanged, `requested_release_stage` set),
        // everything else applies right away — so show its answer.
        try {
            assistant = await getAssistant(id, {include: [...ASSISTANT_DETAIL_INCLUDES]});
        } catch (err) {
            console.error('Failed to reload assistant after release:', err);
        }
        toast.success(assistant?.requested_release_stage === stage
            ? __('assistants.detail.release_submitted')
            : __('assistants.detail.release_updated'));
    }

    /** Open state of the docked test chat (see `ChatDock.svelte`). The chat is
     *  owned here so the dock's header can offer a reset; it only reads the
     *  assistant once the chatbox is rendered, i.e. after it has loaded.
     *  Open by default, except on small screens where it would cover the
     *  whole page — there it starts as the chat button (as in the builder). */
    let chatOpen = $state(!window.matchMedia(breakpointsQueries.bpMdAndSmaller).matches);
    const testChat = createChatStore(createChatConfig(() => assistant!));

    async function onFeedbackSend(value: string) {
        if (!assistant) return;
        try {
            const feedback = await submitAssistantFeedbacks(value, assistant);
            feedbacks = [...feedbacks, feedback];
            toast.success(__('assistants.detail.feedback_sent'));
        } catch (err) {
            toast.error(ApiError.from(err).userMessage);
        }
    }

    const usageLabel = $derived(
        assistant?.usageCount != null
            ? `${assistant.usageCount.toLocaleString('de-DE')} ${__('assistants.detail.meta_usage_unit')}`
            : '—',
    );
    // Back to the page the assistant was opened from; the store when there is
    // none (a direct URL, a reload).
    const goBack = () => {
        router.goTo(detailReturnPath() ?? router.getPath("assistants.dashboard.store"));
    }

</script>
<Page fade="short">
{#snippet body()}
<div class="detail-shell" class:test-open={chatOpen}>
<div class="detail-scroll">
    {#if loading}
        <p>Loading...</p>
    {:else if error}
        <p>Error: {error.message}</p>
    {:else if assistant}

    {#snippet releaseStages()}
        <DropdownMenuRadioGroup value={assistant?.releaseStage} onValueChange={onReleaseStageChange}>
            {#each releaseOptions as option (option.stage)}
                <DropdownMenuRadioItem
                    value={option.stage}
                    indicator="check"
                    iconLeft={option.icon}
                    iconRight={option.stage === pendingStage ? Clock01Icon : undefined}
                >{option.label}{#if option.stage === pendingStage}<span class="u-sr-only">{__('assistants.detail.release_pending')}</span>{/if}</DropdownMenuRadioItem>
            {/each}
        </DropdownMenuRadioGroup>
    {/snippet}

    <div class="page-content">

        {#if assistant.actionPermissions?.update === true || assistant.actionPermissions?.delete === true}
            <ConfirmDialog
                bind:open={deleteConfirmOpen}
                title={__('assistants.detail.delete_confirm_title', {name: assistant.name})}
                description={__('assistants.detail.delete_confirm_description')}
                onConfirm={onDeleteConfirm}
            />
        {/if}

        <div class="cover">
            <div class="topbar">
                <ButtonWithTooltip
                        variant="ghost"
                        iconLeft={ArrowLeft01Icon}
                        tooltip={__('assistants.detail.back')}
                        class="back"
                        onclick={goBack}
                />
                <div class="controls">
                    <FavButton
                        id="favBtn"
                        variant="stroke"
                        isActive={assistant.isFavorite}
                        onchange={onFavoriteChange}
                    />

                    {#if assistant.allowRemix}
                        <ButtonWithTooltip
                            variant="stroke"
                            size="sm"
                            iconLeft={SplitIcon}
                            tooltip={__('assistants.detail.remix')}
                            onclick={startRemix}
                        ><span class="btn-label">{__('assistants.detail.remix')}</span></ButtonWithTooltip>
                    {/if}

                    {#if assistant.actionPermissions?.update === true || assistant.actionPermissions?.release === true || assistant.actionPermissions?.delete === true}
                        <DropdownMenu align="end" bind:open={menuOpen}>
                            {#snippet trigger({props})}
                                <ButtonWithTooltip
                                    {...props}
                                    variant="stroke"
                                    iconLeft={Settings03Icon}
                                    tooltip={__('assistants.detail.menu_aria')}
                                    highlight={props['data-state']}
                                />
                            {/snippet}
                            {#if assistant.actionPermissions?.update === true}
                                <DropdownMenuItem iconLeft={Edit02Icon} onclick={startEdit}>
                                    {__('assistants.detail.edit')}
                                </DropdownMenuItem>
                            {/if}
                            {#if assistant.actionPermissions?.release === true}
                            {#if breakpoint.is('bpSmallerThanMd')}
                                <!-- In the mobile sheet the row works as a dropdown:
                                     tapping it unfolds the options below it, so the
                                     sheet grows to fit them instead of a panel floating
                                     over it. -->
                                <DropdownMenuItem
                                    iconLeft={SentIcon}
                                    iconRight={publishOpen ? ArrowUp01Icon : ArrowDown01Icon}
                                    closeOnSelect={false}
                                    aria-expanded={publishOpen}
                                    onSelect={() => publishOpen = !publishOpen}
                                >
                                    <span class="menu-label">{__('assistants.detail.publish')}</span>
                                    <span class="menu-value">{releaseValueLabel}</span>
                                </DropdownMenuItem>
                                {#if publishOpen}
                                    <div class="menu-nested" transition:growTransition>
                                        {@render releaseStages()}
                                    </div>
                                {/if}
                            {:else}
                                <DropdownMenuSub
                                    iconLeft={SentIcon}
                                    label={__('assistants.detail.publish')}
                                    value={releaseValueLabel}
                                >
                                    {@render releaseStages()}
                                </DropdownMenuSub>
                            {/if}
                            {/if}
                            {#if assistant.actionPermissions?.delete === true}
                                {#if assistant.actionPermissions?.update === true || assistant.actionPermissions?.release === true}
                                    <DropdownMenuSeparator/>
                                {/if}
                                <DropdownMenuItem variant="destructive" iconLeft={Delete02Icon} onclick={() => deleteConfirmOpen = true}>
                                    {__('assistants.detail.delete')}
                                </DropdownMenuItem>
                            {/if}
                        </DropdownMenu>
                    {/if}
                </div>
            </div>
            <AssistantBanner assistantAvatar={avatar} />
        </div>

        <div class="overview">
            <div class="avatar">
                <AssistantAvatarIcon size="large" assistantAvatar={avatar} />
            </div>

            <div class="head">
                <div class="name-wrapper">
                    <h2 class="name">
                        <OverflowTooltip value={assistant.name} truncate="clamp" lines={1} />
                    </h2>
                    <p class="handle">@{assistant.handle}</p>
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

        <FadeText
            value={assistant.detailDescription}
            lines={DESCRIPTION_COLLAPSED_LINES}
            expandLabel={__('assistants.detail.read_more')}
        />

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

        {#if assistant.actionPermissions?.viewAssistantFeedback && feedbacks.length > 0}
            <hr>
            <ReceivedFeedbackList
                feedback={feedbacks}
            />
        {/if}

        {#if assistant.versions.length > 0}
            <hr>

            <section class="section">
                <h3 class="section-title">{__('assistants.detail.history_title')}</h3>
                <VersionTimeline>
                    {#each [...assistant.versions].reverse() as version, index (version.id)}
                        <VersionCard
                            version={version.version}
                            date={new Date(version.createdAt).toLocaleDateString('de-DE')}
                            changedKeys={version.changedKeys}
                            isCreation={index === assistant.versions.length - 1}
                            note={version.text}
                        />
                    {/each}
                </VersionTimeline>
            </section>
        {/if}

    </div>

{/if}
</div>
{#if assistant}
    <ChatDock bind:open={chatOpen} chat={testChat}
              title={__('assistants.detail.try_out')}>
        <Chatbox assistant={assistant} chat={testChat}/>
    </ChatDock>
{/if}
</div>
{/snippet}
</Page>

<style>
    /* Fixed frame for the page: the content column is the only scroll
       region, the test chat floats over it (docked as a right-hand column
       on wide viewports, see ChatDock). */
    .detail-shell {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        grid-template-rows: minmax(0, 1fr);
        height: 100%;
        overflow: hidden;
        --test-panel-w: 26rem;
        --test-fab-inset: var(--space-4);
    }

    .detail-scroll {
        min-width: 0;
        /* Room to scroll the last content out from under the chat button. */
        padding-bottom: calc(var(--test-fab-inset) + 3rem);
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: none;
    }

    .detail-scroll::-webkit-scrollbar {
        display: none;
    }

    /* Wide viewports: the track opens in step with the dock's morph, so the
       content makes room for the column. */
    @media (--bp-xl) {
        .detail-shell {
            grid-template-columns: minmax(0, 1fr) 0rem;
            transition: grid-template-columns 360ms cubic-bezier(0.3, 0, 0.2, 1) 30ms;
        }

        .detail-shell.test-open {
            grid-template-columns: minmax(0, 1fr) var(--test-panel-w);
            transition: grid-template-columns 480ms cubic-bezier(0.3, 0, 0.2, 1);
        }
    }

    @media (--bp-xl) and (prefers-reduced-motion: reduce) {
        .detail-shell, .detail-shell.test-open {
            transition: none;
        }
    }

    /* Same scroll-away reserve as Page's default scroll region, which this
       page replaces: at-rest content clears the floating nav trigger. */
    @media (--bp-md-and-smaller) {
        .detail-scroll {
            padding-top: calc(var(--space-2_5) + var(--nav-row-h) + var(--space-2));
        }
    }

    .page-content {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        width: 100%;
        margin: 0 auto;
        padding: var(--space-6);
    }

    /* Top bar, overlaid on the cover: back on the left, the assistant's
       actions on the right. */
    .topbar {
        position: absolute;
        top: var(--space-2);
        left: var(--space-2);
        right: var(--space-2);
        z-index: 1;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: var(--space-4);
    }
    /* Cover: the avatar banner (gradient + symbol) as a wide hero. Height is
       lifted from the card default via :global so the shared component still
       drives the look. */
    .cover {
        position: relative;
        width: 100%;
        /* Concentric with the round 2rem top-bar buttons inset by
           --space-2: their 1rem radius + the inset. */
        border-radius: calc(1rem + var(--space-2));
        overflow: hidden;
    }
    .cover :global(.banner-container) {
        height: 13rem;
    }
    .cover :global(.banner-container .symbol) {
        font-size: 5rem;
    }

    /* Overview: avatar tile beside the name/handle and description. */
    .overview {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: var(--space-4);
        align-items: start;
    }
    /* Same corner as the cover above. */
    .overview .avatar :global(.icon-container) {
        border-radius: calc(1rem + var(--space-2));
    }
    .head {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        min-width: 0;
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
        overflow-wrap: anywhere;
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
    /* Over the banner's imagery the back and outline buttons get a solid
       white fill with dark ink in both themes (like the store cards' tags). */
    .topbar :global(.btn:is(.back, .btn--stroke)) {
        --btn-bg: oklch(100% 0 0 / 0.9);
        --btn-color: oklch(20% 0 0);
        border-color: transparent;
    }
    .topbar :global(.btn:is(.back, .btn--stroke):not(:disabled):hover),
    .topbar :global(.btn:is(.back, .btn--stroke).btn--active) {
        --btn-bg: oklch(88% 0 0);
        --btn-color: oklch(20% 0 0);
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
    /* Without badges the empty row would still add a second page gap
       between the avatar row and the metadata cards. */
    .badges:not(:has(*)) {
        display: none;
    }

    /* Metadata cards. */
    .metadata {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
        gap: var(--space-3);
    }
    /* Accent-blue leading icons. The cards themselves stay neutral (ValidationState.UNKNOWN), so
       this is a page-level override rather than a StatusCard variant. */
    .metadata :global(.status-card .icon) {
        color: var(--color-accent-text);
    }
    /* Same corner as the form fields (Input/Textarea: --corner-md). */
    .metadata :global(.status-card) {
        border-radius: var(--corner-md);
    }
    /* Mobile sheet's publish dropdown: current value, muted, pushed to the
       row's end before the chevron; the unfolded options sit indented under
       their row. (Both render inside the menu's sheet, outside .page-content.) */
    .menu-label {
        flex: 1;
    }
    .menu-value {
        color: var(--color-text-muted);
    }
    .menu-nested {
        overflow: hidden;
        padding-inline-start: var(--space-4);
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
       of staying locked to the desktop two-column / inline-controls layout.
       (The token widens the switch from the former raw 40rem to ≤767px — the
       breakpoint grid's "sm and smaller" bucket, which is where the page's
       two-column overview no longer fits.) */
    @media (--bp-sm-and-smaller) {
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
        /* Too narrow for the labelled buttons: "Remixen" and "Ausprobieren"
           become round icon buttons like the others, so the whole top bar
           stays on one line. The labels stay readable for screen readers. */
        /* Specific enough to beat Button's icon-side padding (its :has rule). */
        .topbar .controls :global(.btn.btn--sm) {
            width: 2rem;
            padding: 0;
        }
        .btn-label {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
        }
    }
</style>
