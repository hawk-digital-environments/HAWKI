import { onMount } from 'svelte';
import type { ColumnFiltersState, PaginationState, SortingState } from '$lib/components/ui/data-table/types.js';
import type { FetchCollectionQuery } from '$lib/kernel/api/buildQueryString.js';
import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
import type { JsonApiCollection } from '$lib/kernel/api/jsonApiEncoding.js';
import { adminContent, type AdminContent, type AdminField, type AdminRow } from './schemas/admin-content.js';
import { createDraft, prepareValues, rowName } from './form.js';

/** A column of an admin table, declared by the page that renders it. */
export interface AdminColumn {
    /** Key of the row value shown in the cell; also the sort key sent to the server. */
    id: string;
    /** Header label; defaults to the `admin.fields.<id>` translation. */
    header?: string;
    /** The column can be sorted. Defaults to `true`. */
    sortable?: boolean;
    /** API field used for sorting when it differs from the displayed key. */
    sortKey?: string;
    /** `boolean` shows yes/no for truthy values, `enum` translates the value through `admin.values.<value>`. */
    format?: 'boolean' | 'enum';
    /** Offer the options of the select field with the same key as a value filter in the header. */
    filter?: boolean;
}

/**
 * Reads the section content for the current table state. The page supplies
 * it, since every section has its own endpoint, and may
 * ignore `query` when the page filters through its own form instead.
 */
export type AdminReader = (signal: AbortSignal, query: FetchCollectionQuery) => Promise<JsonApiCollection<AdminRow>>;

/** A server side action offered in the page menu or per row. */
export interface AdminAction {
    id: string;
    run: () => Promise<unknown>;
    /** Ask for confirmation before the action runs. */
    confirm?: boolean;
    destructive?: boolean;
}

/** Row actions are keyed by id; each request infers its own response type. */
export type AdminRowActions<Results> = {
    [Id in keyof Results]?: {
        run: () => Promise<Results[Id]>;
        confirm?: boolean;
        destructive?: boolean;
        dialog?: boolean;
    };
};

export interface AdminWorkspaceOptions<Results extends Record<string, unknown> = {}> {
    /** Actions for the current row; dialog responses infer the workspace result type. */
    rowActions?: (row: AdminRow) => AdminRowActions<Results>;
    /** Persist an editor submission; a null row creates a record. */
    save?: (values: Record<string, unknown>, row: AdminRow | null) => Promise<unknown>;
    /** Delete or reset the given record. */
    remove?: (row: AdminRow) => Promise<unknown>;
    /** Refresh the page's dependent caches after a successful write. */
    refresh?: () => Promise<unknown>;
    /** Value filters that apply before the first read; users can change or clear them in the table. */
    columnFilters?: ColumnFiltersState;
    /** Rows come from config files; deleting a database row only resets it to the file value. */
    resettable?: boolean;
    /** Narrows or adjusts the editor fields for a given row before the editor opens. */
    editFields?: (row: AdminRow, fields: AdminField[]) => AdminField[];
}

export interface AdminEditorState {
    row: AdminRow | null;
    fields: AdminField[];
}

export interface AdminConfirmation {
    title: string;
    description: string;
    run: () => Promise<void>;
}

export interface AdminActionResult<Result> {
    /** Id of the row the action ran for. */
    id: string | null;
    response: Result;
    /** Focus returns to the trigger for this result, even when other dialogs opened later. */
    trigger: HTMLElement | null;
}

type Translate = (label: string, replacements?: Record<string, string>) => string;

/**
 * Everything one admin section page works with: the columns the page
 * declared, the content of the last read, the table state that shapes each
 * read (search, pagination, sorting, value filters), the writes and actions of
 * the section and the dialogs they open (editor, confirmation, action result).
 *
 * A page creates it with {@link useAdminWorkspace} and passes it to
 * `AdminPage`, `AdminSearch`, `AdminTable` and `AdminResultDialog`; cells call
 * {@link update} directly.
 */
export class AdminWorkspace<Results extends Record<string, unknown> = {}> {
    readonly columns: AdminColumn[];
    readonly resettable: boolean;

    content = $state<AdminContent | null>(null);
    /** `true` until the first response arrives, and during every later read. */
    loading = $state(true);
    /** Message of the last failed read, write or action; cleared when the next one starts. */
    error = $state('');
    search = $state('');
    pagination = $state<PaginationState>({ pageIndex: 0, pageSize: 25 });
    sorting = $state<SortingState>([]);
    columnFilters = $state<ColumnFiltersState>([]);

