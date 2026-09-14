<script lang="ts">
    import { onMount } from 'svelte';
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import AdminPage from '../components/AdminPage.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import AdminActionMenu from '../components/AdminActionMenu.svelte';
    import type { AdminMenuItem } from '../components/AdminActionMenu.svelte';
    import { adminActionIcons } from '../actionIcons.js';
    import { useAdminWorkspace, type AdminColumn } from '../workspace.svelte.js';
    import { QueuedActionSchema } from '../schemas/admin-actions.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn[] = [
        { id: 'name' },
        { id: 'status', format: 'enum' },
        { id: 'message' },
        { id: 'response_time' }
    ];
    const workspace = useAdminWorkspace(columns, (signal, query) =>
        app.restApi.getResourceCollection('admin-health', { query, signal })
    );
    const failedJobs = $derived(workspace.content?.failed_jobs ?? []);
    const menuItems = $derived<AdminMenuItem[]>(
        app.can('health.manage') && failedJobs.length ?
            [
                {
                    label: __('admin.actions.flush-jobs'),
                    icon: adminActionIcons['flush-jobs'],
                    destructive: true,
                    run: (target) =>
                        workspace.action(
                            {
                                id: 'flush-jobs',
                                confirm: true,
                                run: () =>
                                    app.restApi.postToResourceAction(
                                        'admin-health',
                                        `actions/flush-jobs`,
                                        {},
                                        { schema: QueuedActionSchema }
                                    )
                            },
                            target
                        )
                }
            ]
        :   []
    );
    // Re-reads every 30 s while the tab is visible and nothing is in flight.
    onMount(() => {
        const timer = window.setInterval(() => {
            if (!document.hidden && !workspace.loading && !workspace.busy && !workspace.updating.length)
                void workspace.load();
        }, 30000);
        return () => window.clearInterval(timer);
    });
</script>

<AdminPage
    section="health"
    {workspace}
    pageActions={app.can('health.manage') ?
        [
            {
                id: 'check-ai-status',
                run: () =>
                    app.restApi.postToResourceAction(
                        'admin-health',
                        `actions/check-ai-status`,
                        {},
                        { schema: QueuedActionSchema }
                    )
            }
        ]
    :   []}
    {menuItems}
>
    <AdminTable
        caption={__('admin.sections.health')}
        {workspace}
    />
    {#if workspace.content}
        <section class="health-details">
            <h2>{__('admin.queues')}</h2>
            <dl>
                {#each Object.entries(workspace.content?.queues ?? {}) as [name, value]}<div>
                        <dt>{name}</dt>
                        <dd>{value ?? __('admin.values.unknown')}</dd>
                    </div>{/each}
            </dl>
        </section>
        <section class="health-details">
            <h2>{__('admin.failed_jobs')}</h2>
            {#if failedJobs.length}<ul>
                    {#each failedJobs as job}<li>
                            <span>{job.queue} · {job.failed_at}</span>{#if app.can('health.manage')}<AdminActionMenu
                                    compact
                                    label={__('admin.row_actions', { name: job.queue + ' · ' + job.failed_at })}
                                    disabled={workspace.busy}
                                    dialogOpen={workspace.dialogOpen}
                                    items={[
                                        {
                                            label: __('admin.actions.retry-job'),
                                            icon: adminActionIcons['retry-job'],
                                            run: (target) =>
                                                workspace.action(
                                                    {
                                                        id: 'retry-job',
                                                        confirm: true,
                                                        run: () =>
                                                            app.restApi.postToResourceAction(
                                                                'admin-health',
                                                                `${encodeURIComponent(job.uuid)}/actions/retry-job`,
                                                                {},
                                                                { schema: QueuedActionSchema }
                                                            )
                                                    },
                                                    target
                                                )
                                        }
                                    ]}
                                />{/if}
                        </li>{/each}
                </ul>
            {:else}<p>{__('admin.no_failed_jobs')}</p>{/if}
        </section>
        <section class="health-details">
            <h2>{__('admin.environment')}</h2>
            <dl>
                {#each Object.entries(workspace.content?.versions ?? {}) as [key, value]}<div>
                        <dt>{__('admin.fields.' + key)}</dt>
                        <dd>{typeof value === 'boolean' ? __(value ? 'admin.yes' : 'admin.no') : value}</dd>
                    </div>{/each}
            </dl>
        </section>
    {/if}
</AdminPage>

<style>
    dt {
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
    }
    dd {
        margin: var(--space-1) 0 0;
        font-variant-numeric: tabular-nums;
    }
    .health-details {
        margin-top: var(--space-7);
    }
    .health-details h2 {
        font-size: var(--font-size-lg);
        margin-bottom: var(--space-3);
    }
    .health-details dl {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-5);
    }
    .health-details li {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        margin-bottom: var(--space-2);
    }
</style>
