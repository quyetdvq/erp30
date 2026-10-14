import type { Product, ProductUomConversion } from '@/api/generated/types.gen'

/** Editable values remain strings until the API contract serializes DECIMAL fields. */
export type ProductEditorForm = {
  product_code: string
  sku: string
  barcode: string
  product_type: Product['product_type']
  product_name: string
  product_child_name: string
  parent_product_id: string
  product_group_id: string
  base_uom_id: string
  price: string
  net_weight_g: string
  package_gross_weight_g: string
  package_length_mm: string
  package_width_mm: string
  package_height_mm: string
  serial_management_enabled: boolean
  description: string
  status: Product['status']
}

export type ConversionForm = {
  id: string
  uom_id: string
  conversion_factor: string
  price: string
  package_gross_weight_g: string
  package_length_mm: string
  package_width_mm: string
  package_height_mm: string
  usage_type: ProductUomConversion['usage_type']
  status: ProductUomConversion['status']
}

export type ComboForm = { id: string; component_product_id: string; quantity: string }

export type SelectionOption = { value: string; disabled?: boolean }
