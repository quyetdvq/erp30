import type {
  ComboItemInput,
  ProductCreate,
  ProductPatch,
  ProductUomConversionsRequest,
} from '@/api/generated/types.gen'
import type { ComboForm, ConversionForm, ProductEditorForm } from './types'

function optionalString(value: string): string | undefined {
  return value === '' ? undefined : value
}

export function comboPayload(items: readonly ComboForm[]): ComboItemInput[] {
  return items.map((row) => ({
    component_product_id: row.component_product_id,
    quantity: row.quantity,
  }))
}

export function productPayload(
  form: ProductEditorForm,
  flags: { isChild: boolean; isCombo: boolean; isParentRole: boolean; editing: boolean },
  comboItems: readonly ComboForm[],
): ProductCreate | ProductPatch {
  const base: ProductCreate = {
    product_code: form.product_code || null,
    sku: form.sku || null,
    barcode: form.barcode || null,
    product_type: form.product_type,
    ...(form.product_group_id ? { product_group_id: form.product_group_id } : {}),
    ...(form.base_uom_id ? { base_uom_id: form.base_uom_id } : {}),
    ...(form.parent_product_id ? { parent_product_id: form.parent_product_id } : {}),
    ...(flags.isChild
      ? { product_child_name: form.product_child_name }
      : { product_name: form.product_name }),
    ...(optionalString(form.price) && !flags.isParentRole ? { price: form.price } : {}),
    ...(optionalString(form.net_weight_g) ? { net_weight_g: form.net_weight_g } : {}),
    ...(optionalString(form.package_gross_weight_g)
      ? { package_gross_weight_g: form.package_gross_weight_g }
      : {}),
    ...(optionalString(form.package_length_mm)
      ? { package_length_mm: form.package_length_mm }
      : {}),
    ...(optionalString(form.package_width_mm) ? { package_width_mm: form.package_width_mm } : {}),
    ...(optionalString(form.package_height_mm)
      ? { package_height_mm: form.package_height_mm }
      : {}),
    ...(form.description ? { description: form.description } : {}),
    serial_management_enabled: flags.isCombo ? false : form.serial_management_enabled,
  }
  // One CREATE request: product + COMBO components are written atomically.
  return !flags.editing && flags.isCombo && comboItems.length
    ? { ...base, combo_items: comboPayload(comboItems) }
    : base
}

export function conversionsPayload(
  conversions: readonly ConversionForm[],
): ProductUomConversionsRequest {
  return conversions.map((row) => ({
    uom_id: row.uom_id,
    conversion_factor: row.conversion_factor,
    ...(row.price ? { price: row.price } : {}),
    ...(row.package_gross_weight_g ? { package_gross_weight_g: row.package_gross_weight_g } : {}),
    ...(row.package_length_mm ? { package_length_mm: row.package_length_mm } : {}),
    ...(row.package_width_mm ? { package_width_mm: row.package_width_mm } : {}),
    ...(row.package_height_mm ? { package_height_mm: row.package_height_mm } : {}),
    usage_type: row.usage_type,
    status: row.status,
  }))
}
