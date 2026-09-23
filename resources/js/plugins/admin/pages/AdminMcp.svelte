<script lang="ts">
    import { untrack } from 'svelte';
    import { type RouteProps, useQueryState } from '$lib/components/ui/routing/index.js';
    import type { ColumnFiltersState } from '$lib/components/ui/data-table/types.js';
    const {}: RouteProps = $props();
    import Button from '$lib/components/ui/button/Button.svelte';
    import AdminPage from '../components/AdminPage.svelte';
    import AdminResultDialog from '../components/AdminResultDialog.svelte';
    import AdminRowSwitch from '../components/AdminRowSwitch.svelte';
    import AdminSearch from '../components/AdminSearch.svelte';
    import AdminTable from '../components/AdminTable.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import type { AdminField } from '../schemas/admin-content.js';
    import { revealServerId } from '../mcp.js';
    import type { AdminMcpServerResource } from '../schemas/resources/admin-mcp.schema.js';
    import type { AdminToolResource } from '../schemas/resources/admin-tools.schema.js';
    import { type AdminColumn, type AdminReader, useAdminWorkspace } from '../workspace.svelte.js';
    import { McpTestSchema, McpDiscoverySchema } from '../schemas/admin-actions.js';

    const app = useApp();
    const { __ } = useTranslator();

    /** Both tool Workspaces read the same section; a server's tools come from its value filter. */
    const readTools: AdminReader<AdminToolResource> = (signal, query) =>
        app.restApi.getResourceCollection('admin-tools', { query, signal });
    /** The server of a tool is decided by the expanded row, not by the editor. */
    const toolFields = (_row: AdminToolResource, fields: AdminField[]) =>
        fields.filter((field) => field.key !== 'mcp_server_id');
    const saveTool = async (values: Record<string, unknown>, row: AdminToolResource | null) => {
        if (!row) throw new Error(__('admin.errors.save'));
        return app.restApi.updateResource('admin-tools', row.id, values, {
            headers: { 'If-Match': `"${row._version}"` }
        });
    };

    // The expanded server row is shared through links and kept after reloads.
    const mcpServerId = useQueryState('mcp_server_id');
    const mcpServerFilter = $derived<ColumnFiltersState>(
        mcpServerId.current ? [{ id: 'mcp_server_id', value: mcpServerId.current }] : []
    );

    const toolColumns: AdminColumn<AdminToolResource>[] = [
        { id: 'name' },
        { id: 'kind', format: 'enum' },
        { id: 'active', sortable: false },
        { id: 'mapped_capability' }
    ];
    const tools = useAdminWorkspace(toolColumns, readTools, {
        editFields: toolFields,
        columnFilters: untrack(() => mcpServerFilter),
        save: saveTool,
        refresh: () => app.refreshConnection()
    });
    // The tools of a server usually fit on one page of the nested table; 100 is the largest page the API
    // serves, so a server with more tools pages through them in the nested table.
    tools.pagination = { pageIndex: 0, pageSize: 100 };
    /** The nested table may only show rows the API returned for the expanded server. */
    function showsToolsOf(id: string): boolean {
        return (
            !tools.stale &&
            tools.columnFilters.some((item) => item.id === 'mcp_server_id' && String(item.value) === id)
        );
    }

    const builtinToolColumns: AdminColumn<AdminToolResource>[] = [
        { id: 'name' },
        { id: 'active', sortable: false },
        { id: 'mapped_capability' }
    ];
    /** Built-in HAWKI tools have no server; the API filters them by their `function` type. */
    const builtinTools = useAdminWorkspace(builtinToolColumns, readTools, {
        editFields: toolFields,
        columnFilters: [{ id: 'kind', value: 'function' }],
        save: saveTool,
        refresh: () => app.refreshConnection()
    });
    builtinTools.pagination = { pageIndex: 0, pageSize: 100 };

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
                await builtinTools.load();
            }
        }
    );
    function showTools(id: string) {
        mcpServerId.current = id;
    }
    // Links and browser navigation expand the server without remounting the page.
    $effect(() => {
        const filters = mcpServerFilter;
        untrack(() => void tools.applyColumnFilters(filters));
    });
    // Expanding a row updates the URL. Assignments do not subscribe this effect to URL changes.
    $effect(() => {
        const value = tools.columnFilters.find((item) => item.id === 'mcp_server_id')?.value;
        mcpServerId.current = typeof value === 'string' && value ? value : null;
    });
    /** A missing selection is looked up once, including when it no longer exists. */
    let revealed = $state<string | null>(null);
    // Resolve links outside the loaded page independently of labels and existing filters.
    $effect(() => {
        const id = revealServerId({
            selected: mcpServerId.current,
            rows: servers.rows,
            loading: servers.loading,
            attempted: revealed
        });
        if (id === null) return;
        revealed = mcpServerId.current;
        untrack(() => {
            // Nothing may hide the row the link points at: neither a page, a search nor a value filter.
            servers.search = '';
            void servers.applyColumnFilters([{ id: 'id', value: id }]);
        });
    });
    function clearServerSelection() {
        mcpServerId.current = null;
        revealed = null;
        servers.columnFilters = servers.columnFilters.filter((filter) => filter.id !== 'id');
    }
