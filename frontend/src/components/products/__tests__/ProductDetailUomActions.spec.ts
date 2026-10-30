import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import type { Product, ProductUom } from '@/api/generated/types.gen'
import { i18n } from '@/i18n'
import ProductDetailModal from '../ProductDetailModal.vue'

const actions = vi.hoisted(() => ({
  listConversions: vi.fn(),
  replaceConversions: vi.fn(),
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ getSessionGeneration: () => 1 }),
}))
vi.mock('@/composables/useBasePermission', () => ({
  useBasePermission: () => ({
    snapshotState: { value: 'ready' },
    hasBasePermission: (resource: string, action: string) => resource === 'product' && ['view', 'edit'].includes(action),
    hasBasePermissionKey: (key: string) => key === 'product.view' || key === 'product.edit',
  }),
}))
vi.mock('@/composables/useFormatters', () => ({
  useFormatters: () => ({
    formatQuantity: (value: string) => value,
    formatMoney: (value: string) => value,
  }),
}))
vi.mock('@/services/errorBoundary', () => ({ reportApiError: vi.fn() }))
vi.mock('@/services/productCatalog', () => ({
  getProductById: vi.fn(),
  listProductComboItems: vi.fn(),
  listProductConversions: actions.listConversions,
  listProductPage: vi.fn(),
  replaceProductComboItems: vi.fn(),
  replaceProductConversions: actions.replaceConversions,
  saveProduct: vi.fn(),
}))

const product = {
  id: '1479', product_code: 'P-1479', full_name: 'Example product11',
  role: 'SIMPLE', product_type: 'STANDARD', base_uom_id: '1',
  status: 'ACTIVE', selectable: true, leaf_transactionable: true,
  allowed_actions: { edit: true, setstate: false },
} as Product
const uoms = [
  { id: '1', code: 'EA', name: 'Chiếc', status: 'ACTIVE' },
  { id: '2', code: 'DM', name: 'dm', status: 'ACTIVE' },
  { id: '3', code: 'TREE', name: 'Cây', status: 'ACTIVE' },
] as ProductUom[]
const rows = [
  { id: '101', product_id: '1479', uom_id: '2', conversion_factor: '22',
    usage_type: 'BOTH', status: 'ACTIVE', price: '333333' },
  { id: '102', product_id: '1479', uom_id: '3', conversion_factor: '12',
    usage_type: 'BOTH', status: 'INACTIVE', price: '345555' },
]
const cleanups: Array<() => void> = []

async function mountDetail(currentProduct: Product = product): Promise<VueWrapper> {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const wrapper = mount(ProductDetailModal, {
    attachTo: host,
    props: { open: false, product: currentProduct, groups: [], uoms, products: [currentProduct] },
    global: {
      plugins: [i18n],
      stubs: {
        ProductImageField: true, RemoteSearchableSelect: true, SearchableSelect: true,
        DecimalInput: true,
      },
    },
  })
  cleanups.push(() => { wrapper.unmount(); host.remove() })
  await wrapper.setProps({ open: true })
  await flushPromises()
  return wrapper
}

async function openConversions(wrapper: VueWrapper): Promise<void> {
  const tab = wrapper.findAll('[role="tab"]').find((item) => item.text() === i18n.global.t('product.tabConversions'))
  expect(tab, 'Product SIMPLE must have the conversion tab').toBeDefined()
  await tab!.trigger('click')
  await flushPromises()
}

beforeEach(() => {
  actions.listConversions.mockReset()
  actions.listConversions.mockResolvedValue(rows)
  actions.replaceConversions.mockReset()
  actions.replaceConversions.mockResolvedValue({})
})
afterEach(() => { cleanups.splice(0).forEach((cleanup) => cleanup()) })

