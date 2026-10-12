import type { ProductGroup, ProductUom } from '@/api/generated/types.gen'

/** MySQLi may serialize TINYINT(1) as '0'/'1'; Boolean('0') is true. */
export function databaseFlagEnabled(value: unknown): boolean {
  return value === true || value === 1 || value === '1'
}

/** A group is selectable only if its entire ancestor chain is ACTIVE.
 * A historical choice remains visible but disabled; missing/cyclic parents fail closed.
 */
export function selectableProductGroupOptions(
  groups: readonly ProductGroup[],
  currentId = '',
): Array<{ value: string; label: string; disabled: boolean }> {
  const byId = new Map(groups.map((group) => [String(group.id), group]))
  const memo = new Map<string, boolean>()
  const checking = new Set<string>()
  const eligible = (id: string): boolean => {
    if (memo.has(id)) return memo.get(id)!
    const row = byId.get(id)
    if (!row || row.status !== 'ACTIVE' || checking.has(id)) return false
    checking.add(id)
    const parentId = row.parent_id == null ? '' : String(row.parent_id)
    const valid = !parentId || eligible(parentId)
    checking.delete(id)
    memo.set(id, valid)
    return valid
  }
  return groups
    .filter((row) => eligible(String(row.id)) || String(row.id) === currentId)
    .map((row) => ({
      value: String(row.id),
      label: `${row.name} (${row.code})`,
      disabled: !eligible(String(row.id)),
    }))
}

/** DRAFT/INACTIVE UOM cannot be referenced by newly chosen Product base UOM. */
export function selectableProductUomOptions(
  uoms: readonly ProductUom[],
  currentId = '',
): Array<{ value: string; label: string; disabled: boolean }> {
  return uoms
    .filter((row) => row.status === 'ACTIVE' || String(row.id) === currentId)
    .map((row) => ({
      value: String(row.id),
      label: `${row.name} (${row.code})`,
      disabled: row.status !== 'ACTIVE',
    }))
}

export function productUomName(
  uoms: readonly ProductUom[],
  id: string | number | null | undefined,
): string | undefined {
  return uoms.find((row) => String(row.id) === String(id))?.name
}

export function productRoleLabel(role: string, translate: (key: string) => string): string {
  const names: Record<string, string> = {
    SIMPLE: 'product.roleSimple',
    CHILD: 'product.roleChild',
    PARENT: 'product.roleParent',
    COMBO: 'product.roleCombo',
  }
  return names[role] ? translate(names[role]) : role
}

export function productUsageLabel(usage: string, translate: (key: string) => string): string {
  const names: Record<string, string> = {
    PURCHASE: 'product.usagePurchase',
    SALES: 'product.usageSales',
    BOTH: 'product.usageBoth',
  }
  return names[usage] ? translate(names[usage]) : usage
}
