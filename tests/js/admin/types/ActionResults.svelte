<script lang="ts">
    import type { RestApi } from '../../../../resources/js/kernel/api/RestApi.js';
    import AdminResultDialog from '../../../../resources/js/plugins/admin/components/AdminResultDialog.svelte';
    import { useAdminWorkspace } from '../../../../resources/js/plugins/admin/workspace.svelte.js';
    import {
        McpDiscoverySchema,
        ProviderDiscoverySchema,
        type ProviderDiscovery,
        type McpDiscovery
    } from '../../../../resources/js/plugins/admin/schemas/admin-actions.js';

    let { api, allowed = true }: { api: RestApi; allowed?: boolean } = $props();
    const workspace = useAdminWorkspace(
        [{ id: 'name' }],
        (signal, query) => api.getResourceCollection('admin-providers', { signal, query }),
        {
            rowActions: (row) => ({
                discover: {
                    dialog: true,
                    run: () =>
                        api.postToResourceAction(
                            'admin-providers',
                            `${row.id}/actions/discover`,
                            {},
                            { schema: ProviderDiscoverySchema }
                        )
                },
                tools:
                    allowed ?
                        {
                            dialog: true,
                            run: () =>
                                api.postToResourceAction(
                                    'admin-mcp',
                                    `${row.id}/actions/discover`,
                                    {},
                                    { schema: McpDiscoverySchema }
                                )
                        }
                    :   undefined
            })
        }
    );

    function checkActions() {
        type Response = NonNullable<typeof workspace.results.discover>['response'];
        const response: Response = { models: [] };
        // @ts-expect-error One action's response cannot leak into another dialog.
        response.tools;
        // @ts-expect-error Required response fields cannot silently be omitted.
        const empty: Response = {};
        // @ts-expect-error Unknown action ids are rejected.
        workspace.closeResult('missing');
        // @ts-expect-error Results retain their response type by id.
        workspace.results.tools = { response: { models: [] }, id: null, trigger: null };
    }

    function modelCount(response: ProviderDiscovery): number {
        return response.models.length;
    }
    function toolCount(response: McpDiscovery): number {
        return response.tools.length;
    }
</script>

<AdminResultDialog
    {workspace}
    action="discover"
>
    {#snippet children(response, id)}
        <p>{id}: {modelCount(response)}</p>
        {#each response.models as model}<p>{model.model_id}: {model.label}</p>{/each}
    {/snippet}
</AdminResultDialog>
<AdminResultDialog
    {workspace}
    action="tools"
>
    {#snippet children(response, id)}
        <p>{id}: {toolCount(response)}</p>
        {#each response.tools as tool}<p>{tool}</p>{/each}
    {/snippet}
</AdminResultDialog>
