<script lang="ts" generics="T extends {id: string}">
    import {createTable, FlexRender, functionalUpdate} from '@tanstack/svelte-table';
    import type {Snippet} from 'svelte';
    import {dataTableFeatures, type ColumnFiltersState, type DataTableColumn, type DataTableFilter, type PaginationState, type SortingState, type RowSelectionState} from './types.js';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuRadioGroup from '$lib/components/ui/dropdown-menu/DropdownMenuRadioGroup.svelte';
    import DropdownMenuRadioItem from '$lib/components/ui/dropdown-menu/DropdownMenuRadioItem.svelte';
    import FilterIcon from '$lib/components/ui/icons/iconset/FilterIcon.svelte';
    import ChevronRightIcon from '$lib/components/ui/icons/iconset/ChevronRightIcon.svelte';
    import ChevronDownIcon from '$lib/components/ui/icons/iconset/ChevronDownIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        data: T[];
        columns: DataTableColumn<T>[];
        caption: string;
        loading?: boolean;
        server?: boolean;
        total?: number;
        pagination?: PaginationState;
        sorting?: SortingState;
        /** Value filters offered behind an icon in the column header; `columnFilters` holds the chosen values by column id. */
        filters?: DataTableFilter[];
        columnFilters?: ColumnFiltersState;
        selection?: RowSelectionState;
        selectable?: boolean;
        rowLabel?: (row: T) => string;
        onChange?: () => void;
        actions?: Snippet<[T]>;
        /** Renders the cells of the given column ids with a snippet instead of the column definition's `cell`. */
        cells?: Partial<Record<string, Snippet<[T]>>>;
        /** Renders a full-width row below the row whose id equals `expanded`. */
        details?: Snippet<[T]>;
        /** The id of the single expanded row. */
        expanded?: string | null;
        /**
         * Continues a parent table's rows inside its details row: no frame, no header background and no
         * pagination as long as the rows fit on one page.
         */
        embedded?: boolean;
        empty?: string;
    }

    let {data, columns, caption, loading = false, server = false, total, pagination = $bindable({pageIndex: 0, pageSize: 25}), sorting = $bindable([]), filters = [], columnFilters = $bindable([]), selection = $bindable({}), selectable = false, rowLabel = row => row.id, onChange, actions, cells, details, expanded = $bindable(null), embedded = false, empty}: Props = $props();
    const {__} = useTranslator();
    const uid = $props.id();
    const columnCount = $derived(columns.length + Number(selectable) + Number(!!actions) + Number(!!details));
    const table = createTable({
        features: dataTableFeatures,
        get data() { return data; },
        get columns() { return columns; },
        getRowId: row => row.id,
        get manualSorting() { return server; },
        get manualPagination() { return server; },
        get manualFiltering() { return server; },
        defaultColumn: {filterFn: 'weakEquals'},
        get rowCount() { return total ?? data.length; },
        get enableRowSelection() { return selectable; },
        autoResetPageIndex: false,
        state: {
            get sorting() { return sorting; },
            get pagination() { return pagination; },
            get columnFilters() { return columnFilters; },
            get rowSelection() { return selection; }
        },
        onSortingChange: updater => {
            sorting = functionalUpdate(updater, sorting);
            pagination = {...pagination, pageIndex: 0};
            onChange?.();
        },
        onColumnFiltersChange: updater => {
            columnFilters = functionalUpdate(updater, columnFilters);
            pagination = {...pagination, pageIndex: 0};
            onChange?.();
        },
        onPaginationChange: updater => {
            pagination = functionalUpdate(updater, pagination);
            onChange?.();
        },
        onRowSelectionChange: updater => { selection = functionalUpdate(updater, selection); }
    });
</script>