describe('FE-PRODUCT-DETAIL: shared modal and UOM conversion actions', () => {
  it('uses the same ERP header/footer and stable multi-tab workspace as Customer', async () => {
    const wrapper = await mountDetail()
    expect(wrapper.get('.detail-modal.tabbed-detail-workspace').attributes('role')).toBe('dialog')
    expect(wrapper.get('.modal-card__header .modal-card-title').text()).toContain('Example product11')
    expect(wrapper.get('.modal-card__header .product-detail-code').text()).toContain('P-1479')
    expect(wrapper.get('.modal-card-button-close').attributes('aria-label')).toBe(i18n.global.t('common.close'))
    expect(wrapper.find('.modal-card__header .product-header-status .table-status--active').exists()).toBe(true)
    expect(wrapper.get('.product-header-status').text()).toContain(i18n.global.t('status.active'))
    const footerButtons = wrapper.findAll('.modal-card__footer .secondary-button')
    expect(footerButtons).toHaveLength(2)
    expect(footerButtons[0]!.text()).toBe(i18n.global.t('common.edit'))
    expect(footerButtons[1]!.text()).toBe(i18n.global.t('common.close'))
    expect(wrapper.find('.product-detail-toolbar').exists()).toBe(false)
    await openConversions(wrapper)
    expect(wrapper.find('.detail-body').exists()).toBe(true)
    expect(wrapper.find('.modal-card__header').exists()).toBe(true)
    expect(wrapper.find('.modal-card__footer').exists()).toBe(true)
  })

  it('edits from the footer, not the header, and keeps the close action next to it', async () => {
    const wrapper = await mountDetail()
    expect(wrapper.find('.modal-card__header button.secondary-button').exists()).toBe(false)
    await wrapper.findAll('.modal-card__footer button')[0]!.trigger('click')
    expect(wrapper.emitted('edit')).toHaveLength(1)
    expect(wrapper.emitted('edit')?.[0]?.[0]).toMatchObject({ id: '1479' })
    await wrapper.findAll('.modal-card__footer button')[1]!.trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('renders status icons and edit/delete icons for each authorized conversion', async () => {
    const wrapper = await mountDetail()
    await openConversions(wrapper)
    expect(wrapper.findAll('.conversion-state .table-status')).toHaveLength(2)
    expect(wrapper.find('.conversion-state .table-status--active').exists()).toBe(true)
    expect(wrapper.find('.conversion-state .table-status--inactive').exists()).toBe(true)
    expect(wrapper.findAll('.conversion-actions .table-row-action')).toHaveLength(4)
    const buttons = wrapper.findAll('.conversion-actions .table-row-action')
    expect(buttons[0]!.attributes('aria-label')).toContain(i18n.global.t('common.edit'))
    expect(buttons[1]!.attributes('aria-label')).toContain(i18n.global.t('common.delete'))
    await buttons[0]!.trigger('click')
    expect(wrapper.find('.nested-backdrop [role="dialog"]').exists()).toBe(true)
    expect(wrapper.get('.nested-backdrop [role="dialog"]').attributes('aria-label'))
      .toBe(i18n.global.t('product.conversionForm'))
  })

  it('removes a conversion only in draft until the canonical replacement is saved', async () => {
    const wrapper = await mountDetail()
    await openConversions(wrapper)
    expect(wrapper.findAll('.conversion-actions')).toHaveLength(2)
    await wrapper.findAll('.conversion-actions .table-row-action')[1]!.trigger('click')
    expect(actions.replaceConversions).not.toHaveBeenCalled()
    expect(wrapper.findAll('.conversion-actions')).toHaveLength(1)
    const save = wrapper.find('.tab-panel > .primary-button')
    await save.trigger('click')
    await flushPromises()
    expect(actions.replaceConversions).toHaveBeenCalledTimes(1)
    const [id, body] = actions.replaceConversions.mock.calls[0]!
    expect(id).toBe('1479')
    expect(body).toHaveLength(1)
    expect(body[0].uom_id).toBe('3')
  })

  it('never exposes conversion mutation icons without the persisted Product edit hint', async () => {
    const wrapper = await mountDetail({ ...product, allowed_actions: { edit: false, setstate: false } })
    await openConversions(wrapper)
    expect(wrapper.findAll('.conversion-state .table-status')).toHaveLength(2)
    expect(wrapper.findAll('.conversion-actions .table-row-action')).toHaveLength(0)
    expect(wrapper.findAll('.modal-card__footer button')).toHaveLength(1)
    expect(wrapper.find('.modal-card__footer').text()).toContain(i18n.global.t('common.close'))
    expect(wrapper.find('.product-header-status').exists()).toBe(true)
    expect(wrapper.find('.tab-panel > .primary-button').exists()).toBe(false)
  })
})
