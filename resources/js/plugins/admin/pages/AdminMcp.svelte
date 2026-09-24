<script lang="ts">
    import { tick, untrack } from 'svelte';
    import { type RouteProps, useQueryState } from '$lib/components/ui/routing/index.js';
    import type { ColumnFiltersState } from '$lib/components/ui/data-table/types.js';
    const {}: RouteProps = $props();
    import Button from '$lib/components/ui/button/Button.svelte';
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import AdminPage from '../components/AdminPage.svelte';
    import AdminResultDialog from '../components/AdminResultDialog.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import type { AdminMcpServerResource } from '../schemas/resources/admin-mcp.schema.js';
    import type { AdminToolResource } from '../schemas/resources/admin-tools.schema.js';
    import { type AdminColumn, useAdminWorkspace } from '../workspace.svelte.js';
    import { McpTestSchema, McpDiscoverySchema } from '../schemas/admin-actions.js';

    const app = useApp();
    const { __ } = useTranslator();

    const toolColumns: AdminColumn<AdminToolResource>[] = [
        { id: 'name' },
        { id: 'mcp_server_id', filter: true },
        { id: 'kind', format: 'enum' },
        { id: 'active', format: 'boolean' },
        { id: 'access_rule', sortable: false },
        { id: 'mapped_capability' }
    ];
    // Share MCP server filters through links and keep them after reloads.
    const mcpServerId = useQueryState('mcp_server_id');
    const mcpServerFilter = $derived<ColumnFiltersState>(
        mcpServerId.current ? [{ id: 'mcp_server_id', value: mcpServerId.current }] : []
    );
    const tools = useAdminWorkspace(
        toolColumns,
        (signal, query) => app.restApi.getResourceCollection('admin-tools', { query, signal }),
        {
            editFields: (row, fields) =>
                fields.filter(
                    (field) =>
                        field.key !== 'mcp_server_id' &&
                        (field.key !== 'access_rule' || (app.can('mcp.manage') && app.can('roles.manage')))
                ),
            columnFilters: untrack(() => mcpServerFilter),
            save: async (values, row) => {
                if (!row) throw new Error(__('admin.errors.save'));
                return app.restApi.updateResource('admin-tools', row.id, values, {
                    headers: { 'If-Match': `"${row._version}"` }
                });
            },
            refresh: () => app.refreshConnection()
        }
    );

    const serverColumns: AdminColumn<AdminMcpServerResource>[] = [
        { id: 'server_label' },
        { id: 'kind', filter: true },
        { id: 'url', sortable: false },
        { id: 'status', format: 'enum' },
        { id: 'tools_count', sortable: false },
        { id: 'api_key_set', sortable: false }
    ];
    const servers = useAdminWorkspace(
        serverColumns,
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
                return row ?
                        app.restApi.updateResource('admin-mcp', row.id, values, {
                            headers: { 'If-Match': `"${row._version}"` }
                        })
                    :   app.restApi.createResource('admin-mcp', values);
            },
            remove: (row) =>
                app.restApi.deleteResource('admin-mcp', row.id, {
                    headers: { 'If-Match': `"${row._version}"` }
                }),
            refresh: async () => {
                for (const name of ['ai-models', 'ai-tools'] as const) {
                    if (app.stores.has(name)) await app.stores.get(name).loadData?.(app);
                }
                await tools.load();
            }
        }
    );
    let toolsHeading = $state<HTMLHeadingElement>();
    const selectedServer = $derived(
        tools.fields
            .find((field) => field.key === 'mcp_server_id' && field.type === 'select')
            ?.options.find((option) => String(option.value) === mcpServerId.current)?.label ?? mcpServerId.current
    );
    async function showTools(id: string) {
        mcpServerId.current = id;
        await tick();
        toolsHeading?.focus();
    }
    // Links and browser navigation update the filter without remounting the page.
    $effect(() => {
        const filters = mcpServerFilter;
        untrack(() => void tools.applyColumnFilters(filters));
    });
    // Table selections update the URL. Assignments do not subscribe this effect to URL changes.
    $effect(() => {
        const value = tools.columnFilters.find((item) => item.id === 'mcp_server_id')?.value;
        mcpServerId.current = typeof value === 'string' && value ? value : null;
    });
</script>

{#snippet mcpServer(row: AdminToolResource)}
    {#if row.mcp_server_id === null}
        {__('admin.tool_sources.builtin')}
    {:else}
        {@const server = tools.fields
            .find((field) => field.key === 'mcp_server_id' && field.type === 'select')
            ?.options.find((option) => String(option.value) === String(row.mcp_server_id))}
        {server?.label ?? row.mcp_server_id}
    {/if}
{/snippet}

{#snippet mappedCapability(row: AdminToolResource)}
    {#if row.mapped_capability}
        <span class="capability" data-capability={row.mapped_capability}>{row.mapped_capability}</span>
    {:else}
        —
    {/if}
{/snippet}

{#snippet accessRule(row: AdminToolResource)}
    {@const rule = tools.content?.access_rules.find((entry) => entry.name === row.access_rule)}
    {__(rule?.title_label ?? 'admin.tool_access_rules.unavailable.title')}
{/snippet}

<AdminPage
    workspace="mcp"
    recordSet={servers}
    related={[{ recordSet: tools, editor: 'tools', title: __('admin.tools') }]}
>
    <AdminSearch recordSet={servers} />
    <AdminTable
        caption={__('admin.sections.mcp')}
        recordSet={servers}
        rowMenuItems={(row) => [
            {
                label: __('admin.view_tools'),
                icon: Search01Icon,
                run: () => showTools(row.id)
            }
        ]}
    />
    <AdminResultDialog
        recordSet={servers}
        action="discover"
    >
        {#snippet children(response, id)}
            <ul>
                {#each response.tools as tool}
                    <li>{tool}</li>
                {/each}
            </ul>
            {#if id !== null}
                <Button
                    variant="stroke"
                    onclick={() => {
                        servers.closeResult('discover');
                        void showTools(id);
                    }}
                >
                    {__('admin.view_tools')}
                </Button>
            {/if}
        {/snippet}
    </AdminResultDialog>
    <section class="tools">
        <div class="tools-heading">
            <h2 tabindex="-1" bind:this={toolsHeading}>
                {mcpServerId.current ? __('admin.tools_of', { server: String(selectedServer) }) : __('admin.tools')}
            </h2>
            {#if mcpServerId.current}
                <Button variant="stroke" onclick={() => (mcpServerId.current = null)}>
                    {__('admin.show_all_tools')}
                </Button>
            {/if}
        </div>
        <AdminSearch recordSet={tools} />
        <AdminTable
            caption={__('admin.tools')}
            recordSet={tools}
            cells={{ mcp_server_id: mcpServer, mapped_capability: mappedCapability, access_rule: accessRule }}
        />
    </section>
</AdminPage>

<style>
    .tools {
        margin-top: var(--space-8);
    }
    .tools-heading {
        display: flex;
        gap: var(--space-3);
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: var(--space-3);
    }
    h2 {
        font-size: var(--font-size-lg);
        font-weight: 600;
    }
    .capability {
        display: inline-flex;
        padding: var(--space-1) var(--space-2_5);
        border-radius: var(--corner-xs);
        background-color: var(--capability-surface);
        color: var(--capability-color);
        font-size: var(--font-size-xs);
    }
</style>
