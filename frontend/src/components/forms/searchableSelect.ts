import type { SelectFilter } from 'naive-ui'

export function normalizeSearchText(value: unknown): string {
  return String(value ?? '')
    .trim()
    .toLocaleLowerCase()
}

export const searchableSelectFilter: SelectFilter = (pattern, option) => {
  const query = normalizeSearchText(pattern)
  if (!query) return true
  return normalizeSearchText(option.label).includes(query)
}
