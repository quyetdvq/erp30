import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { i18n } from '@/i18n'
import ProductImportView from '@/features/product-import/pages/ProductImportView.vue'

const mocks = vi.hoisted(() => ({
  push: vi.fn<() => void>(),
  startProductImportJob: vi.fn<(file: File, mode: string) => Promise<unknown>>(),
  allowed: true,
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mocks.push }),
}))

vi.mock('@/services/productImport', () => ({
  startProductImportJob: mocks.startProductImportJob,
}))

vi.mock('@/composables/useBasePermission', () => ({
  useBasePermission: () => ({
    snapshotState: { value: 'ready' },
    hasBasePermission: () => mocks.allowed,
  }),
}))

describe('Product import wizard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mocks.push.mockReset()
    mocks.startProductImportJob.mockReset()
    mocks.allowed = true
  })

  function render() {
    return mount(ProductImportView, {
      global: { plugins: [i18n] },
    })
  }

  it('offers versioned CSV and XLSX templates from the shared wizard', () => {
    const wrapper = render()
    const links = wrapper.findAll('.download-templates a')
    expect(links).toHaveLength(2)
    expect(links.map(link => link.attributes('href'))).toEqual([
      '/import-templates/product-v1.xlsx',
      '/import-templates/product-v1.csv',
    ])
    expect(links.every(link => link.attributes('download') !== undefined)).toBe(true)
  })

  it('defaults to CREATE_ONLY and rejects formula-like CSV values before submit', async () => {
    const wrapper = render()
    const input = wrapper.get('input[type="file"]')
    const csv = new File(['product_code,name\n=NOW(),Formula\n'], 'products.csv', {
      type: 'text/csv',
    })

    Object.defineProperty(input.element, 'files', { value: [csv] })
    await input.trigger('change')
    expect(wrapper.get('input[type="radio"][value="CREATE_ONLY"]').element).toHaveProperty(
      'checked',
      true,
    )
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('CSV chứa công thức')
    expect(mocks.startProductImportJob).not.toHaveBeenCalled()
  })

  it('fails closed when the permission snapshot has no product.import hint', () => {
    mocks.allowed = false
    const wrapper = render()
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Base Permission product.import')
  })
})
