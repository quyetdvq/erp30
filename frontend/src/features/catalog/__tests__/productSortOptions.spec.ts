import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ListQueryControls from '@/components/lists/ListQueryControls.vue'
import { i18n } from '@/i18n'
import type { ListQuery } from '@/components/lists/listTypes'
import { createListQuery, serializeListQuery } from '@/components/lists/listTypes'
import { DEFAULT_PRODUCT_SORT, productSortOptions } from '@/features/catalog/productSortOptions'
import { en } from '@/i18n/locales/en'
import { vi } from '@/i18n/locales/vi'

describe('Product sorting choices', () => {
  it('defaults to product_id (persisted id) and resets pagination when changed', () => {
    expect(DEFAULT_PRODUCT_SORT).toBe('product_id')
    expect(serializeListQuery(createListQuery({ sort: DEFAULT_PRODUCT_SORT }))).toContain(
      'sort=product_id',
    )
  })

  it('exposes ascending and descending options for each supported Product field', () => {
    const fields = ['product_id', 'full_name', 'price', 'product_code', 'sku', 'product_type', 'status']
    const values = productSortOptions.map(({ value }) => value)
    expect(values).toEqual(fields.flatMap((field) => [field, `-${field}`]))
    expect(new Set(values).size).toBe(values.length)
    for (const item of productSortOptions) {
      const key = item.labelKey.replace('product.', '') as keyof typeof vi.product
      expect(vi.product[key]).toBeTruthy()
      expect(en.product[key as keyof typeof en.product]).toBeTruthy()
    }
  })
  it('submits the selected sort through the existing shared query controls', async () => {
    const wrapper = mount(ListQueryControls, {
      props: {
        modelValue: createListQuery({ sort: DEFAULT_PRODUCT_SORT }),
        sortOptions: productSortOptions,
      },
      global: { plugins: [i18n] },
    })
    const select = wrapper.findAll('select').find((item) =>
      item.find('option[value="-price"]').exists(),
    )
    expect(select).toBeDefined()
    expect((select!.element as HTMLSelectElement).value).toBe(DEFAULT_PRODUCT_SORT)
    await select!.setValue('-price')
    await wrapper.get('form').trigger('submit')
    const submitted = wrapper.emitted('apply')?.[0]?.[0] as ListQuery
    expect(submitted.sort).toBe('-price')
    expect(submitted.page).toBe(1)
    wrapper.unmount()
  })
})
