export const DEFAULT_LIST_PAGE_SIZE = 50
export const MAX_LIST_PAGE_SIZE = 200

export type ListFilterValue = string | readonly string[]

export type ListQuery = {
  q: string
  page: number
  page_size: number
  sort: string
  filter: Readonly<Record<string, ListFilterValue>>
}

export type ListMeta = {
  page: number
  page_size: number
  total: number
}

export type ListResponse<T> = {
  data: T[]
  meta: ListMeta
  [key: string]: unknown
}

export type ListFilterOption = {
  value: string
  labelKey: string
  label?: string
}

export type ListFilterDefinition = {
  key: string
  labelKey: string
  type?: 'text' | 'select'
  multiple?: boolean
  searchable?: boolean
  options?: readonly ListFilterOption[]
}

export type ListSortOption = {
  value: string
  labelKey: string
}

export type ListColumnFormat =
  'text' | 'date' | 'datetime' | 'time' | 'quantity' | 'percentage' | 'money'

export type ListColumn<T = Record<string, unknown>> = {
  key: string
  labelKey: string
  sortable?: boolean
  sortField?: string
  format?: ListColumnFormat
  currencySymbol?: string
  align?: 'start' | 'center' | 'end'
  value?: (row: T) => unknown
}

export function createListQuery(input: Partial<ListQuery> = {}): ListQuery {
  const page = Number.isInteger(input.page) && (input.page ?? 0) > 0 ? input.page! : 1
  const requestedPageSize = input.page_size ?? DEFAULT_LIST_PAGE_SIZE
  const page_size = Math.min(
    MAX_LIST_PAGE_SIZE,
    Math.max(1, Number.isInteger(requestedPageSize) ? requestedPageSize : DEFAULT_LIST_PAGE_SIZE),
  )
  const filter: Record<string, ListFilterValue> = {}
  for (const [key, value] of Object.entries(input.filter ?? {})) {
    if (!key || value === undefined) continue
    if (Array.isArray(value)) {
      const values = value.filter(
        (item): item is string => typeof item === 'string' && item.length > 0,
      )
      if (values.length > 0) filter[key] = [...values]
    } else if (typeof value === 'string' && value.length > 0) {
      filter[key] = value
    }
  }
  return {
    q: typeof input.q === 'string' ? input.q.trim() : '',
    page,
    page_size,
    sort: typeof input.sort === 'string' ? input.sort : '',
    filter,
  }
}

export function cloneListQuery(query: ListQuery): ListQuery {
  return createListQuery({
    ...query,
    filter: Object.fromEntries(
      Object.entries(query.filter).map(([key, value]) => [
        key,
        Array.isArray(value) ? [...value] : value,
      ]),
    ),
  })
}

export function serializeListQuery(query: ListQuery): string {
  const params = new URLSearchParams()
  if (query.q) params.set('q', query.q)
  params.set('page', String(query.page))
  params.set('page_size', String(query.page_size))
  if (query.sort) params.set('sort', query.sort)
  for (const key of Object.keys(query.filter).sort()) {
    const value = query.filter[key]
    if (Array.isArray(value)) {
      for (const item of [...value].sort()) params.append(`filter[${key}][]`, item)
    } else if (typeof value === 'string' && value) {
      params.append(`filter[${key}]`, value)
    }
  }
  return params.toString()
}

export function hasActiveListFilters(query: ListQuery): boolean {
  return query.q.length > 0 || Object.keys(query.filter).length > 0
}

export function resetListQuery(query: ListQuery): ListQuery {
  return createListQuery({ page_size: query.page_size })
}