    /** A write or action is running; the whole page is locked. */
    busy = $state(false);
    /** Ids of rows currently being saved through `update`; only those rows are locked, not the page. */
    updating = $state<string[]>([]);
    /** Announced through the status region after a successful write or action. */
    notice = $state('');
    editor = $state<AdminEditorState | null>(null);
    confirmation = $state<AdminConfirmation | null>(null);
    results = $state<{ [Id in keyof Results]?: AdminActionResult<Results[Id]> }>({});

    /** Element that opened the current dialog; focus returns there when the dialog closes. */
    trigger: HTMLElement | null = null;
    /** Focus target when the trigger left the DOM (e.g. a deleted row's menu); `AdminPage` points it at its toolbar. */
    focusFallback: () => HTMLElement | null = () => null;

    readonly rows = $derived<AdminRow[]>(this.content?.rows ?? []);
    readonly fields = $derived<AdminField[]>(this.content?.fields ?? []);
    /** Row count on the server; `undefined` when the section returns everything at once. */
    readonly total = $derived(this.content?.total);
    readonly canCreate = $derived.by(() => !!this.operations.save && !!this.content?.create);
    readonly canEdit = $derived.by(() => !!this.operations.save && this.fields.length > 0);
    readonly canDelete = $derived.by(() => !!this.operations.remove && !!this.content?.delete);
    readonly dialogOpen = $derived(!!this.editor || !!this.confirmation || Object.values(this.results).some(Boolean));

    private readonly __: Translate;
    private readonly read: AdminReader;
    private readonly editFields?: (row: AdminRow, fields: AdminField[]) => AdminField[];
    private readonly operations: AdminWorkspaceOptions<Results>;
    private request?: AbortController;

    constructor(
        __: Translate,
        columns: AdminColumn[],
        read: AdminReader,
        options: AdminWorkspaceOptions<Results> = {}
    ) {
        this.__ = __;
        this.operations = options;
        this.columns = columns;
        this.read = read;
        this.resettable = !!options.remove && (options.resettable ?? false);
        this.editFields = options.editFields;
        this.columnFilters = options.columnFilters ?? [];
    }

    /** JSON:API query for the current table state: page, size, search, sort and value filters. */
    get query(): FetchCollectionQuery {
        const where = Object.fromEntries(
            this.columnFilters
                .filter((item) => item.value !== undefined && item.value !== '')
                .map((item) => [item.id, String(item.value)])
        );
        const [sort] = this.sorting;
        return {
            page: { number: this.pagination.pageIndex + 1, size: this.pagination.pageSize },
            ...(sort ?
                {
                    sort: `${sort.desc ? '-' : ''}${this.columns.find((column) => column.id === sort.id)?.sortKey ?? sort.id}`
                }
            :   {}),
            filter: {
                ...(this.search ? { search: this.search } : {}),
                ...(Object.keys(where).length ? { where } : {})
            }
        };
    }

    /** Reads the section for the current query; a newer read aborts an older one still in flight. */
    async load(): Promise<void> {
        this.request?.abort();
        const controller = new AbortController();
        this.request = controller;
        this.loading = true;
        this.error = '';
        try {
            const content = await this.read(controller.signal, this.query);
            if (!controller.signal.aborted) this.content = adminContent(content);
        } catch (failure) {
            if (!controller.signal.aborted) this.error = this.message(failure, 'admin.errors.load');
        } finally {
            if (!controller.signal.aborted) this.loading = false;
        }
    }

    /** Reads the current search term from the first page. */
    submitSearch(): Promise<void> {
        this.pagination = { ...this.pagination, pageIndex: 0 };
        return this.load();
    }

    /** Replaces the value filters and reads from the first page; a no-op when they already apply. */
    applyColumnFilters(filters: ColumnFiltersState): Promise<void> {
        if (JSON.stringify(filters) === JSON.stringify(this.columnFilters)) return Promise.resolve();
        this.columnFilters = filters;
        this.pagination = { ...this.pagination, pageIndex: 0 };
        return this.load();
    }

    /** Aborts a running read; the page is going away. */
    dispose(): void {
        this.request?.abort();
    }

    /** `row` must not be changed right now: the page is busy or the row itself is being saved. */
    locked(row: AdminRow): boolean {
        return this.busy || this.updating.includes(row.id);
    }

    /** Opens the editor for `row`, or for a new row when `null`. */
    edit(row: AdminRow | null, trigger: HTMLElement | null): void {
        this.trigger = trigger;
        const fields = this.fields;
        this.editor = { row, fields: row && this.editFields ? this.editFields(row, fields) : fields };
    }

    /** Persists an editor submission: updates the edited row or creates a new one. */
    async save(values: Record<string, unknown>): Promise<void> {
        await this.persist(values, this.editor?.row);
        await this.afterWrite();
    }

