/**
 * Flatten the groups loaded on the current page without exposing children of
 * collapsed parents as unrelated top-level rows.
 *
 * The caller supplies sibling lists in the desired order. Rows whose parent is
 * not present on this page remain provisional roots (the API is paginated).
 */
export type ProductGroupTreeSource = {
  id: string | number
  parent_id?: string | number | null
}

export type VisibleProductGroup<T extends ProductGroupTreeSource> = T & {
  depth: number
  hasChildren: boolean
  expanded: boolean
}

export function buildVisibleProductGroupTree<T extends ProductGroupTreeSource>(
  rows: readonly T[],
  byParent: ReadonlyMap<string, readonly T[]>,
  expanded: ReadonlySet<string>,
  sortUnlinked?: (first: T, second: T) => number,
): VisibleProductGroup<T>[] {
  const idsOnPage = new Set(rows.map((row) => String(row.id)))
  const roots = byParent.get('ROOT') ?? []
  const unlinkedRoots = rows.filter(
    (row) => Boolean(row.parent_id) && !idsOnPage.has(String(row.parent_id)),
  )
  if (sortUnlinked) unlinkedRoots.sort(sortUnlinked)

  const connected = new Set<string>()
  const rendered = new Set<string>()
  const visible: VisibleProductGroup<T>[] = []

  // Structural reachability must ignore expansion. Otherwise descendants of a
  // collapsed node would be mistaken for unlinked page rows in the fallback.
  function markConnected(root: T): void {
    const pending = [root]
    while (pending.length > 0) {
      const current = pending.pop()
      if (!current) continue
      const id = String(current.id)
      if (connected.has(id)) continue
      connected.add(id)
      pending.push(...(byParent.get(id) ?? []))
    }
  }

  function render(node: T, depth: number): void {
    const id = String(node.id)
    if (rendered.has(id)) return
    rendered.add(id)
    const children = byParent.get(id) ?? []
    const isExpanded = expanded.has(id)
    visible.push({ ...node, depth, hasChildren: children.length > 0, expanded: isExpanded })
    if (isExpanded) {
      for (const child of children) render(child, depth + 1)
    }
  }

  for (const root of [...roots, ...unlinkedRoots]) {
    markConnected(root)
    render(root, 0)
  }

  // Valid hierarchies are already covered. This fallback keeps corrupted or
  // cyclic disconnected data inspectable, without reviving collapsed children.
  for (const row of rows) {
    if (connected.has(String(row.id))) continue
    markConnected(row)
    render(row, 0)
  }

  return visible
}
