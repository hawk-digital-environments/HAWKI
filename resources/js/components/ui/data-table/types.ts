import {createPaginatedRowModel, rowPaginationFeature, tableFeatures, type ColumnDef} from '@tanstack/svelte-table';

/**
 * The minimal table feature bundle: a paginated column model with custom cell
 * renderers. Sorting, filtering and row selection deliberately live outside —
 * add the matching features back here when a surface actually needs them.
 */
export const dataTableFeatures = tableFeatures({
    rowPaginationFeature,
    paginatedRowModel: createPaginatedRowModel()
});

export type DataTableColumn<T extends object> = ColumnDef<typeof dataTableFeatures, T>;
export type {PaginationState} from '@tanstack/svelte-table';
