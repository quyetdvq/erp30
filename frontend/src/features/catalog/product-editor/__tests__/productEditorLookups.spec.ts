import { computed, ref } from 'vue'
import { describe, expect, it } from 'vitest'
import type { ProductGroup, ProductUom } from '@/api/generated/types.gen'
import { emptyProductForm } from '../model'
import { useProductEditorLookups } from '../useProductEditorLookups'

describe('Product Editor lookup ownership', () => {
  it('preserves the current historical disabled references and filters self-selection', () => {
    const id = ref<string | undefined>('p1')
    const form = ref(emptyProductForm())
    const comboItems = ref([{ id: '', component_product_id: '', quantity: '' }])
    const lookups = useProductEditorLookups(
      computed(() => id.value),
      form,
      comboItems,
      (key) => key,
    )
    form.value.product_group_id = 'g2'
    form.value.base_uom_id = 'u2'
    lookups.groups.value = [
      { id: 'g1', status: 'ACTIVE', code: 'A', name: 'Active', parent_id: null },
      { id: 'g2', status: 'INACTIVE', code: 'B', name: 'Old', parent_id: null },
    ] as ProductGroup[]
    lookups.uoms.value = [
      { id: 'u1', status: 'ACTIVE', code: 'EA', name: 'Each' },
      { id: 'u2', status: 'INACTIVE', code: 'BX', name: 'Old box' },
    ] as ProductUom[]
    expect(lookups.groupOptions.value).toEqual([
      { value: 'g1', label: 'Active (A)', disabled: false },
      { value: 'g2', label: 'Old (B)', disabled: true },
    ])
    expect(lookups.uomOptions.value).toEqual([
      { value: 'u1', label: 'Each (EA)', disabled: false },
      { value: 'u2', label: 'Old box (BX)', disabled: true },
    ])
    expect(lookups.baseUomName.value).toBe('Old box')
    lookups.products.value = [
      {
        id: 'p1',
        full_name: 'Self',
        product_code: 'SELF',
        role: 'SIMPLE',
        status: 'ACTIVE',
        selectable: true,
      },
      {
        id: 'p2',
        full_name: 'Other',
        product_code: 'OTHER',
        role: 'CHILD',
        status: 'INACTIVE',
        selectable: false,
      },
    ] as never
    expect(lookups.comboSelectOptions.value).toEqual([
      { value: 'p2', label: 'Other (product.roleChild)', disabled: true },
    ])
    form.value.parent_product_id = 'p2'
    expect(lookups.parentSelectOptions.value).toEqual([{ value: 'p2', label: 'Other (OTHER)' }])
    expect(lookups.parentRow.value?.id).toBe('p2')
  })
})
