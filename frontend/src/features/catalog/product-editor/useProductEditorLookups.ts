import { computed, ref, type ComputedRef, type Ref } from 'vue'
import type { ProductGroup, ProductUom } from '@/api/generated/types.gen'
import {
  productRoleLabel,
  productUomName,
  selectableProductGroupOptions,
  selectableProductUomOptions,
} from '@/services/productPresentation'
import {
  resolveProductOptions,
  searchProductOptions,
  type ProductOption,
} from '@/services/productLookups'
import type { ComboForm, ProductEditorForm } from './types'

/** Product option fetching, selection and hydration share a single owner. */
export function useProductEditorLookups(
  id: ComputedRef<string | undefined>,
  form: Ref<ProductEditorForm>,
  comboItems: Ref<ComboForm[]>,
  t: (key: string) => string,
) {
  const groups = ref<ProductGroup[]>([])
  const uoms = ref<ProductUom[]>([])
  const products = ref<ProductOption[]>([])
  const lookupsReady = ref(false)
  const groupOptions = computed(() =>
    selectableProductGroupOptions(groups.value, form.value.product_group_id),
  )
  const uomOptions = computed(() => selectableProductUomOptions(uoms.value, form.value.base_uom_id))
  const parentSelectOptions = computed(() =>
    products.value
      .filter((row) => row.id !== id.value && row.id === form.value.parent_product_id)
      .map((row) => ({
        value: String(row.id),
        label: `${row.full_name} (${row.product_code ?? row.id})`,
      })),
  )
  const comboSelectOptions = computed(() =>
    products.value
      .filter((row) => String(row.id) !== id.value)
      .map((row) => ({
        value: String(row.id),
        label: `${row.full_name} (${productRoleLabel(row.role, t)})`,
        disabled: row.status !== 'ACTIVE' || !row.selectable,
      })),
  )
  async function searchParentProducts(q: string, page: number) {
    const response = await searchProductOptions('parent_product', q, page)
    return {
      options: response.data
        .filter((row) => String(row.id) !== id.value)
        .map((row) => ({
          value: String(row.id),
          label: `${row.full_name} (${row.product_code ?? row.id})`,
        })),
      total: response.meta.total,
    }
  }
  async function searchComboProducts(q: string, page: number) {
    const response = await searchProductOptions('combo_component', q, page)
    return {
      options: response.data
        .filter((row) => String(row.id) !== id.value)
        .map((row) => ({
          value: String(row.id),
          label: `${row.full_name} (${productRoleLabel(row.role, t)})`,
          disabled: row.status !== 'ACTIVE' || !row.selectable,
        })),
      total: response.meta.total,
    }
  }
  async function hydrateChosenProducts(ids: readonly string[]): Promise<void> {
    const distinct = [...new Set(ids.filter(Boolean))]
    if (!distinct.length) return
    const result = await resolveProductOptions(distinct)
    const known = new Map(products.value.map((row) => [String(row.id), row]))
    for (const row of result) known.set(String(row.id), row)
    products.value = [...known.values()]
  }
  async function parentChanged(value: string): Promise<void> {
    form.value.parent_product_id = value
    if (value) await hydrateChosenProducts([value])
  }
  async function componentChanged(index: number, value: string): Promise<void> {
    const row = comboItems.value[index]
    if (!row) return
    row.component_product_id = value
    if (value) await hydrateChosenProducts([value])
  }
  const baseUomName = computed(
    () => productUomName(uoms.value, form.value.base_uom_id) ?? t('list.noValue'),
  )
  const parentRow = computed(() =>
    products.value.find((row) => String(row.id) === form.value.parent_product_id),
  )

  return {
    groups,
    uoms,
    products,
    lookupsReady,
    groupOptions,
    uomOptions,
    parentSelectOptions,
    comboSelectOptions,
    searchParentProducts,
    searchComboProducts,
    hydrateChosenProducts,
    parentChanged,
    componentChanged,
    baseUomName,
    parentRow,
  }
}
