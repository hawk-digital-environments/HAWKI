<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import AdminPage from '../components/AdminPage.svelte';
    import AdminResultDialog from '../components/AdminResultDialog.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useAdminWorkspace, type AdminColumn } from '../workspace.svelte.js';
    import { McpTestSchema, McpDiscoverySchema } from '../schemas/admin-actions.js';
    const app = useApp();
    const { __ } = useTranslator();
    const columns: AdminColumn[] = [
        { id: 'server_label' },
        { id: 'type', sortKey: 'kind' },
        { id: 'url' },
        { id: 'status', format: 'enum' },
        { id: 'api_key_set', sortable: false }
    ];
    const workspace = useAdminWorkspace(
        columns,
        (signal, query) => app.restApi.getResourceCollection('admin-mcp', { query, signal }),
        {
            rowActions: (row) => ({
                test: {
                    run: () =>
                        app.restApi.postToResourceAction(
                            'admin-mcp',
                            `${encodeURIComponent(row.id)}/actions/test`,
                            {},
                            { schema: McpTestSchema }
                        )
                },
                discover: {
                    confirm: true,
                    dialog: true,
                    run: () =>
                        app.restApi.postToResourceAction(
                            'admin-mcp',
                            `${encodeURIComponent(row.id)}/actions/discover`,
                            {},
                            { schema: McpDiscoverySchema }
                        )
                }
            }),
            save: (values, row) => {
                const { type, ...attributes } = values;
                if (type !== undefined) attributes.kind = type;
                return row ?
                        app.restApi.updateResource('admin-mcp', row.id, attributes, {
                            headers: { 'If-Match': `"${row._version}"` }
                        })
                    :   app.restApi.createResource('admin-mcp', attributes);
            },
            remove: (row) =>
                app.restApi.deleteResource('admin-mcp', row.id, {
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
    section="mcp"
    {workspace}
>
    <AdminSearch {workspace} />
    <AdminTable
        caption={__('admin.sections.mcp')}
        {workspace}
    />
    <AdminResultDialog
        {workspace}
        action="discover"
    >
        {#snippet children(response)}
            <ul>
                {#each response.tools as tool}
                    <li>{tool}</li>
                {/each}
            </ul>
        {/snippet}
    </AdminResultDialog>
</AdminPage>
