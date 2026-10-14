import type { ComboItem, Product, ProductUomConversion } from '@/api/generated/types.gen'
import { databaseFlagEnabled } from '@/services/productPresentation'
import type { ComboForm, ConversionForm, ProductEditorForm } from './types'

export function emptyProductForm(): ProductEditorForm {
  return {
    product_code: '',
    sku: '',
    barcode: '',
    product_type: 'STANDARD' as Product['product_type'],
    product_name: '',
    product_child_name: '',
    parent_product_id: '',
    product_group_id: '',
    base_uom_id: '',
    price: '',
    net_weight_g: '',
    package_gross_weight_g: '',
    package_length_mm: '',
    package_width_mm: '',
    package_height_mm: '',
    serial_management_enabled: false,
    description: '',
    status: 'ACTIVE' as Product['status'],
  }
}

export function emptyConversion(): ConversionForm {
  return {
    id: '',
    uom_id: '',
    conversion_factor: '',
    price: '',
    package_gross_weight_g: '',
    package_length_mm: '',
    package_width_mm: '',
    package_height_mm: '',
    usage_type: 'BOTH',
    status: 'ACTIVE',
  }
}

export function textValue(value: string | number | null | undefined): string {
  return value === null || value === undefined ? '' : String(value)
}

/** Replace the form wholesale, as before, so dirty-snapshot semantics stay intact. */
export function productToForm(row: Product): ProductEditorForm {
  return {
    product_code: textValue(row.product_code),
    sku: textValue(row.sku),
    barcode: textValue(row.barcode),
    product_type: row.product_type,
    product_name: textValue(row.product_name),
    product_child_name: textValue(row.product_child_name),
    parent_product_id: textValue(row.parent_product_id),
    product_group_id: textValue(row.product_group_id),
    base_uom_id: textValue(row.base_uom_id),
    price: textValue(row.price),
    net_weight_g: textValue(row.net_weight_g),
    package_gross_weight_g: textValue(row.package_gross_weight_g),
    package_length_mm: textValue(row.package_length_mm),
    package_width_mm: textValue(row.package_width_mm),
    package_height_mm: textValue(row.package_height_mm),
    serial_management_enabled: databaseFlagEnabled(row.serial_management_enabled),
    description: textValue(row.description),
    status: row.status,
  }
}

export function toConversion(row: ProductUomConversion): ConversionForm {
  return {
    id: textValue(row.id),
    uom_id: textValue(row.uom_id),
    conversion_factor: textValue(row.conversion_factor),
    price: textValue(row.price),
    package_gross_weight_g: textValue(row.package_gross_weight_g),
    package_length_mm: textValue(row.package_length_mm),
    package_width_mm: textValue(row.package_width_mm),
    package_height_mm: textValue(row.package_height_mm),
    usage_type: row.usage_type,
    status: row.status,
  }
}

export function toCombo(row: ComboItem): ComboForm {
  return {
    id: textValue(row.id),
    component_product_id: textValue(row.component_product_id),
    quantity: textValue(row.quantity),
  }
}
