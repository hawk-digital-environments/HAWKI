import { onMount } from 'svelte';
import { ApiTransportError } from '$lib/kernel/api/errors.js';
import type { ColumnFiltersState, PaginationState, SortingState } from '$lib/components/ui/data-table/types.js';
import type { FetchCollectionQuery } from '$lib/kernel/api/buildQueryString.js';
import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
import type { JsonApiCollection } from '$lib/kernel/api/jsonApiEncoding.js';
import { adminContent, type AdminContent, type AdminField, type AdminRow } from './schemas/admin-content.js';
import { createDraft, prepareValues, rowName } from './form.js';

interface AdminColumnOptions {
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
 * A column backed by a row property. Its id must be a key of `Row`; the table
 * reads and formats that value unless the page supplies a cell snippet.
 */
export type AdminDataColumn<Row extends AdminRow> = AdminColumnOptions & { id: keyof Row & string };

/**
 * A display-only column rendered by a page-provided cell snippet. It has no
 * row value and must explicitly disable sorting; its id is one of the names the
 * page lists in the second type argument of {@link AdminColumn}.
 */
export type AdminDisplayColumn<Id extends string = never> = Pick<AdminColumnOptions, 'header'> & {
    id: Id;
    sortable: false;
};

/**
 * A column declared by an admin page: backed by a row property, or display-only
 * when its id is listed in `Display`.
 *
 *     const columns: AdminColumn<AdminModelResource, 'visible' | 'capabilities'>[] = [
 *         { id: 'label' },
 *         { id: 'visible', sortable: false }
 *     ];
 */
export type AdminColumn<Row extends AdminRow = AdminRow, Display extends string = never> =
    AdminDataColumn<Row> | AdminDisplayColumn<Display>;

/**
 * Reads the Workspace page content for the current table state. The page supplies
 * it, since every Workspace page has its own endpoint, and may
 * ignore `query` when the page filters through its own form instead.
 */
export type AdminReader<Row extends AdminRow> = (
    signal: AbortSignal,
    query: FetchCollectionQuery
) => Promise<JsonApiCollection<Row>>;

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

export interface AdminRecordSetOptions<Row extends AdminRow = AdminRow, Results extends Record<string, unknown> = {}> {
    /** Actions for the current row; dialog responses infer the record set result type. */
    rowActions?: (row: Row) => AdminRowActions<Results>;
    /** Persist an editor submission; a null row creates a record. */
    save?: (values: Record<string, unknown>, row: Row | null) => Promise<unknown>;
    /** Delete or reset the given record. */
    remove?: (row: Row) => Promise<unknown>;
    /** Refresh the page's dependent caches after a successful write. */
    refresh?: () => Promise<unknown>;
    /** Value filters that apply before the first read; users can change or clear them in the table. */
    columnFilters?: ColumnFiltersState;
    /** Rows come from config files; deleting a database row only resets it to the file value. */
    resettable?: boolean;
    /** Narrows or adjusts the editor fields for a given row before the editor opens. */
    editFields?: (row: Row, fields: AdminField[]) => AdminField[];
}

export interface AdminEditorState<Row extends AdminRow = AdminRow> {
    row: Row | null;
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
 * Everything one admin Workspace page works with: the columns the page
 * declared, the content of the last read, the table state that shapes each
 * read (search, pagination, sorting, value filters), the writes and actions of
 * the Workspace page and the dialogs they open (editor, confirmation, action result).
 *
 * A page creates it with {@link useAdminRecordSet} and passes it to
 * `AdminPage`, `AdminSearch`, `AdminTable` and `AdminResultDialog`; cells call
 * {@link update} directly.
 */
export class AdminRecordSet<
    Row extends AdminRow = AdminRow,
    ColumnId extends string = string,
    Results extends Record<string, unknown> = {}
> {
    readonly columns: ReadonlyArray<AdminColumn<Row, string> & { id: ColumnId }>;
    readonly resettable: boolean;

    content = $state<AdminContent<Row> | null>(null);
    /** `true` until the first response arrives, and during every visible read. */
    loading = $state(true);
    /** Message of the last failed read, write or action; cleared when the next one starts. */
    error = $state('');
    /** A denied operation remains visible through the coordinated refresh. */
    authorizationDenied = $state(false);
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
    editor = $state<AdminEditorState<Row> | null>(null);
    confirmation = $state<AdminConfirmation | null>(null);
    results = $state<{ [Id in keyof Results]?: AdminActionResult<Results[Id]> }>({});

    /** Element that opened the current dialog; focus returns there when the dialog closes. */
    trigger: HTMLElement | null = null;
    /** Focus target when the trigger left the DOM (e.g. a deleted row's menu); `AdminPage` points it at its toolbar. */
    focusFallback: () => HTMLElement | null = () => null;

    readonly rows = $derived<Row[]>(this.content?.rows ?? []);
    readonly fields = $derived<AdminField[]>(this.content?.fields ?? []);
    /** Row count on the server; `undefined` when the section returns everything at once. */
    readonly total = $derived(this.content?.total);
    readonly canCreate = $derived.by(() => !!this.operations.save && !!this.content?.create);
    readonly canEdit = $derived.by(() => !!this.operations.save && this.fields.length > 0);
    readonly canDelete = $derived.by(() => !!this.operations.remove && !!this.content?.delete);
    readonly dialogOpen = $derived(!!this.editor || !!this.confirmation || Object.values(this.results).some(Boolean));

    private readonly __: Translate;
    private readonly read: AdminReader<AdminRow>;
    private readonly editFields?: (row: Row, fields: AdminField[]) => AdminField[];
    private readonly operations: AdminRecordSetOptions<Row, Results>;
    private request?: AbortController;
    private suspended = $state(false);
    private generation = 0;

    /** Stop publishing reads while changed or unverified authorization is handled. */
    suspend(): void {
        this.suspended = true;
        this.generation++;
        this.request?.abort();
        this.loading = false;
    }

    /** Forget protected data and every pending action; a later grant starts fresh. */
    invalidate(): void {
        this.suspend();
        this.content = null;
        this.editor = null;
        this.confirmation = null;
        this.results = {};
        this.error = '';
        this.notice = '';
    }

    /**
     * Reload visibly after changed or unverified authorization, retaining a draft only if its target and metadata
     * are unchanged.
     * Returns whether a dialog closed, so the mounted page can restore focus after rendering.
     */
    async resume(): Promise<boolean> {
        const generation = this.generation;
        const previous = this.content;
        const editor = this.editor;
        let closed = !!this.confirmation;
        // A confirmation captures an action on the old authorization snapshot.
        this.confirmation = null;
        this.suspended = false;
        const loaded = await this.loadContent();
        if (generation !== this.generation) return false;
        if (!loaded) this.content = null;
        if (this.reconcileEditor(previous, editor, loaded)) closed = true;
        return closed;
    }

    /**
     * Re-read silently after a routine connection refresh and reconcile an open editor.
     * Pending confirmations stay open. Returns whether the editor closed.
     */
    async revalidate(): Promise<boolean> {
        if (this.suspended) return false;
        const generation = this.generation;
        const previous = this.content;
        const editor = this.editor;
        const loaded = await this.loadContent({ silent: true });
        if (generation !== this.generation || !loaded) return false;
        return this.reconcileEditor(previous, editor, true);
    }

    constructor(
        __: Translate,
        columns: ReadonlyArray<AdminColumn<Row, string> & { id: ColumnId }>,
        read: AdminReader<AdminRow>,
        options: AdminRecordSetOptions<Row, Results> = {}
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
                    sort: `${sort.desc ? '-' : ''}${this.sortKey(sort.id)}`
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
        await this.loadContent();
    }

    private async loadContent(options: { silent?: boolean } = {}): Promise<boolean> {
        if (this.suspended) return false;
        this.request?.abort();
        const controller = new AbortController();
        this.request = controller;
        if (!options.silent || !this.content) this.loading = true;
        this.error = '';
        try {
            const content = await this.read(controller.signal, this.query);
            if (!controller.signal.aborted) {
                this.content = adminContent(content) as AdminContent<Row>;
                return true;
            }
        } catch (failure) {
            if (this.isDenied(failure)) this.authorizationDenied = true;
            else if (!controller.signal.aborted) this.error = this.message(failure, 'admin.errors.load');
        } finally {
            if (!controller.signal.aborted) this.loading = false;
        }
        return false;
    }

    private reconcileEditor(
        previous: AdminContent<Row> | null,
        editor: AdminEditorState<Row> | null,
        verified: boolean
    ): boolean {
        if (!editor || this.editor !== editor) return false;
        const current = this.content;
        const row = editor.row ? current?.rows.find((row) => row.id === editor.row?.id) : null;
        const fields = row && this.editFields ? this.editFields(row, current?.fields ?? []) : (current?.fields ?? []);
        const metadata = (content: AdminContent<Row> | null, fields: AdminField[]) =>
            JSON.stringify({
                fields,
            });
        const targetUnchanged =
            editor.row ?
                !!row && !!editor.row._version && row._version === editor.row._version
            :   !!current?.create && !!this.operations.save;
        if (!verified || !targetUnchanged || metadata(previous, editor.fields) !== metadata(current, fields)) {
            this.editor = null;
            this.notice = this.__(verified ? 'admin.editor_refreshed' : 'admin.editor_unverified');
            return true;
        }
        if (row) {
            // Keep the editor instance (and its local form draft), but use the current row reference.
            editor.row = row;
        }
        return false;
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
        this.invalidate();
    }

    /** `row` must not be changed right now: the page is busy or the row itself is being saved. */
    locked(row: Row): boolean {
        return this.suspended || this.busy || this.updating.includes(row.id);
    }

    /** Opens the editor for `row`, or for a new row when `null`. */
    edit(row: Row | null, trigger: HTMLElement | null): void {
        if (this.suspended) return;
        this.trigger = trigger;
        const fields = this.fields;
        this.editor = { row, fields: row && this.editFields ? this.editFields(row, fields) : fields };
    }

    /** Persists an editor submission: updates the edited row or creates a new one. */
    async save(values: Record<string, unknown>): Promise<void> {
        const generation = this.generation;
        this.authorizationDenied = false;
        try {
            await this.persist(values, this.editor?.row);
            if (generation === this.generation) {
                this.editor = null;
                await this.afterWrite();
            }
        } catch (failure) {
            if (this.isDenied(failure)) this.authorizationDenied = true;
            throw failure;
        }
    }

    /** Saves `changes` merged into the existing row, like an editor submission that touched only those fields. */
    async update(row: Row, changes: Record<string, unknown>): Promise<void> {
        if (this.locked(row)) return;
        const generation = this.generation;
        this.updating = [...this.updating, row.id];
        this.error = '';
        try {
            const fields = this.fields;
            const prepared = prepareValues(fields, { ...createDraft(fields, row), ...changes });
            if (Object.keys(prepared.errors).length) throw new Error(this.__('admin.errors.invalid_value'));
            await this.persist(prepared.values, row);
            if (generation === this.generation) await this.afterWrite();
        } catch (failure) {
            if (this.isDenied(failure)) this.authorizationDenied = true;
            else if (generation === this.generation) this.error = this.message(failure, 'admin.errors.save');
        } finally {
            this.updating = this.updating.filter((id) => id !== row.id);
        }
    }

    /** Asks before deleting `row`, or resetting it in resettable sections. */
    remove(row: Row, trigger: HTMLElement | null): void {
        const remove = this.operations.remove;
        if (!remove || this.suspended) return;
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
    rowActions(row: Row): AdminAction[] {
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
                    const generation = this.generation;
                    const response = await action.run();
                    if (action.dialog && generation === this.generation && !this.suspended)
                        this.results[id] = { id: row.id, response, trigger };
                }
            });
        }
        return items;
    }

    /** Runs an action, asking first when it requires confirmation. */
    action(item: AdminAction, trigger?: HTMLElement | null): void {
        if (this.suspended) return;
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
        if (!pending || this.suspended) return;
        this.confirmation = null;
        try {
            await pending.run();
        } catch (failure) {
            if (this.isDenied(failure)) this.authorizationDenied = true;
            else this.error = this.message(failure, 'admin.errors.save');
        }
    }

    closeResult(id: keyof Results): void {
        if (!this.busy) delete this.results[id];
    }

    restoreFocus(trigger = this.trigger): HTMLElement | null {
        return trigger?.isConnected && !trigger.matches?.(':disabled, [aria-disabled="true"]') ?
                trigger
            :   this.focusFallback();
    }

    private async run(item: AdminAction): Promise<void> {
        if (this.busy || this.suspended) return;
        this.busy = true;
        this.error = '';
        const generation = this.generation;
        try {
            await item.run();
            if (generation === this.generation) await this.afterWrite('admin.action_done');
        } catch (failure) {
            if (this.isDenied(failure)) this.authorizationDenied = true;
            else if (generation === this.generation) this.error = this.message(failure, 'admin.errors.save');
        } finally {
            this.busy = false;
        }
    }

    private async persist(values: Record<string, unknown>, row?: Row | null): Promise<void> {
        if (this.suspended || !this.operations.save) throw new Error(this.__('admin.errors.save'));
        await this.operations.save(values, row ?? null);
    }

    /**
     * Refreshes the dependent caches and re-reads the table after a save, removal or action.
     * A successful write stays successful even if refreshing another cache fails.
     */
    private async afterWrite(notice = 'admin.saved'): Promise<void> {
        this.notice = this.__(notice);
        try {
            await this.operations.refresh?.();
        } catch {
            this.notice = this.__('admin.saved_refresh');
        }
        await this.load();
    }

    private isDenied(failure: unknown): boolean {
        return failure instanceof ApiTransportError && failure.status === 403;
    }

    private message(failure: unknown, fallback: string): string {
        return failure instanceof Error ? failure.message : this.__(fallback);
    }

    private sortKey(id: string): string {
        const column = this.columns.find((item) => item.id === id);
        return column && 'sortKey' in column && column.sortKey ? column.sortKey : id;
    }
}

/**
 * Creates the {@link AdminRecordSet} of a Workspace page, reads once on mount
 * and aborts on unmount. The row type is the resource type the reader
 * returns, so registering the section's resource schema types the whole page.
 * Call it once in the page's script:
 *
 *     const columns: AdminColumn<AdminProviderResource>[] = [{ id: 'name' }, { id: 'active', format: 'boolean' }];
 *     const records = useAdminRecordSet(columns, (signal, query) =>
 *         app.restApi.getResourceCollection('admin-providers', { query, signal })
 *     );
 */
export function useAdminRecordSet<
    Row extends AdminRow,
    const Columns extends readonly AdminColumn<NoInfer<Row>, string>[],
    Results extends Record<string, unknown> = {}
>(
    columns: Columns,
    read: AdminReader<Row>,
    options: AdminRecordSetOptions<Row, Results> = {}
): AdminRecordSet<Row, Columns[number]['id'], Results> {
    const records = new AdminRecordSet<Row, Columns[number]['id'], Results>(
        useTranslator().__,
        columns as ReadonlyArray<AdminColumn<Row, string> & { id: Columns[number]['id'] }>,
        read,
        options
    );
    onMount(() => {
        void records.load();
        return () => records.dispose();
    });
    return records;
}
