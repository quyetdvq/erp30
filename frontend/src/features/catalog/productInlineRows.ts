import type { Product } from '@/api/generated/types.gen'

/**
 * Additional rows for the Product table. Pagination/total are still owned by
 * the authorized /products response, not by this view-only tree projection.
 */
export type ProductInlineRelation = {
  children: readonly Product[]
  total: number
  full: boolean
  loading: boolean
  error?: boolean
}

export type ProductInlineRow =
  | { kind: 'product'; key: string; product: Product; childOf?: string }
  | {
      kind: 'more'
      key: string
      parentId: string
      total: number
      expanded: boolean
      loading: boolean
      error: boolean
    }

const PREVIEW_CHILDREN = 2

/**
 * Each parent shows two authorized preview children by default. Expanded
 * children are read from the independently authorized parent-filtered list.
 * If a child is also on the current primary page, represent it only once,
 * nested under its visible parent. Orphans on this page remain normal rows.
 */
export function buildProductInlineRows(
  page: readonly Product[],
  relations: Readonly<Record<string, ProductInlineRelation>>,
  collapsed: ReadonlySet<string>,
  showAll: ReadonlySet<string>,
): ProductInlineRow[] {
  const pageById = new Map(page.map((product) => [String(product.id), product]))
  const parentIds = new Set(page.filter((product) => product.role === 'PARENT').map((row) => String(row.id)))
  const result: ProductInlineRow[] = []

  for (const product of page) {
    const id = String(product.id)
    const parentId = product.parent_product_id == null ? '' : String(product.parent_product_id)
    const parentRelation = relations[parentId]
    if (
      product.role === 'CHILD' &&
      parentIds.has(parentId) &&
      parentRelation &&
      !parentRelation.loading &&
      parentRelation.total > 0
    ) {
      // This page row is represented under its visible parent, possibly behind
      // that parent's "view more" control. Do not render it twice.
      continue
    }

    result.push({ kind: 'product', key: `page:${id}`, product })
    if (product.role !== 'PARENT' || collapsed.has(id)) continue
    const relation = relations[id]
    if (!relation || relation.total === 0) continue

    const children = showAll.has(id) && relation.full
      ? relation.children
      : relation.children.slice(0, PREVIEW_CHILDREN)
    const seen = new Set<string>()
    for (const child of children) {
      const childId = String(child.id)
      if (String(child.parent_product_id) !== id || seen.has(childId)) continue
      seen.add(childId)
      result.push({
        kind: 'product',
        key: `child:${id}:${childId}`,
        // When expanded, use the fresh parent-filtered PBAC result. Preview
        // rows may borrow same-page hints, but never overwrite newer hints.
        product: showAll.has(id) && relation.full ? child : pageById.get(childId) ?? child,
        childOf: id,
      })
    }

    if (relation.total > PREVIEW_CHILDREN) {
      result.push({
        kind: 'more',
        key: `more:${id}`,
        parentId: id,
        total: relation.total,
        expanded: showAll.has(id) && relation.full,
        loading: relation.loading,
        error: relation.error === true,
      })
    }
  }
  return result
}
