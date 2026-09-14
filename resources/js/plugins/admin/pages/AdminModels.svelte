<script
    module
    lang="ts"
>
    import { configurePage } from '$lib/components/ui/routing/index.js';

    export const config = configurePage({
        loadData: async ({ path }) => ({
            providerId: new URL(path, window.location.origin).searchParams.get('provider_id')
        })
    });
</script>

<script lang="ts">
    import { untrack } from 'svelte';
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    import type { ColumnFiltersState } from '$lib/components/ui/data-table/types.js';
    const { data: route }: RouteProps<typeof config> = $props();
    import AdminModelCapabilities from '../components/AdminModelCapabilities.svelte';
    import AdminPage from '../components/AdminPage.svelte';
    import AdminRowSwitch from '../components/AdminRowSwitch.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { isModelVisible, toggleModelVisible } from '../capabilities.js';
    import { type AdminRow } from '../schemas/admin-content.js';
    import { useAdminWorkspace, type AdminColumn } from '../workspace.svelte.js';

    import { ModelRefreshSchema } from '../schemas/admin-actions.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn[] = [
        { id: 'label' },
        { id: 'model_id' },
        { id: 'provider_id', filter: true },
        { id: 'active', format: 'boolean' },
        { id: 'visible', sortable: false },
        { id: 'capabilities', sortable: false }
    ];
    const providerFilter = $derived<ColumnFiltersState>(
        route.providerId ? [{ id: 'provider_id', value: route.providerId }] : []
    );
    const workspace = useAdminWorkspace(
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
    // Following a "View models" link while already on this page swaps the filter in place; the
    // state ignores the call when the filter already applies, so the initial read runs once.
    $effect(() => {
        const filters = providerFilter;
        untrack(() => void workspace.applyColumnFilters(filters));
    });
</script>

{#snippet active(row: AdminRow)}
    <AdminRowSwitch
        checked={row.active === true}
        label={__('admin.fields.active')}
        disabled={workspace.locked(row)}
        onToggle={(enabled) => workspace.update(row, { active: enabled })}
    />
{/snippet}

{#snippet visible(row: AdminRow)}
    <AdminRowSwitch
        checked={isModelVisible(row)}
        label={__('admin.fields.visible')}
        disabled={workspace.locked(row)}
        onToggle={(enabled) => workspace.update(row, toggleModelVisible(row, enabled))}
    />
{/snippet}

{#snippet capabilities(row: AdminRow)}
    <AdminModelCapabilities
        {row}
        disabled={workspace.locked(row)}
        onChange={(changes) => workspace.update(row, changes)}
    />
{/snippet}

<AdminPage
    section="models"
    {workspace}
>
    <AdminSearch {workspace} />
    <AdminTable
        caption={__('admin.sections.models')}
        {workspace}
        cells={{ active, visible, capabilities }}
    />
</AdminPage>
