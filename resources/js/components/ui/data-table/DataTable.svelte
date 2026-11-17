<!--
  @component Generic data table: a column model with custom cell renderers
  over a paginated row model. Supports client-side pagination or a server
  mode where the caller drives the reads (bind `pagination`, pass `total`).
  Sorting, filtering, selection and row actions are deliberately not part of
  this component — extend `types.ts` when a surface needs them.
-->
<script lang="ts" generics="T extends {id: string}">
    import {createTable, FlexRender, functionalUpdate} from '@tanstack/svelte-table';
    import type {Snippet} from 'svelte';
    import {dataTableFeatures, type DataTableColumn, type PaginationState} from './types.js';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        data: T[];
        columns: DataTableColumn<T>[];
        caption: string;
        loading?: boolean;
        /** The server paginates; every page change is the caller's read trigger. */
        server?: boolean;
        total?: number;
        pagination?: PaginationState;
        /** Renders the cells of the given column ids with a snippet instead of the column definition's `cell`. */
        cells?: Partial<Record<string, Snippet<[T]>>>;
        empty?: string;
    }

    let {data, columns, caption, loading = false, server = false, total, pagination = $bindable({pageIndex: 0, pageSize: 25}), cells, empty}: Props = $props();
    const {__} = useTranslator();
    const table = createTable({
        features: dataTableFeatures,
        get data() { return data; },
        get columns() { return columns; },
        getRowId: row => row.id,
        get manualPagination() { return server; },
        get rowCount() { return total ?? data.length; },
        autoResetPageIndex: false,
        state: {
            get pagination() { return pagination; }
        },
        onPaginationChange: updater => {
            pagination = functionalUpdate(updater, pagination);
        }
    });
</script>

<div class="data-table" aria-busy={loading}>
    <!-- svelte-ignore a11y_no_noninteractive_tabindex (keyboard users must be able to scroll wide tables) -->
    <div class="table-scroll" tabindex="0" role="region" aria-label={caption}>
        <table>
            <caption class="u-sr-only">{caption}</caption>
            <thead>
                {#each table.getHeaderGroups() as group (group.id)}
                    <tr>
                        {#each group.headers as header (header.id)}
                            <th scope="col" colspan={header.colSpan}>
                                {#if !header.isPlaceholder}<FlexRender {header}/>{/if}
                            </th>
                        {/each}
                    </tr>
                {/each}
            </thead>
            <tbody>
                {#each table.getRowModel().rows as row (row.id)}
                    <tr>
                        {#each row.getAllCells() as cell (cell.id)}{@const custom = cells?.[cell.column.id]}<td>{#if custom}{@render custom(row.original)}{:else}<FlexRender {cell}/>{/if}</td>{/each}
                    </tr>
                {:else}
                    <tr><td class="empty" colspan={columns.length}>{loading ? __('ui.loading') : empty ?? __('ui.table.empty')}</td></tr>
                {/each}
            </tbody>
        </table>
    </div>
    <div class="pagination">
        <p role="status">{__('ui.table.page', {page: String(pagination.pageIndex + 1), pages: String(Math.max(1, table.getPageCount())), total: String(total ?? data.length)})}</p>
        <div class="page-size"><span>{__('ui.table.page_size')}</span>
            <SingleSelect value={String(pagination.pageSize)} onValueChange={(value: string) => table.setPageSize(Number(value))} disabled={loading}
                triggerProps={{'aria-label': __('ui.table.page_size')}} items={[10, 25, 50, 100].map(size => ({value: String(size), label: String(size)}))}/>
        </div>
        <Button variant="stroke" size="sm" disabled={loading || !table.getCanPreviousPage()} onclick={() => table.previousPage()}>{__('ui.table.previous')}</Button>
        <Button variant="stroke" size="sm" disabled={loading || !table.getCanNextPage()} onclick={() => table.nextPage()}>{__('ui.table.next')}</Button>
    </div>
</div>

<style>
    .data-table { border: var(--border); border-radius: var(--corner-lg); overflow: hidden; background: var(--color-surface-raised); }
    .table-scroll { overflow: auto; }
    table { width: 100%; border-collapse: collapse; text-align: start; font-size: var(--font-size-sm); }
    th { background: var(--color-surface); font-weight: var(--font-weight-semibold); white-space: nowrap; }
    th, td { padding: var(--space-3) var(--space-4); border-bottom: var(--border); text-align: start; }
    td { max-width: 24rem; overflow-wrap: anywhere; vertical-align: middle; }
    tbody tr:focus-within { background: var(--color-active-surface); }
    .empty { padding: var(--space-8); text-align: center; }
    .pagination { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: var(--space-3); padding: var(--space-3) var(--space-4); }
    .pagination p { margin: 0 auto 0 0; font-size: var(--font-size-sm); }
    .page-size { display: flex; align-items: center; gap: var(--space-2); font-size: var(--font-size-sm); }
</style>
