import type { ListSortOption } from '@/components/lists/listTypes'

/** Product id is the canonical default; API also accepts the historic `id` alias. */
export const DEFAULT_PRODUCT_SORT = '-product_id'

/** Explicit ASC/DESC options keep the shared list query controls unchanged. */
export const productSortOptions: readonly ListSortOption[] = [
  { value: 'product_id', labelKey: 'product.sortIdAsc' },
  { value: '-product_id', labelKey: 'product.sortIdDesc' },
  { value: 'full_name', labelKey: 'product.sortNameAsc' },
  { value: '-full_name', labelKey: 'product.sortNameDesc' },
  { value: 'price', labelKey: 'product.sortPriceAsc' },
  { value: '-price', labelKey: 'product.sortPriceDesc' },
  { value: 'product_code', labelKey: 'product.sortCodeAsc' },
  { value: '-product_code', labelKey: 'product.sortCodeDesc' },
  { value: 'sku', labelKey: 'product.sortSkuAsc' },
  { value: '-sku', labelKey: 'product.sortSkuDesc' },
  { value: 'product_type', labelKey: 'product.sortTypeAsc' },
  { value: '-product_type', labelKey: 'product.sortTypeDesc' },
  { value: 'status', labelKey: 'product.sortStatusAsc' },
  { value: '-status', labelKey: 'product.sortStatusDesc' },
]
