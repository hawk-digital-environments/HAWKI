<script lang="ts">
    import { readAdmin, createAdmin, updateAdmin, deleteAdmin, runAdmin } from '../api.js';
    import { onMount, type Snippet } from 'svelte';
    import Page from '$lib/components/ui/page/Page.svelte';
    import PageHeaderBar from '$lib/components/ui/page/PageHeaderBar.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Input from '$lib/components/ui/input/Input.svelte';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import DataTable from '$lib/components/ui/data-table/DataTable.svelte';
    import type {
        ColumnFiltersState,
        DataTableColumn,
        DataTableFilter,
        PaginationState,
        SortingState
    } from '$lib/components/ui/data-table/types.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useBreakpoint } from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';
    import AdminEditor from './AdminEditor.svelte';
    import type { AdminContent, AdminField, AdminRow } from '../schemas/admin-content.js';
    import { sections, type SectionId } from '../sections.js';
    import AdminActionMenu, { type AdminMenuItem } from './AdminActionMenu.svelte';
    import { provideAdminWorkspace, type AdminAction, type AdminWorkspaceContext } from '../workspace.js';
    import { createDraft, prepareValues } from '../form.js';
    import { adminActionIcons } from '../actionIcons.js';

    let {
        section,
        searchable = true,
        pollInterval,
        rowActions = [],
        pageActions = [],
        query,
        filters,
        beforeTable,
        afterTable,
        body,
        menuItems,
        hint,
        editable = false,
        resettable = false,
        editSystemRows = false,
        editFields,
        cells,
        filterColumns = []
    }: {
        section: SectionId;
        searchable?: boolean;
        pollInterval?: number;
        rowActions?: AdminAction[];
        pageActions?: AdminAction[];
        query?: () => Record<string, string | number>;
        filters?: Snippet<[AdminWorkspaceContext]>;
        beforeTable?: Snippet<[AdminWorkspaceContext]>;
        afterTable?: Snippet<[AdminWorkspaceContext]>;
        body?: Snippet<[AdminWorkspaceContext]>;
        menuItems?: (context: AdminWorkspaceContext) => AdminMenuItem[];
        hint?: string;
        editable?: boolean;
        resettable?: boolean;
        editSystemRows?: boolean;
        editFields?: (row: AdminRow, fields: AdminField[]) => AdminField[];
        /** Extra or replaced table cells by column id; ids the section does not provide become non-sortable columns. */
        cells?: Partial<Record<string, Snippet<[AdminRow]>>>;
        /** Columns offered as value filters above the table; each needs a select field whose options supply the values. */
        filterColumns?: string[];
    } = $props();
    const app = useApp();
    const { __ } = useTranslator();
    const breakpoint = useBreakpoint();
    let content = $state<AdminContent | null>(null);
    let loading = $state(true);
    let busy = $state(false);
    let updating = $state<string[]>([]);
    let error = $state('');
    let notice = $state('');
    let search = $state('');
    let pagination = $state<PaginationState>({ pageIndex: 0, pageSize: 25 });
    let sorting = $state<SortingState>([]);
    let columnFilters = $state<ColumnFiltersState>([]);
    let editor = $state<{ row: AdminRow | null; fields: AdminField[] } | null>(null);
    let confirmation = $state<{ title: string; description: string; run: () => Promise<void> } | null>(null);
    let result = $state<{
        models?: { model_id: string; label: string }[];
        tools?: string[];
        tokens?: Record<string, unknown>[];
    } | null>(null);
    let providerId = $state<string | null>(null);
    let candidates = $state<string[]>([]);
    let discoverySearch = $state('');
    const discoveredModels = $derived.by(() => {
        const models = result?.models ?? [];
        const needle = discoverySearch.trim().toLowerCase();
        if (!needle) return models;
        return models.filter(
            (model) =>
                model.label.toLowerCase().includes(needle) || model.model_id.toLowerCase().includes(needle)
        );
    });
    let trigger: HTMLElement | null = null;
    let toolbar = $state<HTMLDivElement>();
    let request: AbortController | undefined;
    let disposed = false;

    const server = $derived(content?.total !== undefined);
    const columns = $derived<DataTableColumn<AdminRow>[]>([
        ...(content?.columns ?? []).map((key) => ({
            id: key,
            accessorFn: (row: AdminRow) => row[key],
            header: __('admin.fields.' + key),
            enableSorting: !['api_key_set', 'seen_count', 'accepted_count'].includes(key),
            cell: (info: { getValue: () => unknown }) => display(info.getValue(), key)
        })),
        ...(content ? Object.keys(cells ?? {}) : [])
            .filter((key) => !content?.columns.includes(key))
            .map((key) => ({
                id: key,
                accessorFn: (row: AdminRow) => row[key],
                header: __('admin.fields.' + key),
                enableSorting: false
            }))
    ]);
    const tableFilters = $derived<DataTableFilter[]>(
        filterColumns.flatMap((key) => {
            const field = selectField(key);
            if (!field?.options.length) return [];
            return [
                {
                    column: key,
                    label: __('admin.fields.' + key),
                    options: field.options.map((option) => ({ value: String(option.value), label: option.label }))
                }
            ];
        })
    );
    const allowed = $derived(
        app.can('admin.access') && app.can(sections.find((item) => item.id === section)!.permission)
    );
    const canEdit = $derived(!!content?.fields.length || editable);
    const context = $derived<AdminWorkspaceContext>({
        content,
        loading,
        busy,
        updating,
        dialogOpen: !!editor || !!confirmation || !!result,
        reload: load,
        action,
        edit: openEditor,
        reset: remove,
        update
    });
    provideAdminWorkspace(() => context);
    const toolbarItems = $derived<AdminMenuItem[]>([
        { label: __('admin.reload'), icon: adminActionIcons.reload, disabled: loading, run: () => load() },
        ...pageActions.map((item) => ({
            label: __('admin.actions.' + item.id),
            icon: adminActionIcons[item.id],
            destructive: item.destructive,
            run: (target: HTMLButtonElement | null) => action(item, undefined, target)
        })),
        ...(menuItems?.(context) ?? [])
    ]);

    function selectField(key: string): AdminField | undefined {
        return content?.fields.find((field) => field.key === key && field.type === 'select');
    }

    function display(value: unknown, key: string): string {
        if (section === 'settings' && key === 'key') return __('admin.settings_labels.' + value);
        if (value === null || value === undefined || value === '') return '—';
        // Numeric select options reference rows of another table; show their labels instead of the ids.
        const options = selectField(key)?.options ?? [];
        if (options.some((option) => typeof option.value === 'number')) {
            const option = options.find((option) => String(option.value) === String(value));
            if (option) return option.label;
        }
        if (typeof value === 'boolean' || ['is_system', 'is_published', 'active', 'admin_disabled'].includes(key))
            return __(value ? 'admin.yes' : 'admin.no');
        if (['source', 'status'].includes(key) && typeof value === 'string') return __('admin.values.' + value);
        if (value === '[set]' || value === '[not set]')
            return __(value === '[set]' ? 'admin.secret_set' : 'admin.secret_unset');
        if (typeof value === 'object') return JSON.stringify(value);
        const text = String(value);
        return text.length > 180 ? text.slice(0, 180) + '…' : text;
    }

    async function load() {
        if (!allowed) return;
        request?.abort();
        const controller = new AbortController();
        request = controller;
        loading = true;
        error = '';
        try {
            const where = Object.fromEntries(
                columnFilters
                    .filter((item) => item.value !== undefined && item.value !== '')
                    .map((item) => [item.id, String(item.value)])
            );
            const filter =
                query ? query() : (
                    {
                        page: pagination.pageIndex + 1,
                        size: pagination.pageSize,
                        ...(search ? { search } : {}),
                        ...(sorting[0] ? { sort: sorting[0].id, direction: sorting[0].desc ? 'desc' : 'asc' } : {}),
                        ...(Object.keys(where).length ? { where } : {})
                    }
                );
            const response = await readAdmin(app, section, { filter }, controller.signal);
            if (!controller.signal.aborted && !disposed) content = response;
        } catch (failure) {
            if (!controller.signal.aborted && !disposed)
                error = failure instanceof Error ? failure.message : __('admin.errors.load');
        } finally {
            if (!controller.signal.aborted && !disposed) loading = false;
        }
    }

    onMount(() => {
        void load();
        const timer =
            pollInterval ?
                window.setInterval(() => {
                    if (!document.hidden && !busy && !updating.length && !loading) void load();
                }, pollInterval)
            :   undefined;
        return () => {
            disposed = true;
            request?.abort();
            if (timer) window.clearInterval(timer);
        };
    });

    function restoreFocus() {
        return trigger?.isConnected ? trigger : (toolbar?.querySelector<HTMLElement>('button') ?? null);
    }
    function openEditor(row: AdminRow | null, target: HTMLElement | null) {
        trigger = target;
        const fields = row && editFields ? editFields(row, content?.fields ?? []) : (content?.fields ?? []);
        editor = { row, fields };
    }

    async function invalidate() {
        if (['users', 'roles', 'mappings'].includes(section)) await app.refreshConnection();
        const stores =
            ['providers', 'models', 'tools', 'mcp'].includes(section) ?
                ['ai-models', 'ai-tools']
            : section === 'system-models' ? ['ai-models', 'ai-tools', 'system-prompts']
            : section === 'announcements' ? ['announcements']
            : [];
        for (const name of stores) if (app.stores.has(name)) await app.stores.get(name).loadData?.(app);
    }

    async function save(values: Record<string, unknown>) {
        if (editor?.row) await updateAdmin(app, section, editor.row, values);
        else await createAdmin(app, section, values);
        notice = __('admin.saved');
        // A successful write stays successful even if refreshing another store fails.
        try {
            await invalidate();
        } catch {
            notice = __('admin.saved_refresh');
        }
        await load();
    }

    async function update(row: AdminRow, changes: Record<string, unknown>) {
        if (busy || updating.includes(row.id)) return;
        updating = [...updating, row.id];
        error = '';
        try {
            const fields = content?.fields ?? [];
            const prepared = prepareValues(fields, { ...createDraft(fields, row), ...changes });
            if (Object.keys(prepared.errors).length) throw new Error(__('admin.errors.invalid_value'));
            await updateAdmin(app, section, row, prepared.values);
            notice = __('admin.saved');
            try {
                await invalidate();
            } catch {
                notice = __('admin.saved_refresh');
            }
            await load();
        } catch (failure) {
            error = failure instanceof Error ? failure.message : __('admin.errors.save');
        } finally {
            updating = updating.filter((id) => id !== row.id);
        }
    }

    function remove(row: AdminRow, target: HTMLElement | null) {
        trigger = target;
        confirmation = {
            title: __(resettable ? 'admin.reset' : 'admin.delete'),
            description: __('admin.delete_description', {
                name: String(row.name ?? row.title ?? row.label ?? row.key ?? row.id)
            }),
            run: async () => {
                await deleteAdmin(app, section, row);
                notice = __(resettable ? 'admin.reset_done' : 'admin.deleted');
                if ((content?.rows.length ?? 0) <= 1)
                    pagination = { ...pagination, pageIndex: Math.max(0, pagination.pageIndex - 1) };
                await invalidate();
                await load();
            }
        };
    }

    function action(item: AdminAction, id?: string, target?: HTMLElement | null) {
        trigger = target ?? null;
        if (item.confirm) {
            confirmation = {
                title: __('admin.actions.' + item.id),
                description: __('admin.action_confirmation'),
                run: () => run(item.id, id)
            };
        } else {
            void run(item.id, id);
        }
    }

    async function run(action: string, id?: string) {
        if (busy) return;
        busy = true;
        error = '';
        try {
            const response = await runAdmin(app, section, action, id);
            if (action === 'discover' || action === 'tokens') {
                result = response;
                providerId = id ?? null;
                candidates = [];
                discoverySearch = '';
            }
            notice = __('admin.action_done');
            await load();
        } catch (failure) {
            error = failure instanceof Error ? failure.message : __('admin.errors.save');
        } finally {
            busy = false;
        }
    }

    async function addModels() {
        if (busy || !providerId) return;
        busy = true;
        error = '';
        try {
            for (const modelId of [...candidates]) {
                const model = result?.models?.find((item) => item.model_id === modelId);
                await createAdmin(app, 'models', {
                    model_id: modelId,
                    label: model?.label ?? modelId,
                    provider_id: Number(providerId),
                    active: false,
                    model_type: 'chat',
                    tools: [],
                    usage_rules: ['main']
                });
                candidates = candidates.filter((id) => id !== modelId);
                if (result?.models)
                    result = { ...result, models: result.models.filter((item) => item.model_id !== modelId) };
            }
            notice = __('admin.models_added');
        } catch (failure) {
            error = failure instanceof Error ? failure.message : __('admin.errors.save');
        } finally {
            busy = false;
        }
    }
