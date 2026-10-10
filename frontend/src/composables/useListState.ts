import { computed, reactive, type ComputedRef, type Ref } from 'vue'

import { useQueryState, type QueryState } from '@/composables/useQueryState'

import {
  createListQuery,
  cloneListQuery,
  hasActiveListFilters,
  resetListQuery,
  serializeListQuery,
  type ListQuery,
  type ListResponse,
} from '@/components/lists/listTypes'

export type ListRequest<T> = (query: ListQuery) => Promise<ListResponse<T>>

export type ListState<T> = {
  query: Readonly<ListQuery>
  response: Readonly<Ref<ListResponse<T> | null>>
  rows: Readonly<ComputedRef<T[]>>
  total: Readonly<ComputedRef<number>>
  pageCount: Readonly<ComputedRef<number>>
  queryString: Readonly<ComputedRef<string>>
  hasActiveFilters: Readonly<ComputedRef<boolean>>
  isInitialLoading: Readonly<ComputedRef<boolean>>
  isBackgroundRefreshing: Readonly<ComputedRef<boolean>>
  isEmptyDataset: Readonly<ComputedRef<boolean>>
  isEmptyFilteredResult: Readonly<ComputedRef<boolean>>
  queryState: QueryState<ListResponse<T>>
  setSearch: (value: string) => void
  setFilters: (value: Record<string, string | readonly string[]>) => void
  setSort: (value: string) => void
  setPage: (value: number) => void
  setPageSize: (value: number) => void
  apply: () => Promise<ListResponse<T>>
  refresh: () => Promise<ListResponse<T>>
  reset: () => void
}

export function useListState<T>(
  request: ListRequest<T>,
  initialQuery: Partial<ListQuery> = {},
): ListState<T> {
  const query = reactive(createListQuery(initialQuery))
  const queryState = useQueryState<ListResponse<T>>({
    request: () => request(cloneListQuery(query)),
    isEmpty: (result) => result.data.length === 0,
  })
  // Single source of truth: useQueryState only commits the latest request.
  // A second response ref updated after await would resurrect stale data.
  const response = queryState.data
  const rows = computed(() => response.value?.data ?? [])
  const total = computed(() => response.value?.meta.total ?? 0)
  const pageCount = computed(() =>
    Math.max(1, Math.ceil(total.value / (response.value?.meta.page_size ?? query.page_size))),
  )
  const queryString = computed(() => serializeListQuery(query))
  const activeFilters = computed(() => hasActiveListFilters(query))
  const isInitialLoading = computed(() => queryState.isLoading.value && !queryState.hasData.value)
  const isBackgroundRefreshing = computed(() => queryState.isRefreshing.value)
  const isEmptyDataset = computed(
    () => queryState.isEmpty.value && !activeFilters.value && total.value === 0,
  )
  const isEmptyFilteredResult = computed(
    () => queryState.isEmpty.value && activeFilters.value && rows.value.length === 0,
  )

  function replaceQuery(next: ListQuery): void {
    query.q = next.q
    query.page = next.page
    query.page_size = next.page_size
    query.sort = next.sort
    query.filter = next.filter
  }

  function setSearch(value: string): void {
    query.q = value.trim()
    query.page = 1
  }

  function setFilters(value: Record<string, string | readonly string[]>): void {
    query.filter = createListQuery({ filter: value }).filter
    query.page = 1
  }

  function setSort(value: string): void {
    query.sort = value
    query.page = 1
  }

  function setPage(value: number): void {
    query.page = Math.max(1, Math.trunc(value))
  }

  function setPageSize(value: number): void {
    const next = createListQuery({ page_size: value })
    query.page_size = next.page_size
    query.page = 1
  }

  function apply(): Promise<ListResponse<T>> {
    return queryState.execute()
  }

  function refresh(): Promise<ListResponse<T>> {
    return queryState.refresh()
  }

  function reset(): void {
    replaceQuery(resetListQuery(query))
    queryState.reset()
  }

  return {
    query,
    response,
    rows,
    total,
    pageCount,
    queryString,
    hasActiveFilters: activeFilters,
    isInitialLoading,
    isBackgroundRefreshing,
    isEmptyDataset,
    isEmptyFilteredResult,
    queryState,
    setSearch,
    setFilters,
    setSort,
    setPage,
    setPageSize,
    apply,
    refresh,
    reset,
  }
}
