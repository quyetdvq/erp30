import { describe, expect, it } from 'vitest'
import type { Product } from '@/api/generated/types.gen'
import { buildProductInlineRows, type ProductInlineRelation } from '../productInlineRows'

function product(id: number, role: Product['role'], parent: number | null = null): Product {
  return {
    id: String(id),
    product_code: `P${id}`,
    parent_product_id: parent === null ? null : String(parent),
    role,
    full_name: `Product ${id}`,
    product_type: 'STANDARD',
    status: 'ACTIVE',
    selectable: true,
    leaf_transactionable: role === 'CHILD' || role === 'SIMPLE',
  }
}
function relation(children: Product[], total: number, full = false): ProductInlineRelation {
  return { children, total, full, loading: false }
}
function dataIds(rows: ReturnType<typeof buildProductInlineRows>): string[] {
  return rows.filter((row) => row.kind === 'product').map((row) => String(row.product.id))
}

describe('Product parent/child inline table', () => {
  const parent = product(10, 'PARENT')
  const children = [11, 12, 13, 14].map((id) => product(id, 'CHILD', 10))

  it('shows at most two authorized children by default', () => {
    const rows = buildProductInlineRows([parent], { '10': relation(children.slice(0, 2), 4) }, new Set(), new Set())
    expect(dataIds(rows)).toEqual(['10', '11', '12'])
    expect(rows.filter((row) => row.kind === 'more')).toHaveLength(1)
  })

  it('shows all children when expanded and only two when reduced', () => {
    const relations = { '10': relation(children, 4, true) }
    expect(dataIds(buildProductInlineRows([parent], relations, new Set(), new Set(['10']))))
      .toEqual(['10', '11', '12', '13', '14'])
    expect(dataIds(buildProductInlineRows([parent], relations, new Set(), new Set())))
      .toEqual(['10', '11', '12'])
  })

  it('allows the parent to collapse all child rows', () => {
    expect(dataIds(buildProductInlineRows([parent], { '10': relation(children, 4, true) }, new Set(['10']), new Set(['10']))))
      .toEqual(['10'])
  })

  it('avoids duplicates when the primary API page also contains a child', () => {
    const result = buildProductInlineRows([children[0]!, parent], { '10': relation(children.slice(0, 2), 4) }, new Set(), new Set())
    expect(dataIds(result)).toEqual(['10', '11', '12'])
    expect(result[1]?.kind === 'product' && result[1].childOf).toBe('10')
  })

  it('keeps an orphaned page child visible when its parent is not on this page', () => {
    expect(dataIds(buildProductInlineRows([children[0]!], {}, new Set(), new Set())))
      .toEqual(['11'])
  })

  it('does not fabricate action permissions on preview-only products', () => {
    const result = buildProductInlineRows([parent], { '10': relation(children.slice(0, 2), 4) }, new Set(), new Set())
    expect(result[1]?.kind === 'product' && result[1].product.allowed_actions).toBeUndefined()
  })
})