</script>

{#if allowed}
    <Page style="grid-template-columns: minmax(0, 1fr); min-width: 0">
        {#snippet header()}
            <PageHeaderBar heading={__('admin.sections.' + section)}>
                <div
                    class="header-actions"
                    bind:this={toolbar}
                >
                    <AdminActionMenu
                        label={__('admin.page_actions', { name: __('admin.sections.' + section) })}
                        items={toolbarItems}
                        disabled={busy}
                        compact={breakpoint.is('bpSmAndSmaller')}
                        dialogOpen={context.dialogOpen}
                    />
                    {#if content?.create}
                        <Button
                            type="button"
                            variant="fill"
                            disabled={busy}
                            onclick={(event) => openEditor(null, event.currentTarget)}
                        >
                            {__('admin.create')}
                        </Button>
                    {/if}
                </div>
            </PageHeaderBar>
        {/snippet}
        <div class="workspace">
            <p class="description">{__('admin.descriptions.' + section)}</p>
            {#if filters || searchable}
                <div class="toolbar">
                    {@render filters?.(context)}
                    {#if searchable}
                        <form
                            class="search"
                            onsubmit={(event) => {
                                event.preventDefault();
                                pagination = { ...pagination, pageIndex: 0 };
                                void load();
                            }}
                        >
                            <Input
                                aria-label={__('admin.search')}
                                type="search"
                                bind:value={search}
                            /><Button
                                type="submit"
                                variant="stroke"
                                disabled={loading}>{__('admin.search')}</Button
                            >
                        </form>
                    {/if}
                </div>
            {/if}
            {#if hint}<p class="hint">{hint}</p>{/if}
            <p
                role="status"
                class="feedback"
            >
                {notice}
            </p>
            {#if error}<p
                    role="alert"
                    class="error"
                >
                    {error}
                </p>{/if}
            {@render beforeTable?.(context)}
            {#if body}
                {@render body(context)}
            {:else}
                <DataTable
                    data={content?.rows ?? []}
                    {columns}
                    caption={__('admin.sections.' + section)}
                    {loading}
                    {server}
                    total={content?.total}
                    bind:pagination
                    bind:sorting
                    filters={tableFilters}
                    bind:columnFilters
                    onChange={() => {
                        if (server) void load();
                    }}
                    actions={canEdit || content?.delete || rowActions.length ? renderRowActions : undefined}
                    {cells}
                />
            {/if}
            {@render afterTable?.(context)}
        </div>
    </Page>

    {#snippet renderRowActions(row: AdminRow)}
        {@const items: AdminMenuItem[] = [
        ...(canEdit && (editSystemRows || !row.is_system) ? [{label: __('admin.edit'), icon: adminActionIcons.edit, run: (target: HTMLButtonElement | null) => openEditor(row, target)}] : []),
        ...rowActions.map(item => ({label: __('admin.actions.' + item.id), icon: adminActionIcons[item.id], destructive: item.destructive, run: (target: HTMLButtonElement | null) => action(item, row.id, target)})),
        ...((content?.delete || resettable && row.source === 'database') && !row.is_system ? [{label: __(resettable ? 'admin.reset' : 'admin.delete'), icon: resettable ? adminActionIcons.reset : adminActionIcons.delete, destructive: true, run: (target: HTMLButtonElement | null) => remove(row, target)}] : [])
    ]}
        <AdminActionMenu
            compact
            label={__('admin.row_actions', { name: String(row.name ?? row.title ?? row.label ?? row.key ?? row.id) })}
            {items}
            disabled={busy || updating.includes(row.id)}
            dialogOpen={context.dialogOpen}
        />
    {/snippet}

    {#if editor}
        <AdminEditor
            {section}
            fields={editor.fields}
            row={editor.row}
            title={__('admin.sections.' + section) + ' · ' + __(editor.row ? 'admin.edit' : 'admin.create')}
            onSave={save}
            onClose={() => (editor = null)}
            {restoreFocus}
        />
    {/if}
    <ConfirmDialog
        open={!!confirmation}
        title={confirmation?.title}
        description={confirmation?.description}
        onOpenChange={(open) => {
            if (!open) confirmation = null;
        }}
        restoreFocusTo={restoreFocus}
        onConfirm={async () => {
            try {
                await confirmation?.run();
            } catch (failure) {
                error = failure instanceof Error ? failure.message : __('admin.errors.save');
            }
        }}
    />
    <Dialog
        contentProps={{
            onCloseAutoFocus: (event) => {
                const target = restoreFocus();
                if (target) {
                    event.preventDefault();
                    target.focus({ preventScroll: true });
                }
            }
        }}
        open={!!result}
        title={__('admin.action_result')}
        onOpenChange={(open) => {
            if (!open && !busy) result = null;
        }}
    >
        {#if error}<p role="alert">{error}</p>{/if}
        {#if result?.models}
            <p>{__('admin.discovery_hint')}</p>
            <Input
                aria-label={__('admin.discovery_search')}
                placeholder={__('admin.discovery_search')}
                type="search"
                bind:value={discoverySearch}
            />
            <p
                class="discovery-count"
                aria-live="polite"
            >
                {__('admin.discovery_count', {
                    shown: String(discoveredModels.length),
                    total: String(result.models.length)
                })}
            </p>
            {#if discoveredModels.length === 0}
                <p class="discovery-empty">{__('admin.discovery_no_matches')}</p>
            {/if}
            <ul class="discovered">
                {#each discoveredModels as model (model.model_id)}<li>
                        <label
                            ><input
                                type="checkbox"
                                checked={candidates.includes(model.model_id)}
                                disabled={busy || !app.can('models.manage')}
                                onchange={(event) =>
                                    (candidates =
                                        event.currentTarget.checked ?
                                            [...candidates, model.model_id]
                                        :   candidates.filter((id) => id !== model.model_id))}
                            />{model.label}<small>{model.model_id}</small></label
                        >
                    </li>{/each}
            </ul>
            {#if app.can('models.manage')}<Button
                    variant="fill"
                    disabled={busy || candidates.length === 0}
                    onclick={addModels}>{__('admin.add_models')}</Button
                >{/if}
        {:else if result?.tools}<ul>
                {#each result.tools as tool}<li>{tool}</li>{/each}
            </ul>
        {:else if result?.tokens}<ul>
                {#each result.tokens as token}<li>
                        {String(token.name)} · {String(token.last_used_at ?? __('admin.never'))}
                    </li>{/each}
            </ul>{/if}
    </Dialog>
{:else}
    <Page title={__('admin.forbidden_title')}><p>{__('admin.forbidden')}</p></Page>
{/if}

<style>
    .workspace {
        padding: var(--space-6);
        max-width: 100rem;
        margin-inline: auto;
    }
    .description {
        margin-bottom: var(--space-5);
        max-width: 65rem;
        color: var(--color-text-muted);
    }
    .toolbar,
    .toolbar form {
        display: flex;
        align-items: end;
        flex-wrap: wrap;
        gap: var(--space-3);
    }
    .toolbar {
        margin-bottom: var(--space-3);
    }
    .header-actions {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        gap: var(--space-2);
    }
    .search {
        flex: 1;
        min-width: 15rem;
        max-width: 32rem;
    }
    .search :global(input) {
        flex: 1;
        min-width: 8rem;
    }
    .hint,
    .feedback {
        font-size: var(--font-size-sm);
        margin-block: var(--space-3);
    }
    .feedback:empty {
        display: none;
    }
    .error {
        border: var(--border);
        padding: var(--space-3);
        border-radius: var(--corner-md);
        margin-block: var(--space-3);
    }
    .discovery-count,
    .discovery-empty {
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
        margin-block: var(--space-2);
    }
    .discovered {
        list-style: none;
        padding: 0;
        overflow-y: auto;
        max-height: 24rem;
    }
    .discovered label {
        display: flex;
        gap: var(--space-2);
        flex-wrap: wrap;
        padding-block: var(--space-2);
    }
    .discovered small {
        color: var(--color-text-muted);
    }
    @media (--bp-md-and-smaller) {
        .workspace {
            padding: var(--space-4);
        }
    }
</style>