<div class="data-table" class:embedded aria-busy={loading}>
    <!-- svelte-ignore a11y_no_noninteractive_tabindex (keyboard users must be able to scroll wide tables) -->
    <div class="table-scroll" tabindex="0" role="region" aria-label={caption}>
        <table>
            <caption class="u-sr-only">{caption}</caption>
            <thead>
                {#each table.getHeaderGroups() as group (group.id)}
                    <tr>
                        {#if selectable}
                            <th scope="col"><input type="checkbox" checked={table.getIsAllPageRowsSelected()} indeterminate={table.getIsSomePageRowsSelected()} onchange={table.getToggleAllPageRowsSelectedHandler()} aria-label={__('admin.table.select_page')} /></th>
                        {/if}
                        {#if details}
                            <th scope="col"><span class="u-sr-only">{__('admin.table.details')}</span></th>
                        {/if}
                        {#each group.headers as header (header.id)}
                            <th scope="col" colspan={header.colSpan} aria-sort={header.column.getIsSorted() === 'asc' ? 'ascending' : header.column.getIsSorted() === 'desc' ? 'descending' : undefined}>
                                {#if !header.isPlaceholder}
                                    {@const filter = filters.find(item => item.column === header.column.id)}
                                    {@const selected = filter ? columnFilters.find(item => item.id === filter.column)?.value : undefined}
                                    {@const current = selected === undefined ? undefined : filter?.options.find(option => option.value === String(selected))}
                                    <span class="header-cell">
                                        {#if header.column.getCanSort()}
                                            <button type="button" class="sort" onclick={header.column.getToggleSortingHandler()} disabled={loading}>
                                                <FlexRender {header}/><span aria-hidden="true">{header.column.getIsSorted() === 'asc' ? '↑' : header.column.getIsSorted() === 'desc' ? '↓' : '↕'}</span>
                                            </button>
                                        {:else}<FlexRender {header}/>{/if}
                                        {#if filter}
                                            <DropdownMenu title={filter.label} disabled={loading}>
                                                {#snippet trigger({props})}
                                                    <Button {...props} variant="ghost" size="sm" iconLeft={FilterIcon} highlight={!!current} disabled={loading}
                                                        aria-label={current ? __('admin.table.filter_active', {name: filter.label, value: current.label}) : __('admin.table.filter', {name: filter.label})}/>
                                                {/snippet}
                                                <DropdownMenuRadioGroup value={current?.value ?? ''} onValueChange={(value: string) => header.column.setFilterValue(value || undefined)}>
                                                    <DropdownMenuRadioItem value="" indicator="check">{__('admin.table.filter_all')}</DropdownMenuRadioItem>
                                                    {#each filter.options as option (option.value)}
                                                        <DropdownMenuRadioItem value={option.value} indicator="check">{option.label}</DropdownMenuRadioItem>
                                                    {/each}
                                                </DropdownMenuRadioGroup>
                                            </DropdownMenu>
                                        {/if}
                                    </span>
                                {/if}
                            </th>
                        {/each}
                        {#if actions}<th scope="col">{__('admin.table.actions')}</th>{/if}
                    </tr>
                {/each}
            </thead>
            <tbody>
                {#each table.getRowModel().rows as row (row.id)}
                    {@const detailsId = `${uid}-details-${row.id}`}
                    <tr aria-selected={selectable ? row.getIsSelected() : undefined}>
                        {#if selectable}<td><input type="checkbox" checked={row.getIsSelected()} onchange={row.getToggleSelectedHandler()} aria-label={__('admin.table.select_row', {name: rowLabel(row.original)})}/></td>{/if}
                        {#if details}
                            <td class="row-toggle">
                                <Button variant="ghost" size="sm" iconLeft={expanded === row.id ? ChevronDownIcon : ChevronRightIcon} disabled={loading}
                                    aria-expanded={expanded === row.id} aria-controls={expanded === row.id ? detailsId : undefined}
                                    aria-label={__(expanded === row.id ? 'admin.table.collapse' : 'admin.table.expand', {name: rowLabel(row.original)})}
                                    onclick={() => { expanded = expanded === row.id ? null : row.id; }}/>
                            </td>
                        {/if}
                        {#each row.getAllCells() as cell (cell.id)}{@const custom = cells?.[cell.column.id]}<td>{#if custom}{@render custom(row.original)}{:else}<FlexRender {cell}/>{/if}</td>{/each}
                        {#if actions}<td class="row-actions">{@render actions(row.original)}</td>{/if}
                    </tr>
                    {#if details && expanded === row.id}
                        <tr class="details"><td id={detailsId} colspan={columnCount}>{@render details(row.original)}</td></tr>
                    {/if}
                {:else}
                    <tr><td class="empty" colspan={columnCount}>{loading ? __('ui.loading') : empty ?? __('admin.table.empty')}</td></tr>
                {/each}
            </tbody>
        </table>
    </div>
    <!-- An embedded table keeps its pagination out of the way, but rows beyond the first page must stay reachable. -->
    {#if !embedded || table.getPageCount() > 1}<div class="pagination">
        <p role="status">{__('admin.table.page', {page: String(pagination.pageIndex + 1), pages: String(Math.max(1, table.getPageCount())), total: String(total ?? data.length)})}</p>
        <div class="page-size"><span>{__('admin.table.page_size')}</span>
            <SingleSelect value={String(pagination.pageSize)} onValueChange={(value: string) => table.setPageSize(Number(value))} disabled={loading}
                triggerProps={{'aria-label': __('admin.table.page_size')}} items={[10, 25, 50, 100].map(size => ({value: String(size), label: String(size)}))}/>
        </div>
        <Button variant="stroke" size="sm" disabled={loading || !table.getCanPreviousPage()} onclick={() => table.previousPage()}>{__('admin.table.previous')}</Button>
        <Button variant="stroke" size="sm" disabled={loading || !table.getCanNextPage()} onclick={() => table.nextPage()}>{__('admin.table.next')}</Button>
    </div>{/if}
</div>

<style>
    .data-table { border: var(--border); border-radius: var(--corner-lg); overflow: hidden; background: var(--color-surface-raised); }
    .table-scroll { overflow: auto; }
    table { width: 100%; border-collapse: collapse; text-align: start; font-size: var(--font-size-sm); }
    th { background: var(--color-surface); font-weight: 600; white-space: nowrap; }
    th, td { padding: var(--space-3) var(--space-4); border-bottom: var(--border); text-align: start; }
    td { max-width: 24rem; overflow-wrap: anywhere; vertical-align: middle; }
    .header-cell { display: inline-flex; align-items: center; gap: var(--space-1); }
    th .sort { display: flex; align-items: center; gap: var(--space-2); font: inherit; color: inherit; background: none; border: 0; padding: 0; cursor: pointer; }
    tr[aria-selected="true"] { background: var(--color-active-surface); }
    tbody tr:focus-within { background: var(--color-active-surface); }
    .row-actions { white-space: nowrap; }
    .row-toggle { width: 1px; padding-inline-end: 0; }
    .details > td { padding: 0 0 0 var(--space-12); }
    .embedded { border: 0; border-radius: 0; background: transparent; }
    .embedded th { background: transparent; font-size: var(--font-size-xs); color: var(--color-text-muted); }
    .embedded tbody tr:last-child td { border-bottom: 0; }
    .embedded .pagination { padding-inline: 0; font-size: var(--font-size-xs); }
    .empty { padding: var(--space-8); text-align: center; }
    .pagination { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: var(--space-3); padding: var(--space-3) var(--space-4); }
    .pagination p { margin: 0 auto 0 0; font-size: var(--font-size-sm); }
    .page-size { display: flex; align-items: center; gap: var(--space-2); font-size: var(--font-size-sm); }
</style>
