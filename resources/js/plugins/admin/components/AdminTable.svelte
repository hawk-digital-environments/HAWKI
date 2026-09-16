<!--
  @component Table of an admin section. Renders the columns the page declared
  in its record set with the rows it read, binds the record set's table state
  (pagination, sorting, value filters) and offers edit, delete/reset and the
  record set's row actions in a row menu.
-->
<script
    lang="ts"
    generics="Row extends AdminRow, ColumnId extends string, Results extends Record<string, unknown>"
>
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import DataTable from '$lib/components/ui/data-table/DataTable.svelte';
    import type { DataTableColumn, DataTableFilter } from '$lib/components/ui/data-table/types.js';
    import type { Snippet } from 'svelte';
    import { adminActionIcons } from '../actionIcons.js';
    import { rowName } from '../form.js';
    import type { AdminField, AdminRow } from '../schemas/admin-content.js';
    import type { AdminColumn, AdminRecordSet } from '../recordSet.svelte.js';
    import AdminActionMenu, { type AdminMenuItem } from './AdminActionMenu.svelte';

    let {
        recordSet,
        caption,
        cells,
        rowMenuItems,
        editSystemRows = false
    }: {
        recordSet: AdminRecordSet<Row, ColumnId, Results>;
        caption: string;
        /** Replaces the cells of the given column ids with a snippet. */
        cells?: { [Id in ColumnId]?: Snippet<[Row]> };
        /** Extra row menu entries between "edit" and the row actions. */
        rowMenuItems?: (row: Row) => AdminMenuItem[];
        /** Offer "edit" for rows flagged `is_system` too. */
        editSystemRows?: boolean;
    } = $props();
    const { __ } = useTranslator();
    /** The server paginates, sorts and filters; every table state change reads again. */
    const server = $derived(recordSet.total !== undefined);
    const tableColumns = $derived<DataTableColumn<Row>[]>(
        recordSet.columns.map((column) => ({
            id: column.id,
            accessorFn: (row: Row) => (row as AdminRow)[column.id],
            header: column.header ?? __('admin.fields.' + column.id),
            enableSorting: column.sortable ?? true,
            cell: (info: { getValue: () => unknown }) => display(info.getValue(), column)
        }))
    );
    const tableFilters = $derived<DataTableFilter[]>(
        recordSet.columns
            .filter((column) => 'filter' in column && column.filter)
            .flatMap((column) => {
                const field = selectField(column.id);
                if (!field?.options.length) return [];
                return [
                    {
                        column: column.id,
                        label: column.header ?? __('admin.fields.' + column.id),
                        options: field.options.map((option) => ({ value: String(option.value), label: option.label }))
                    }
                ];
            })
    );
    const hasRowMenu = $derived(
        !!rowMenuItems ||
            recordSet.canEdit ||
            recordSet.canDelete ||
            recordSet.resettable ||
            recordSet.rows.some((row) => recordSet.rowActions(row).length > 0)
    );

    function selectField(key: string): AdminField | undefined {
        return recordSet.fields.find((field) => field.key === key && field.type === 'select');
    }

    function display(value: unknown, column: AdminColumn<Row, string>): string {
        if (value === null || value === undefined || value === '') return '—';
        // Numeric select options reference rows of another table; show their labels instead of the ids.
        const options = selectField(column.id)?.options ?? [];
        if (options.some((option) => typeof option.value === 'number')) {
            const option = options.find((option) => String(option.value) === String(value));
            if (option) return option.label;
        }
        if (typeof value === 'boolean' || ('format' in column && column.format === 'boolean'))
            return __(value ? 'admin.yes' : 'admin.no');
        if ('format' in column && column.format === 'enum' && typeof value === 'string')
            return __('admin.values.' + value);
        if (value === '[set]' || value === '[not set]')
            return __(value === '[set]' ? 'admin.secret_set' : 'admin.secret_unset');
        if (typeof value === 'object') return JSON.stringify(value);
        const text = String(value);
        return text.length > 180 ? text.slice(0, 180) + '…' : text;
    }

    function rowFlag(row: Row, key: 'is_system' | 'source'): unknown {
        return (row as AdminRow)[key];
    }

    function menuItems(row: Row): AdminMenuItem[] {
        const items: AdminMenuItem[] = [];
        if (recordSet.canEdit && (editSystemRows || !rowFlag(row, 'is_system')))
            items.push({
                label: __('admin.edit'),
                icon: adminActionIcons.edit,
                run: (target) => recordSet.edit(row, target)
            });
        items.push(...(rowMenuItems?.(row) ?? []));
        items.push(
            ...recordSet.rowActions(row).map((item) => ({
                label: __('admin.actions.' + item.id),
                icon: adminActionIcons[item.id],
                destructive: item.destructive,
                run: (target: HTMLButtonElement | null) => recordSet.action(item, target)
            }))
        );
        if (
            (recordSet.canDelete || (recordSet.resettable && rowFlag(row, 'source') === 'database')) &&
            !rowFlag(row, 'is_system')
        )
            items.push({
                label: __(recordSet.resettable ? 'admin.reset' : 'admin.delete'),
                icon: recordSet.resettable ? adminActionIcons.reset : adminActionIcons.delete,
                destructive: true,
                run: (target) => recordSet.remove(row, target)
            });
        return items;
    }
</script>

<DataTable
    data={recordSet.rows}
    columns={tableColumns}
    {caption}
    loading={recordSet.loading}
    {server}
    total={recordSet.total}
    bind:pagination={recordSet.pagination}
    bind:sorting={recordSet.sorting}
    bind:columnFilters={recordSet.columnFilters}
    filters={tableFilters}
    onChange={() => {
        if (server) void recordSet.load();
    }}
    actions={hasRowMenu ? renderRowMenu : undefined}
    {cells}
/>

{#snippet renderRowMenu(row: Row)}
    <AdminActionMenu
        compact
        label={__('admin.row_actions', { name: rowName(row) })}
        items={menuItems(row)}
        disabled={recordSet.locked(row)}
        dialogOpen={recordSet.dialogOpen}
    />
{/snippet}
