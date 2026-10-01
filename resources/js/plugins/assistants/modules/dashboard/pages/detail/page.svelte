<script lang="ts">

    import type {AssistantAvatar} from "$plugins/assistants/types/assistant/AssistantAvatar";
    import FavButton from "$plugins/assistants/modules/dashboard/components/favButton/FavButton.svelte";
    import Button from "$lib/components/ui/button/Button.svelte";
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
    import LinkSquare01Icon from "$lib/components/ui/icons/iconset/LinkSquare01Icon.svelte";
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
    import type {RouteParams} from '$lib/components/ui/routing/index.js';
    import {useToastContext} from "$lib/components/ui/toast/ToastContext.svelte";
    import {requestBuilderIntent} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte";
    import Settings03Icon from "$lib/components/ui/icons/iconset/Settings03Icon.svelte";
    import WindowsOldIcon from "$lib/components/ui/icons/iconset/WindowsOldIcon.svelte";
    import Chatbox from "$plugins/assistants/components/testChat";
    import {growTransition} from "$lib/utils/transitions/growTransition";
    import OverflowTooltip from "$lib/components/ui/tooltip/OverflowTooltip.svelte";
    import DropdownMenu from "$lib/components/ui/dropdown-menu/DropdownMenu.svelte";
    import DropdownMenuItem from "$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte";
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

    const {__} = useTranslator();
    let assistant = $state<Assistant | undefined>(undefined);
    let loading = $state(true);
    let error = $state<Error | null>(null);
    let feedbacks = $state<AssistantFeedback[]>([]);

    // CHECK AWAIT Syntax from Svelte
    $effect(() => {
        // `params` can be null before the first resolution, and the router
        // types route params loosely (string | string[]) even though `:id`
        // routes always deliver a plain string — hence the array-tolerant
        // extraction (resolves the former `@todo` about `params.id[0]`).
        const rawId = params?.id;
        const id = Array.isArray(rawId) ? rawId[0] : rawId;
        if (!id) return;

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
        goToRoute("assistants.dashboard.store");
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

    /** Toggles the inline test chat (see `assistants.components.testChat`). */
    let chatOpen = $state(false);
    const startTryOut = () => {
        chatOpen = !chatOpen;
    };

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
    const backToStore = () => {
        if(window.history.state && window.history.length>1){
            window.history.back();
        } else {
            // Fallback to store
            goToRoute("assistants.dashboard.store");
        }
    }

</script>
<Page fade="short">
    {#if loading}
        <p>Loading...</p>
    {:else if error}
        <p>Error: {error.message}</p>
    {:else if assistant}

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
                        onclick={backToStore}
                />
                <div class="controls">
                    <FavButton
                        id="favBtn"
                        variant="stroke"
                        isActive={assistant.isFavorite}
                        onchange={onFavoriteChange}
                    />

                    <ButtonWithTooltip
                        variant="stroke"
                        size="md"
                        iconLeft={SplitIcon}
                        tooltip={assistant.allowRemix
                            ? __('assistants.detail.remix')
                            : __('assistants.detail.remix_disabled')}
                        disabled={!assistant.allowRemix}
                        onclick={startRemix}
                    >{__('assistants.detail.remix')}</ButtonWithTooltip>
                    <Button
                        variant="stroke"
                        size="md"
                        iconLeft={LinkSquare01Icon}
                        highlight={chatOpen}
                        onclick={startTryOut}
                    >{__('assistants.detail.try_out')}</Button>

                    {#if assistant.actionPermissions?.update === true || assistant.actionPermissions?.release === true || assistant.actionPermissions?.delete === true}
                        <DropdownMenu align="end">
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
                                <DropdownMenuSub
                                    iconLeft={SentIcon}
                                    label={__('assistants.detail.publish')}
                                    value={releaseValueLabel}
                                >
                                    <DropdownMenuRadioGroup value={assistant.releaseStage} onValueChange={onReleaseStageChange}>
                                        {#each releaseOptions as option (option.stage)}
                                            <DropdownMenuRadioItem
                                                value={option.stage}
                                                indicator="check"
                                                iconLeft={option.icon}
                                                iconRight={option.stage === pendingStage ? Clock01Icon : undefined}
                                            >{option.label}</DropdownMenuRadioItem>
                                        {/each}
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuSub>
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

        {#if chatOpen}
            <div class="test-chat" transition:growTransition>
                <Chatbox assistant={assistant}/>
            </div>
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
</Page>

<style>
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
        /* Concentric with the round 2.5rem top-bar buttons inset by
           --space-2: their 1.25rem radius + the inset. */
        border-radius: calc(1.25rem + var(--space-2));
        overflow: hidden;
        border: var(--border);
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
    /* Same corner as the store cards' avatar, scaled to this size: the
       small (3rem) avatar's --corner-sm is a quarter of its edge. */
    .overview .avatar :global(.icon-container) {
        border-radius: calc(7rem / 4);
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
    /* Icon-only buttons (back, favourite, menu) match the md buttons' height. */
    .topbar :global(.btn--iconOnly) {
        width: 2.5rem;
        height: 2.5rem;
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
    /* Accent-blue leading icons — same label colour as the filled "Ausprobieren"
       button. The cards themselves stay neutral (ValidationState.UNKNOWN), so
       this is a page-level override rather than a StatusCard variant. */
    .metadata :global(.status-card .icon) {
        color: var(--color-accent-text);
    }
    /* Same corner as the form fields (Input/Textarea: --corner-md). */
    .metadata :global(.status-card) {
        border-radius: var(--corner-md);
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

    /* Inline test chat: Chatbox fills its container (100% height), and the
       page is flow layout, so it needs a definite height both for layout
       and for growTransition's scrollHeight measurement. */
    .test-chat {
        height: 30rem;
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
        /* Too narrow for back and all actions in one line: the actions wrap
           under the back button. */
        .topbar {
            flex-wrap: wrap;
        }
        .controls {
            flex-wrap: wrap;
        }
    }
</style>
