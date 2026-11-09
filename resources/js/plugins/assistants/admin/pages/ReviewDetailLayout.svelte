<!--
  @component Shared layout of the review detail's step flow (`/admin/assistants
  /:id/changes|review`): owns the review fetch (one request for both steps,
  kept while navigating between them), the page shell with header and status
  alert, and the pinned step footer. The step pages render inside it and read
  `useReviewDetailContext()`.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {RouteProps} from '$lib/components/ui/routing/index.js';
    const {params, children}: RouteProps<{assistantId: string | string[]}> & {children?: Snippet} = $props();
    import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte';
    import Page from '$lib/components/ui/page/Page.svelte';
    import Alert from '$lib/components/ui/alert/Alert.svelte';
    import SquareLock02Icon from '$lib/components/ui/icons/iconset/SquareLock02Icon.svelte';
    import StatusPill from '$plugins/assistants/components/status/StatusPill.svelte';
    import ReviewStepFooter from '$plugins/assistants/admin/components/ReviewStepFooter.svelte';
    import {createReviewDetailContext, currentReview} from '$plugins/assistants/admin/contexts/reviewDetailContext.svelte.js';
    import {DRAFT_VERSION} from '$plugins/assistants/admin/api/schemas.js';
    import {reviewPillTone} from '$plugins/assistants/components/status/reviewTones.js';

    const {__} = useTranslator();
    const toast = useToastContext();
    const router = useRouter();
    const review = createReviewDetailContext(toast, __, router);

    const target = $derived(review.target.value);
    const targetReview = $derived(target?.assistant_review ?? null);
    /** The submission's note lives on the working draft (version 0.0). */
    const draft = $derived(
        (target?.assistant_versions ?? []).find(version => String(version.version) === DRAFT_VERSION) ?? null
    );

    $effect(() => {
        const rawId = params?.assistantId;
        const id = Array.isArray(rawId) ? rawId[0] : rawId;
        if (!id) return;

        currentReview.assistantId = id;
        const controller = new AbortController();
        void review.load(id, controller.signal);

        return () => controller.abort();
    });
</script>

<Page title={target?.name ?? __('assistants.admin.detail.title')}>
    <div class="page-content">
        {#if review.loading}
            <p role="status">{__('ui.loading')}</p>
        {:else if review.forbidden}
            <Alert
                tone="error"
                icon={SquareLock02Icon}
                title={__('assistants.admin.detail.no_access')}
            />
        {:else if review.error}
            <Alert tone="error" title={review.error} />
        {:else if target}
            <header class="meta-header">
                <div class="status-row">
                    {#if targetReview}
                        <StatusPill tone={reviewPillTone(targetReview.status)} label={__(`assistants.admin.queue.status.${targetReview.status}`)}/>
                    {/if}
                    <p class="meta">
                        {target.creator?.display_name ?? '—'}
                        · {__(`assistants.release_stage.${target.release_stage ?? 'private'}`)}
                        {#if target.requested_release_stage}
                            → {__(`assistants.release_stage.${target.requested_release_stage}`)}
                        {/if}
                    </p>
                </div>
                {#if draft?.text}
                    <div class="submission-note">
                        <p class="u-label">{__('assistants.admin.detail.note_label')}</p>
                        <p class="note">“{draft.text}”</p>
                    </div>
                {/if}
            </header>

            {@render children?.()}
        {/if}
    </div>
</Page>

<ReviewStepFooter/>

<style>
    .page-content {
        display: flex;
        flex-direction: column;
        gap: var(--space-6);
        max-width: 64rem;
        margin: 0 auto;
        padding: var(--space-8);
    }

    /* The review's one structured header: the current state (status pill,
       creator, stage) above the creator's submission note. */
    .meta-header {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .status-row {
        display: flex;
        align-items: center;
        gap: var(--space-4);
        flex-wrap: wrap;
    }

    .meta {
        color: var(--color-text-muted, inherit);
        font-size: var(--font-size-sm);
        margin: 0;
    }

    .submission-note {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    .note {
        margin: 0;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        color: var(--color-text);
    }
</style>
