import { type ReactNode, useState } from 'react';

import { cn } from '../utils/cn';
import { Input } from './Input';

export interface DataTableColumn<T> {
  key: string;
  header: string;
  sortable?: boolean;
  render?: (row: T) => ReactNode;
  /** Defaults to reading `row[key]` when `render` is omitted. */
  accessor?: (row: T) => ReactNode;
  className?: string;
}

export type SortDirection = 'asc' | 'desc';

export interface SortState {
  key: string;
  direction: SortDirection;
}

export interface PaginationMeta {
  currentPage: number;
  perPage: number;
  total: number;
  lastPage: number;
}

export interface DataTableProps<T> {
  columns: DataTableColumn<T>[];
  data: T[];
  getRowId: (row: T) => string | number;
  isLoading?: boolean;
  emptyMessage?: string;

  sort?: SortState | null;
  onSortChange?: (sort: SortState) => void;

  pagination?: PaginationMeta;
  onPageChange?: (page: number) => void;

  searchable?: boolean;
  searchPlaceholder?: string;
  onSearchChange?: (query: string) => void;

  className?: string;
}

/**
 * Presentation-only table — sorting, pagination, and search are all
 * "controlled" (the callbacks fire, the parent refetches from the API with
 * the new params). FitMirror's lists are server-paginated throughout
 * (ApiResponse::paginated, DOCUMENTATION.md §7.1), so a client-side-sort
 * DataTable would only ever be correct for the current page, which is a
 * worse default than being explicit about the round-trip.
 */
export function DataTable<T>({
  columns,
  data,
  getRowId,
  isLoading = false,
  emptyMessage = 'No records found.',
  sort,
  onSortChange,
  pagination,
  onPageChange,
  searchable = false,
  searchPlaceholder = 'Search…',
  onSearchChange,
  className,
}: DataTableProps<T>) {
  const [searchValue, setSearchValue] = useState('');

  function handleSort(column: DataTableColumn<T>) {
    if (!column.sortable || !onSortChange) return;

    const nextDirection: SortDirection =
      sort?.key === column.key && sort.direction === 'asc' ? 'desc' : 'asc';

    onSortChange({ key: column.key, direction: nextDirection });
  }

  function handleSearchChange(value: string) {
    setSearchValue(value);
    onSearchChange?.(value);
  }

  return (
    <div className={cn('flex flex-col gap-3', className)}>
      {searchable && (
        <Input
          value={searchValue}
          onChange={(event) => handleSearchChange(event.target.value)}
          placeholder={searchPlaceholder}
          className="max-w-xs"
        />
      )}

      <div className="overflow-x-auto rounded-lg border border-neutral-200">
        <table className="w-full text-left text-sm">
          <thead className="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500">
            <tr>
              {columns.map((column) => {
                const isSorted = sort?.key === column.key;

                return (
                  <th
                    key={column.key}
                    scope="col"
                    className={cn('px-4 py-3 font-medium', column.className)}
                  >
                    {column.sortable ? (
                      <button
                        type="button"
                        onClick={() => handleSort(column)}
                        className="inline-flex items-center gap-1 hover:text-neutral-700"
                        aria-sort={
                          isSorted
                            ? sort.direction === 'asc'
                              ? 'ascending'
                              : 'descending'
                            : 'none'
                        }
                      >
                        {column.header}
                        {isSorted && (
                          <span aria-hidden="true">{sort.direction === 'asc' ? '▲' : '▼'}</span>
                        )}
                      </button>
                    ) : (
                      column.header
                    )}
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {isLoading ? (
              <tr>
                <td colSpan={columns.length} className="px-4 py-8 text-center text-neutral-400">
                  Loading…
                </td>
              </tr>
            ) : data.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="px-4 py-8 text-center text-neutral-400">
                  {emptyMessage}
                </td>
              </tr>
            ) : (
              data.map((row) => (
                <tr key={getRowId(row)} className="hover:bg-neutral-50">
                  {columns.map((column) => (
                    <td
                      key={column.key}
                      className={cn('px-4 py-3 text-neutral-700', column.className)}
                    >
                      {column.render
                        ? column.render(row)
                        : (column.accessor?.(row) ??
                          String((row as Record<string, unknown>)[column.key] ?? ''))}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {pagination && onPageChange && pagination.lastPage > 1 && (
        <div className="flex items-center justify-between text-sm text-neutral-500">
          <span>
            Page {pagination.currentPage} of {pagination.lastPage} · {pagination.total} total
          </span>
          <div className="flex gap-2">
            <button
              type="button"
              disabled={pagination.currentPage <= 1}
              onClick={() => onPageChange(pagination.currentPage - 1)}
              className="rounded-md border border-neutral-300 px-3 py-1 disabled:cursor-not-allowed disabled:opacity-50"
            >
              Previous
            </button>
            <button
              type="button"
              disabled={pagination.currentPage >= pagination.lastPage}
              onClick={() => onPageChange(pagination.currentPage + 1)}
              className="rounded-md border border-neutral-300 px-3 py-1 disabled:cursor-not-allowed disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
