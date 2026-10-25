/**
 * Short-lived, display-only Product list catalog lookups.
 * No Product rows, relations, actions or PBAC results are cached here.
 * A caller must provide a session/user/permission/locale scope; missing scope
 * deliberately bypasses the cache. All writes go through backend authorization.
 */
export type ProductListCatalog = 'groups' | 'uoms'

export const PRODUCT_LIST_CATALOG_TTL_MS = 20_000

/** Keep cache partitioning deterministic without mutating the permission snapshot. */
export function productListCatalogScope(input: {
  userId: number | string | null | undefined
  sessionGeneration: number
  permissionState: string
  permissions: readonly string[]
  locale: string
}): string | null {
  if (input.userId === null || input.userId === undefined || input.permissionState !== 'ready') {
    return null
  }
  return JSON.stringify([
    input.sessionGeneration,
    String(input.userId),
    input.locale,
    [...input.permissions].sort(),
  ])
}

type Entry = {
  scope: string
  pending: boolean
  expiresAt: number
  promise: Promise<unknown[]>
}

const entries = new Map<ProductListCatalog, Entry>()

/** Single-flight, with a bounded success TTL and no negative/error caching. */
export function loadProductListCatalog<T>(
  kind: ProductListCatalog,
  scope: string | null,
  loader: () => Promise<T[]>,
): Promise<T[]> {
  if (!scope) return loader()
  const now = Date.now()
  const cached = entries.get(kind)
  if (cached?.scope === scope && (cached.pending || now < cached.expiresAt)) {
    return cached.promise as Promise<T[]>
  }
  const entry: Entry = {
    scope,
    pending: true,
    expiresAt: 0,
    promise: Promise.resolve([]),
  }
  entry.promise = Promise.resolve()
    .then(loader)
    .then((rows) => {
      if (entries.get(kind) === entry) {
        entry.pending = false
        entry.expiresAt = Date.now() + PRODUCT_LIST_CATALOG_TTL_MS
      }
      return rows
    })
    .catch((error: unknown) => {
      if (entries.get(kind) === entry) entries.delete(kind)
      throw error
    })
  entries.set(kind, entry)
  return entry.promise as Promise<T[]>
}

/** Called only AFTER successful Product Group/UOM mutations. */
export function invalidateProductListCatalog(kind?: ProductListCatalog): void {
  if (kind) entries.delete(kind)
  else entries.clear()
}
