<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import AdminWorkspace from '../components/AdminWorkspace.svelte';
    import AdminActionMenu from '../components/AdminActionMenu.svelte';
    import { adminActionIcons } from '../actionIcons.js';
    const app = useApp();
    const { __ } = useTranslator();
</script>

<AdminWorkspace
    section="health"
    searchable={false}
    pollInterval={30000}
    pageActions={app.can('health.manage') ? [{ id: 'check-ai-status' }] : []}
    menuItems={({ content, action }) =>
        app.can('health.manage') && content?.failed_jobs?.length ?
            [
                {
                    label: __('admin.actions.flush-jobs'),
                    icon: adminActionIcons['flush-jobs'],
                    destructive: true,
                    run: (target) => action({ id: 'flush-jobs', confirm: true }, undefined, target)
                }
            ]
        :   []}
>
    {#snippet afterTable({ content, busy, dialogOpen, action })}
        {#if content}
            <section class="health-details">
                <h2>{__('admin.queues')}</h2>
                <dl>
                    {#each Object.entries(content.queues ?? {}) as [name, value]}<div>
                            <dt>{name}</dt>
                            <dd>{value ?? __('admin.values.unknown')}</dd>
                        </div>{/each}
                </dl>
            </section>
            <section class="health-details">
                <h2>{__('admin.failed_jobs')}</h2>
                {#if content.failed_jobs?.length}<ul>
                        {#each content.failed_jobs as job}<li>
                                <span>{job.queue} · {job.failed_at}</span>{#if app.can('health.manage')}<AdminActionMenu
                                        compact
                                        label={__('admin.row_actions', { name: job.queue + ' · ' + job.failed_at })}
                                        disabled={busy}
                                        {dialogOpen}
                                        items={[
                                            {
                                                label: __('admin.actions.retry-job'),
                                                icon: adminActionIcons['retry-job'],
                                                run: (target) =>
                                                    action({ id: 'retry-job', confirm: true }, job.uuid, target)
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
                    {#each Object.entries(content.versions ?? {}) as [key, value]}<div>
                            <dt>{__('admin.fields.' + key)}</dt>
                            <dd>{typeof value === 'boolean' ? __(value ? 'admin.yes' : 'admin.no') : value}</dd>
                        </div>{/each}
                </dl>
            </section>
        {/if}
    {/snippet}
</AdminWorkspace>

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