    /** Saves `changes` merged into the existing row, like an editor submission that touched only those fields. */
    async update(row: AdminRow, changes: Record<string, unknown>): Promise<void> {
        if (this.locked(row)) return;
        this.updating = [...this.updating, row.id];
        this.error = '';
        try {
            const fields = this.fields;
            const prepared = prepareValues(fields, { ...createDraft(fields, row), ...changes });
            if (Object.keys(prepared.errors).length) throw new Error(this.__('admin.errors.invalid_value'));
            await this.persist(prepared.values, row);
            await this.afterWrite();
        } catch (failure) {
            this.error = this.message(failure, 'admin.errors.save');
        } finally {
            this.updating = this.updating.filter((id) => id !== row.id);
        }
    }

    /** Asks before deleting `row`, or resetting it in resettable sections. */
    remove(row: AdminRow, trigger: HTMLElement | null): void {
        const remove = this.operations.remove;
        if (!remove) return;
        this.trigger = trigger;
        this.confirmation = {
            title: this.__(this.resettable ? 'admin.reset' : 'admin.delete'),
            description: this.__('admin.delete_description', { name: rowName(row) }),
            run: async () => {
                await remove(row);
                if (this.rows.length <= 1) {
                    const { pagination } = this;
                    this.pagination = { ...pagination, pageIndex: Math.max(0, pagination.pageIndex - 1) };
                }
                await this.afterWrite(this.resettable ? 'admin.reset_done' : 'admin.deleted');
            }
        };
    }

    /** Computes actions using the current row and permissions, retaining each action's result type. */
    rowActions(row: AdminRow): AdminAction[] {
        const actions = this.operations.rowActions?.(row);
        const items: AdminAction[] = [];
        for (const id in actions) {
            const action = actions[id];
            if (!action) continue;
            items.push({
                id,
                confirm: action.confirm,
                destructive: action.destructive,
                run: async () => {
                    const trigger = this.trigger;
                    const response = await action.run();
                    if (action.dialog) this.results[id] = { id: row.id, response, trigger };
                }
            });
        }
        return items;
    }

    /** Runs an action, asking first when it requires confirmation. */
    action(item: AdminAction, trigger?: HTMLElement | null): void {
        this.trigger = trigger ?? null;
        if (item.confirm) {
            this.confirmation = {
                title: this.__('admin.actions.' + item.id),
                description: this.__('admin.action_confirmation'),
                run: () => this.run(item)
            };
        } else {
            void this.run(item);
        }
    }

    /** Runs the pending confirmation; failures land in `error` instead of the caller. */
    async confirm(): Promise<void> {
        const pending = this.confirmation;
        if (!pending) return;
        try {
            await pending.run();
        } catch (failure) {
            this.error = this.message(failure, 'admin.errors.save');
        }
    }

    closeResult(id: keyof Results): void {
        if (!this.busy) delete this.results[id];
    }

    restoreFocus(trigger = this.trigger): HTMLElement | null {
        return trigger?.isConnected ? trigger : this.focusFallback();
    }

    private async run(item: AdminAction): Promise<void> {
        if (this.busy) return;
        this.busy = true;
        this.error = '';
        try {
            await item.run();
            this.notice = this.__('admin.action_done');
            await this.load();
        } catch (failure) {
            this.error = this.message(failure, 'admin.errors.save');
        } finally {
            this.busy = false;
        }
    }

    private async persist(values: Record<string, unknown>, row?: AdminRow | null): Promise<void> {
        if (!this.operations.save) throw new Error(this.__('admin.errors.save'));
        await this.operations.save(values, row ?? null);
    }

    /** A successful write stays successful even if refreshing another cache fails. */
    private async afterWrite(notice = 'admin.saved'): Promise<void> {
        this.notice = this.__(notice);
        try {
            await this.operations.refresh?.();
        } catch {
            this.notice = this.__('admin.saved_refresh');
        }
        await this.load();
    }

    private message(failure: unknown, fallback: string): string {
        return failure instanceof Error ? failure.message : this.__(fallback);
    }
}

/**
 * Creates the {@link AdminWorkspace} of a section page, reads once on mount
 * and aborts on unmount. Call it once in the page's script:
 *
 *     const workspace = useAdminWorkspace(columns, (signal, query) =>
 *         app.restApi.getResourceCollection('admin-providers', { query, signal })
 *     );
 */
export function useAdminWorkspace<Results extends Record<string, unknown> = {}>(
    columns: AdminColumn[],
    read: AdminReader,
    options: AdminWorkspaceOptions<Results> = {}
): AdminWorkspace<Results> {
    const workspace = new AdminWorkspace<Results>(useTranslator().__, columns, read, options);
    onMount(() => {
        void workspace.load();
        return () => workspace.dispose();
    });
    return workspace;
}
