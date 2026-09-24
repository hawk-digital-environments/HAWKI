<script lang="ts">
    import { untrack } from 'svelte';
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import Link from '$lib/components/util/link/Link.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import PencilEdit01Icon from '$lib/components/ui/icons/iconset/PencilEdit01Icon.svelte';
    import AdminPage from '$plugins/admin/components/AdminPage.svelte';
    import AdminSearch from '$plugins/admin/components/AdminSearch.svelte';
    import AdminTable from '$plugins/admin/components/AdminTable.svelte';
    import StatusPill from '$plugins/assistants/components/status/StatusPill.svelte';
    import { type AdminColumn, useAdminWorkspace } from '$plugins/admin/workspace.svelte.js';
    import {
        AdminAssistantStatusSchema,
        type AdminAssistantResource,
        type AdminAssistantStatus
    } from '$plugins/assistants/admin/schemas/resources/admin-assistant.schema';

    /** Logical color coding for each derived status — matches the tones used everywhere else a StatusPill appears (release stage, risk level, ...). */
    const STATUS_TONE: Record<AdminAssistantStatus, 'info' | 'safe' | 'warning' | 'error'> = {
        waiting_for_review: 'warning',
        published: 'safe',
        private: 'info',
        requires_revision: 'error',
        denied: 'error'
    };

    const app = useApp();
    const { __ } = useTranslator();
    const uid = $props.id();

    const columns: AdminColumn<AdminAssistantResource>[] = [
        { id: 'name' },
        { id: 'handle' },
        { id: 'creator', sortable: false },
        { id: 'status', sortable: false },
        { id: 'version', sortable: false },
        { id: 'based_on', sortable: false },
        { id: 'created_at' },
        { id: 'updated_at' }
    ];

    const recordSet = useAdminWorkspace(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-assistants', { query, signal })
    );

    // Filter facets not tied to a table column's own display format (the
    // status/origin cells need different i18n handling than the filter
    // chips would get from AdminTable's built-in per-column filter, so these
    // are plain selects driving the workspace's value filters directly).
    let status = $state('');
    let origin = $state('');

    $effect(() => {
        const filters = [
            ...(status ? [{ id: 'status', value: status }] : []),
            ...(origin ? [{ id: 'based_on', value: origin }] : [])
        ];
        untrack(() => void recordSet.applyColumnFilters(filters));
    });
</script>

<AdminPage
    workspace="assistants"
    {recordSet}
>
    <div class="toolbar">
        <AdminSearch {recordSet} />
        <div class="filter">
            <span id={`${uid}-status-label`}>{__('admin.fields.status')}</span>
            <SingleSelect
                bind:value={status}
                placeholder={__('admin.table.filter_all')}
                triggerProps={{ 'aria-labelledby': `${uid}-status-label` }}
                items={[
                    { value: '', label: __('admin.table.filter_all') },
                    ...AdminAssistantStatusSchema.options.map((value) => ({
                        value,
                        label: __('admin.values.' + value)
                    }))
                ]}
            />
        </div>
        <div class="filter">
            <span id={`${uid}-origin-label`}>{__('admin.fields.origin')}</span>
            <SingleSelect
                bind:value={origin}
                placeholder={__('admin.table.filter_all')}
                triggerProps={{ 'aria-labelledby': `${uid}-origin-label` }}
                items={[
                    { value: '', label: __('admin.table.filter_all') },
                    { value: 'remix', label: __('admin.values.remix') },
                    { value: 'original', label: __('admin.values.original') }
                ]}
            />
        </div>
    </div>
    <AdminTable
        caption={__('admin.sections.assistants')}
        {recordSet}
        cells={{ name: nameCell, status: statusCell }}
    />
</AdminPage>

{#snippet nameCell(row: AdminAssistantResource)}
    <Link href={{ name: 'admin.assistants.detail', params: { id: row.id } }}>{row.name}</Link>
{/snippet}

{#snippet statusCell(row: AdminAssistantResource)}
    <div class="status-cell">
        <StatusPill label={__('admin.values.' + row.status)} tone={STATUS_TONE[row.status]} />
        {#if row.is_draft}
            <Tooltip tooltip={__('admin.detail.draft_in_progress')}>
                {#snippet children({ props })}
                    <span class="draft-indicator" {...props}>
                        <PencilEdit01Icon size={14} />
                    </span>
                {/snippet}
            </Tooltip>
        {/if}
    </div>
{/snippet}

<style>
    .toolbar {
        display: flex;
        align-items: end;
        flex-wrap: wrap;
        gap: var(--space-3);
        margin-bottom: var(--space-3);
    }
    .status-cell {
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }
    .draft-indicator {
        display: inline-flex;
        color: var(--color-warning);
        cursor: default;
    }
    .filter {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        font-size: var(--font-size-sm);
    }
</style>
