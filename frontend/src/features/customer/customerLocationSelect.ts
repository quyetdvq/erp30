/** Customer geographic dropdown adapters shared by profile and Contact modals. */
export type GeographicRow = {
  id: string | number
  code?: string | null
  name: string
  name_full?: string | null
  province_id?: string | number | null
}

export type GeographicOption = { value: string; label: string }

export function geographicOption(row: GeographicRow): GeographicOption {
  const name = row.name_full?.trim() || row.name.trim()
  const code = row.code?.trim()
  return { value: String(row.id), label: code ? `${code} - ${name}` : name }
}

export function singleLocationId(value: string | readonly string[]): string {
  return typeof value === 'string' ? value : ''
}

/** Never display a ward returned for a different province or a different saved ward. */
export function wardMatchesSelection(
  row: GeographicRow,
  provinceId: string | null | undefined,
  wardId: string | null | undefined,
): boolean {
  return Boolean(provinceId && wardId) &&
    String(row.id) === String(wardId) &&
    String(row.province_id ?? '') === String(provinceId)
}
