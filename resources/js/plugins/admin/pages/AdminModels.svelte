<script lang="ts">
    import { untrack } from 'svelte';
    import { type RouteProps, useQueryState } from '$lib/components/ui/routing/index.js';
    import type { ColumnFiltersState } from '$lib/components/ui/data-table/types.js';
    const {}: RouteProps = $props();
    import AdminModelCapabilities from '../components/AdminModelCapabilities.svelte';
    import AdminPage from '../components/AdminPage.svelte';
    import AdminRowSwitch from '../components/AdminRowSwitch.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { isModelVisible, toggleModelVisible } from '../capabilities.js';
    import { roleLabel } from '../forms/authorization.js';
    import type { AdminModelResource } from '../schemas/resources/admin-models.schema.js';
    import { type AdminColumn, useAdminRecordSet } from '../recordSet.svelte.js';

    import { ModelRefreshSchema, ModelStatusCheckSchema } from '../schemas/admin-actions.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn<AdminModelResource, 'visible'>[] = [
        { id: 'label' },
        { id: 'model_id' },
        { id: 'provider_id', filter: true },
        { id: 'allowed_roles', sortable: false },
        { id: 'active', format: 'boolean' },
        { id: 'status', format: 'enum' },
        { id: 'visible', sortable: false },
        { id: 'flags', sortable: false }
    ];
    // Share provider filters through links and keep them after reloads.
    const providerId = useQueryState('provider_id');
    const providerFilter = $derived<ColumnFiltersState>(
        providerId.current ? [{ id: 'provider_id', value: providerId.current }] : []
    );
    const records = useAdminRecordSet(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-models', { query, signal }),
        {
            rowActions: (row) => ({
                refresh: {
                    confirm: true,
                    run: () =>
                        app.restApi.postToResourceAction(
                            'admin-models',
                            `${encodeURIComponent(row.id)}/actions/refresh`,
                            {},
                            { schema: ModelRefreshSchema }
                        )
                }
            }),
            columnFilters: untrack(() => providerFilter),
            save: (values, row) => {
                return row ?
                        app.restApi.updateResource('admin-models', row.id, values, {
                            headers: { 'If-Match': `"${row._version}"` }
                        })
                    :   app.restApi.createResource('admin-models', values);
            },
            remove: (row) =>
                app.restApi.deleteResource('admin-models', row.id, {
                    headers: { 'If-Match': `"${row._version}"` }
                }),
            refresh: async () => {
                for (const name of ['ai-models', 'ai-tools'] as const) {
                    if (app.stores.has(name)) await app.stores.get(name).loadData?.(app);
                }
            }
        }
    );
    // Links and browser navigation update the filter without remounting the page.
    $effect(() => {
        const filters = providerFilter;
        untrack(() => void records.applyColumnFilters(filters));
    });
    // Table selections update the URL. Assignments do not subscribe this effect to URL changes.
    $effect(() => {
        const value = records.columnFilters.find((item) => item.id === 'provider_id')?.value;
        providerId.current = typeof value === 'string' && value ? value : null;
    });
</script>

{#snippet active(row: AdminModelResource)}
    <AdminRowSwitch
        checked={row.active}
        label={__('admin.fields.active')}
        disabled={records.locked(row)}
        onToggle={(enabled) => records.update(row, { active: enabled })}
    />
{/snippet}

{#snippet visible(row: AdminModelResource)}
    <AdminRowSwitch
        checked={isModelVisible(row)}
        label={__('admin.fields.visible')}
        disabled={records.locked(row)}
        onToggle={(enabled) => records.update(row, toggleModelVisible(row, enabled))}
    />
{/snippet}

{#snippet allowed_roles(row: AdminModelResource)}
    {row.allowed_roles.length ? row.allowed_roles.map((id) => roleLabel(id, records.content?.role_catalog ?? [], records.fields, __)).join(', ') : __('admin.allowed_roles_everyone')}
{/snippet}

{#snippet flags(row: AdminModelResource)}
    <AdminModelCapabilities
        {row}
        disabled={records.locked(row)}
        onChange={(changes) => records.update(row, changes)}
    />
{/snippet}

<AdminPage
    workspace="models"
    recordSet={records}
    pageActions={app.can('models.manage') ?
        [
            {
                id: 'check-status',
                run: () =>
                    app.restApi.postToResourceAction(
                        'admin-models',
                        `actions/check-status`,
                        {},
                        { schema: ModelStatusCheckSchema }
                    )
            }
        ]
    :   []}
>
    <AdminSearch recordSet={records} />
    <AdminTable
        caption={__('admin.sections.models')}
        recordSet={records}
        cells={{ active, visible, flags, allowed_roles }}
    />
</AdminPage>
