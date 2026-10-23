import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import ProductListThumbnail from '../ProductListThumbnail.vue'

const i18n = createI18n({
  legacy: false, locale: 'en',
  messages: { en: { product: { image: 'Image', imageEmpty: 'No image', imageLoadError: 'Unable to load' } } },
})

function mountThumbnail(props: { imageAvailable?: boolean; imagePath?: string | null }) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = { id: '7' } as unknown as NonNullable<typeof auth.user>
  const wrapper = mount(ProductListThumbnail, {
    props: { ...props, productName: 'Example product' },
    global: { plugins: [pinia, i18n] },
  })
  return { wrapper, auth }
}
let intersect: (() => void) | undefined
beforeEach(() => {
  intersect = undefined
  vi.stubGlobal('IntersectionObserver', class {
    constructor(callback: (entries: Array<{ isIntersecting: boolean }>) => void) {
      intersect = () => callback([{ isIntersecting: true }])
    }
    observe() {}
    disconnect() {}
  })
})
afterEach(() => { vi.unstubAllGlobals() })

const imagePath = '/writable/uploads/2026/20261023product5689522.jpg'
describe('Product list static image thumbnails', () => {
  it('renders URL only after entering viewport, never using a Blob', async () => {
    const { wrapper } = mountThumbnail({ imageAvailable: true, imagePath })
    expect(wrapper.find('img').exists()).toBe(false)
    intersect?.()
    await flushPromises()
    expect(wrapper.get('img').attributes('src')).toContain(imagePath)
    wrapper.unmount()
  })

  it('renders a validated URL even if the redundant availability flag is absent', async () => {
    const { wrapper } = mountThumbnail({ imageAvailable: false, imagePath })
    intersect?.()
    await flushPromises()
    expect(wrapper.get('img').attributes('src')).toContain(imagePath)
    await wrapper.setProps({ imageAvailable: true, imagePath: null })
    expect(wrapper.find('svg').exists()).toBe(true)
    wrapper.unmount()
  })

  it('accepts the deployed /writable/uploads URL but rejects mismatched years and types', async () => {
    const deployed = '/writable/uploads/2026/20261023product2726226.jpg'
    const { wrapper } = mountThumbnail({ imagePath: deployed })
    intersect?.()
    await flushPromises()
    expect(wrapper.get('img').attributes('src')).toContain(deployed)
    await wrapper.setProps({ imagePath: '/writable/uploads/2025/20261023product2726226.jpg' })
    expect(wrapper.find('img').exists()).toBe(false)
    await wrapper.setProps({ imagePath: '/writable/uploads/2026/20261023avatar2726226.jpg' })
    expect(wrapper.find('img').exists()).toBe(false)
    wrapper.unmount()
  })

  it('does not load invalid or legacy URLs', async () => {
    const { wrapper } = mountThumbnail({ imageAvailable: true, imagePath: '/uploads/product-images/legacy.jpg' })
    intersect?.()
    await flushPromises()
    expect(wrapper.find('img').exists()).toBe(false)
    wrapper.unmount()
  })

  it('when static URL fails, shows placeholder and never retries via API', async () => {
    const { wrapper } = mountThumbnail({ imageAvailable: true, imagePath })
    intersect?.()
    await flushPromises()
    await wrapper.get('img').trigger('error')
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('svg').exists()).toBe(true)
    wrapper.unmount()
  })

  it('resets on metadata change and hides image after logout', async () => {
    const { wrapper, auth } = mountThumbnail({ imageAvailable: true, imagePath })
    intersect?.()
    await flushPromises()
    await wrapper.get('img').trigger('error')
    const nextPath = '/writable/uploads/2027/20270105product5689524.webp'
    await wrapper.setProps({ imagePath: nextPath })
    expect(wrapper.get('img').attributes('src')).toContain(nextPath)
    auth.user = null
    await flushPromises()
    expect(wrapper.find('img').exists()).toBe(false)
    wrapper.unmount()
  })
})
