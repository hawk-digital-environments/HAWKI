<!--
  @component The assistant review queue: reviews by status, newest first, from
  `GET /assistant-reviews?filter[status]=…&include=assistant`. Gated by the
  section's permission probe (`null` = probe pending → loading state).
-->
<script lang="ts">
    import {untrack} from 'svelte';
    import type {RouteProps} from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import Page from '$lib/components/ui/page/Page.svelte';
    import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import Alert from '$lib/components/ui/alert/Alert.svelte';
    import Tabs from '$lib/components/ui/tabs/Tabs.svelte';
    import DataTable from '$lib/components/ui/data-table/DataTable.svelte';
    import type {DataTableColumn, PaginationState} from '$lib/components/ui/data-table/types.js';
    import SquareLock02Icon from '$lib/components/ui/icons/iconset/SquareLock02Icon.svelte';
    import StatusPill from '$plugins/assistants/components/status/StatusPill.svelte';
    import {ApiError} from '$plugins/assistants/api/errors.js';
    import {listReviewQueue} from '$plugins/assistants/admin/api/reviewClient.js';
    import type {ReleaseStage, ReviewStatus} from '$plugins/assistants/admin/api/schemas.js';
    import {mayReviewAssistants} from '$plugins/assistants/admin/reviewPermission.svelte.js';
    import {reviewPillTone} from '$plugins/assistants/components/status/reviewTones.js';
    import {formatDate} from '$lib/utils/date.js';

    const router = useRouter();
    const {__} = useTranslator();

    const STATUS_KEYS = ['pending', 'needs_revision', 'denied', 'approved'] as const satisfies readonly ReviewStatus[];

    /** The "All" pseudo-status: no status filter, every accessible review. */
    type QueueFilter = ReviewStatus | 'all';

    interface QueueRow {
        id: string;
        reviewId: string;
        assistantId: string;
        name: string;
        handle: string;
        stage: ReleaseStage | null;
        submittedAt: string | null;
        status: ReviewStatus;
    }

    let status = $state<QueueFilter>('all');
    let rows = $state<QueueRow[]>([]);
    let total = $state(0);
    let loading = $state(true);
    let error = $state<string | null>(null);
    let pagination = $state<PaginationState>({pageIndex: 0, pageSize: 25});

    // The admin surface is gated by the permissions probe; while it is
    // unanswered (null) the loading state shows instead of a no-access flash.
    const mayReview = $derived(mayReviewAssistants());

    const columns = $derived<DataTableColumn<QueueRow>[]>([
        {id: 'name', accessorFn: row => row.name, header: __('assistants.admin.queue.columns.name')},
        {id: 'handle', accessorFn: row => row.handle, header: __('assistants.admin.queue.columns.handle')},
        {id: 'stage', accessorFn: row => row.stage, header: __('assistants.admin.queue.columns.stage')},
        {id: 'submittedAt', accessorFn: row => row.submittedAt, header: __('assistants.admin.queue.columns.submitted')},
        {id: 'status', accessorFn: row => row.status, header: __('assistants.admin.queue.columns.status')}
    ]);

    const tabs = $derived([
        {key: 'all', label: __('assistants.admin.queue.tab.all')},
        ...STATUS_KEYS.map(key => ({
            key,
            label: __(`assistants.admin.queue.tab.${key}`)
        }))
    ]);

    async function load(signal?: AbortSignal): Promise<void> {
        loading = true;
        error = null;
        try {
            const page = await listReviewQueue('all' === status ? null : status, {number: pagination.pageIndex + 1, size: pagination.pageSize}, signal);
            rows = page.rows.map(row => ({
                id: row.reviewId,
                reviewId: row.reviewId,
                assistantId: row.assistantId,
                name: row.name,
                handle: row.handle ?? '—',
                stage: row.requestedStage ?? row.stage,
                submittedAt: row.submittedAt,
                status: row.status
            }));
            total = page.total;
        } catch (err) {
            if (err instanceof DOMException && err.name === 'AbortError') return;
            error = ApiError.from(err).userMessage;
        } finally {
            loading = false;
        }
    }

    // Reload whenever the tab or the page moves.
    $effect(() => {
        if (mayReview !== true) return;
        void status;
        void pagination.pageIndex;
        void pagination.pageSize;
        const controller = new AbortController();
        untrack(() => void load(controller.signal));
        return () => controller.abort();
    });

    function selectStatus(key: string): void {
        pagination = {...pagination, pageIndex: 0};
        status = key as QueueFilter;
    }
</script>

<Page title={__('assistants.admin.queue.title')}>
    <div class="page-content">
        <p class="page-description">{__('assistants.admin.queue.description')}</p>

        {#if mayReview === false}
            <Alert
                tone="error"
                icon={SquareLock02Icon}
                title={__('assistants.admin.queue.no_access')}
            />
        {:else if error}
            <Alert tone="error" title={error} />
        {:else}
            <Tabs
                items={tabs}
                value={status}
                onChange={selectStatus}
                aria-label={__('assistants.admin.queue.tab.label')}
                mode="radio"
                disabled={loading}
            />

            <DataTable
                data={rows}
                {columns}
                caption={__('assistants.admin.queue.title')}
                server={true}
                {total}
                bind:pagination
                {loading}
                empty={__('assistants.admin.queue.empty')}
                cells={{status: statusCell, name: nameCell, stage: stageCell, submittedAt: submittedCell}}
            />
        {/if}
    </div>
</Page>

{#snippet statusCell(row: QueueRow)}
    <StatusPill tone={reviewPillTone(row.status)} label={__(`assistants.admin.queue.status.${row.status}`)}/>
{/snippet}

{#snippet nameCell(row: QueueRow)}
    <button type="button" class="name" onclick={() => void router.goToRoute('admin.assistants.changes', {assistantId: row.assistantId})}>
        {row.name}
    </button>
{/snippet}

{#snippet stageCell(row: QueueRow)}
    {#if row.stage}{__(`assistants.release_stage.${row.stage}`)}{:else}—{/if}
{/snippet}

{#snippet submittedCell(row: QueueRow)}
    {#if row.submittedAt}<time datetime={row.submittedAt}>{formatDate(row.submittedAt)}</time>{:else}—{/if}
{/snippet}

<style>
    .page-content {
        display: flex;
        flex-direction: column;
        gap: var(--space-6);
        max-width: 64rem;
        margin: 0 auto;
        padding: var(--space-8);
    }

    .page-description {
        margin: 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }

    .name {
        font: inherit;
        background: none;
        border: 0;
        padding: 0;
        cursor: pointer;
        text-decoration: underline;
        text-underline-offset: 2px;
    }
</style>
