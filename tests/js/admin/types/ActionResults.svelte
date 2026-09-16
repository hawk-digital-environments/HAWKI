<script lang="ts">
    import type { RestApi } from '../../../../resources/js/kernel/api/RestApi.js';
    import AdminResultDialog from '../../../../resources/js/plugins/admin/components/AdminResultDialog.svelte';
    import { useAdminRecordSet } from '../../../../resources/js/plugins/admin/recordSet.svelte.js';
    import {
        McpDiscoverySchema,
        ProviderDiscoverySchema,
        type ProviderDiscovery,
        type McpDiscovery
    } from '../../../../resources/js/plugins/admin/schemas/admin-actions.js';

    let { api, allowed = true }: { api: RestApi; allowed?: boolean } = $props();
    const records = useAdminRecordSet(
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
        type Response = NonNullable<typeof records.results.discover>['response'];
        const response: Response = { models: [] };
        // @ts-expect-error One action's response cannot leak into another dialog.
        response.tools;
        // @ts-expect-error Required response fields cannot silently be omitted.
        const empty: Response = {};
        // @ts-expect-error Unknown action ids are rejected.
        records.closeResult('missing');
        // @ts-expect-error Results retain their response type by id.
        records.results.tools = { response: { models: [] }, id: null, trigger: null };
    }

    function modelCount(response: ProviderDiscovery): number {
        return response.models.length;
    }
    function toolCount(response: McpDiscovery): number {
        return response.tools.length;
    }
</script>

<AdminResultDialog
    recordSet={records}
    action="discover"
>
    {#snippet children(response, id)}
        <p>{id}: {modelCount(response)}</p>
        {#each response.models as model}<p>{model.model_id}: {model.label}</p>{/each}
    {/snippet}
</AdminResultDialog>
<AdminResultDialog
    recordSet={records}
    action="tools"
>
    {#snippet children(response, id)}
        <p>{id}: {toolCount(response)}</p>
        {#each response.tools as tool}<p>{tool}</p>{/each}
    {/snippet}
</AdminResultDialog>
