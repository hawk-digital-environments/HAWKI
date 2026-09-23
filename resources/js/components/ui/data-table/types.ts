import {columnFilteringFeature, createFilteredRowModel, createPaginatedRowModel, createSortedRowModel, filterFn_weakEquals, rowPaginationFeature, rowSelectionFeature, rowSortingFeature, sortFns, tableFeatures, type ColumnDef} from '@tanstack/svelte-table';

export const dataTableFeatures = tableFeatures({
    rowSortingFeature,
    rowPaginationFeature,
    rowSelectionFeature,
    columnFilteringFeature,
    sortedRowModel: createSortedRowModel(),
    filteredRowModel: createFilteredRowModel(),
    paginatedRowModel: createPaginatedRowModel(),
    sortFns,
    // Value filters compare the string a select yields with the raw cell value, e.g. '3' with 3.
    filterFns: {weakEquals: filterFn_weakEquals}
});

export type DataTableColumn<T extends object> = ColumnDef<typeof dataTableFeatures, T>;
/** A column whose cells only take known values; offered as a select above the table. */
export interface DataTableFilter {
    column: string;
    label: string;
    options: {value: string; label: string}[];
}
export type {PaginationState, SortingState, RowSelectionState, ColumnFiltersState} from '@tanstack/svelte-table';
