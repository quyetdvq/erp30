import type { ComboForm, ConversionForm, ProductEditorForm, SelectionOption } from './types'

/** Error keys, priority and eligibility checks are deliberately identical to V2.1. */
export function decimalIsPositive(value: string): boolean {
  return /^\d+(?:\.\d+)?$/.test(value) && !/^0+(?:\.0+)?$/.test(value)
}

export function comboValidationKey(items: readonly ComboForm[]): string | null {
  if (items.some((row) => !row.component_product_id)) return 'product.componentRequired'
  if (items.some((row) => !decimalIsPositive(row.quantity))) return 'product.quantityRequired'
  return null
}

export function conversionValidationKey(draft: ConversionForm, baseUomId: string): string | null {
  if (!draft.uom_id || draft.uom_id === baseUomId) return 'product.alternateUomRequired'
  if (!decimalIsPositive(draft.conversion_factor)) return 'product.factorRequired'
  if ((draft.usage_type === 'SALES' || draft.usage_type === 'BOTH') && !draft.price)
    return 'product.priceRequired'
  return null
}

export function productValidationKey(
  form: ProductEditorForm,
  flags: { isChild: boolean; isParentRole: boolean },
  groupOptions: readonly SelectionOption[],
  uomOptions: readonly SelectionOption[],
): string | null {
  if (flags.isChild && !form.parent_product_id) return 'product.parentRequired'
  if (flags.isChild && !form.product_child_name.trim()) return 'product.childRequired'
  if (!flags.isChild && !form.product_name.trim()) return 'product.nameRequired'
  if (!flags.isParentRole && !form.base_uom_id) return 'product.baseUomRequired'
  if (
    form.product_group_id &&
    groupOptions.find((option) => option.value === form.product_group_id)?.disabled !== false
  )
    return 'product.invalidGroupReference'
  if (
    !flags.isParentRole &&
    uomOptions.find((option) => option.value === form.base_uom_id)?.disabled !== false
  )
    return 'product.invalidUomReference'
  return null
}
