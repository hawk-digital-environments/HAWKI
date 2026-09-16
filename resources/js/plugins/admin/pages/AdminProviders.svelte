<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import ProviderIcon from '$plugins/core/components/ProviderIcon.svelte';
    import AdminModelDiscovery from '../components/AdminModelDiscovery.svelte';
    import AdminPage from '../components/AdminPage.svelte';
    import AdminResultDialog from '../components/AdminResultDialog.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useRouter } from '$lib/components/ui/routing/index.js';
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import type { AdminProviderResource } from '../schemas/resources/admin-providers.schema.js';
    import { type AdminColumn, useAdminRecordSet } from '../recordSet.svelte.js';
    import { QueuedActionSchema, ProviderDiscoverySchema } from '../schemas/admin-actions.js';
    const app = useApp();
    const { __ } = useTranslator();
    const router = useRouter();
    const columns: AdminColumn<AdminProviderResource>[] = [
        { id: 'name' },
        { id: 'provider_id' },
        { id: 'adapter_key' },
        { id: 'active', format: 'boolean' },
        { id: 'api_key_set', sortable: false }
    ];
    const records = useAdminRecordSet(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-providers', { query, signal }),
        {
            rowActions: (row) => ({
                test: {
                    run: () =>
                        app.restApi.postToResourceAction(
                            'admin-providers',
                            `${encodeURIComponent(row.id)}/actions/test`,
                            {},
                            { schema: ProviderDiscoverySchema }
                        )
                },
                discover: {
                    dialog: true,
                    run: () =>
                        app.restApi.postToResourceAction(
                            'admin-providers',
                            `${encodeURIComponent(row.id)}/actions/discover`,
                            {},
                            { schema: ProviderDiscoverySchema }
                        )
                }
            }),
            save: (values, row) => {
                return row ?
                        app.restApi.updateResource('admin-providers', row.id, values, {
                            headers: { 'If-Match': `"${row._version}"` }
                        })
                    :   app.restApi.createResource('admin-providers', values);
            },
            remove: (row) =>
                app.restApi.deleteResource('admin-providers', row.id, {
                    headers: { 'If-Match': `"${row._version}"` }
                }),
            refresh: async () => {
                for (const name of ['ai-models', 'ai-tools'] as const) {
                    if (app.stores.has(name)) await app.stores.get(name).loadData?.(app);
                }
            }
        }
    );
</script>

<AdminPage
    workspace="providers"
    recordSet={records}
    pageActions={app.can('models.manage') && app.can('mcp.manage') ?
        [
            {
                id: 'import',
                confirm: true,
                run: () =>
                    app.restApi.postToResourceAction(
                        'admin-providers',
                        `actions/import`,
                        {},
                        { schema: QueuedActionSchema }
                    )
            }
        ]
    :   []}
>
    <AdminSearch recordSet={records} />
    <AdminTable
        caption={__('admin.sections.providers')}
        recordSet={records}
        cells={{ name: providerName }}
        rowMenuItems={(row) =>
            app.can('models.manage') ?
                [
                    {
                        label: __('admin.view_models'),
                        icon: Search01Icon,
                        run: () =>
                            router.goTo(`${router.getPath('admin.models')}?provider_id=${encodeURIComponent(row.id)}`)
                    }
                ]
            :   []}
    />
    <AdminResultDialog
        recordSet={records}
        action="discover"
    >
        {#snippet children(response, providerId)}
            {#if providerId}<AdminModelDiscovery
                    models={response.models}
                    {providerId}
                />{/if}
        {/snippet}
    </AdminResultDialog>
</AdminPage>

{#snippet providerName(row: AdminProviderResource)}
    <span class="provider-name">
        <ProviderIcon
            name={row.name}
            light={row.icon_url}
            dark={row.icon_url_dark}
            size={24}
        />
        <span>{row.name}</span>
    </span>
{/snippet}

<style>
    .provider-name {
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }
</style>