</script>

{#snippet mappedCapability(row: AdminToolResource)}
    {#if row.mapped_capability}
        <span class="capability" data-capability={row.mapped_capability}>{row.mapped_capability}</span>
    {:else}
        —
    {/if}
{/snippet}

{#snippet toolActive(row: AdminToolResource)}
    <AdminRowSwitch
        checked={row.active}
        label={__('admin.fields.active')}
        disabled={tools.locked(row)}
        onToggle={(enabled) => tools.update(row, { active: enabled })}
    />
{/snippet}

{#snippet builtinActive(row: AdminToolResource)}
    <AdminRowSwitch
        checked={row.active}
        label={__('admin.fields.active')}
        disabled={builtinTools.locked(row)}
        onToggle={(enabled) => builtinTools.update(row, { active: enabled })}
    />
{/snippet}

{#snippet serverTools(row: AdminMcpServerResource)}
    {#if showsToolsOf(row.id)}
        <AdminTable
            embedded
            caption={__('admin.tools_of', { server: row.server_label })}
            recordSet={tools}
            cells={{ active: toolActive, mapped_capability: mappedCapability }}
        />
    {:else}
        <p
            class="tools-status"
            role="status"
        >
            {tools.error && !tools.loading ? tools.error : __('ui.loading')}
        </p>
    {/if}
{/snippet}

<AdminPage
    workspace="mcp"
    recordSet={servers}
    related={[
        { recordSet: tools, editor: 'tools', title: __('admin.tools') },
        { recordSet: builtinTools, editor: 'tools', title: __('admin.tools') }
    ]}
>
    <AdminSearch recordSet={servers} beforeSubmit={clearServerSelection} />
    {#if servers.columnFilters.some((filter) => filter.id === 'id')}
        <Button
            variant="stroke"
            onclick={() => {
                clearServerSelection();
                servers.search = '';
                void servers.submitSearch();
            }}
        >
            {__('admin.show_all_servers')}
        </Button>
    {/if}
    <AdminTable
        caption={__('admin.sections.mcp')}
        recordSet={servers}
        details={serverTools}
        bind:expanded={mcpServerId.current}
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
                        showTools(id);
                    }}
                >
                    {__('admin.view_tools')}
                </Button>
            {/if}
        {/snippet}
    </AdminResultDialog>
    <section class="builtin">
        <h2>{__('admin.tool_sources.builtin')}</h2>
        <AdminTable
            caption={__('admin.tool_sources.builtin')}
            recordSet={builtinTools}
            cells={{ active: builtinActive, mapped_capability: mappedCapability }}
        />
    </section>
</AdminPage>

<style>
    .builtin {
        margin-top: var(--space-8);
    }
    h2 {
        font-size: var(--font-size-lg);
        font-weight: 600;
        margin-bottom: var(--space-3);
    }
    .tools-status {
        margin: 0;
        padding: var(--space-3) var(--space-4);
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
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
