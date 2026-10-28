import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import ProductImageField from '../ProductImageField.vue'

const media = vi.hoisted(() => ({ upload: vi.fn<(...args: unknown[]) => Promise<unknown>>(), remove: vi.fn<(...args: unknown[]) => Promise<unknown>>() }))
vi.mock('@/services/productImage', () => ({
  uploadProductImage: media.upload,
  deleteProductImage: media.remove,
}))

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  messages: { en: { product: {
    image: 'Image', imageEmpty: 'No image', imageChoose: 'Choose',
    imageRemove: 'Remove', imageHint: 'JPEG/PNG/WebP',
    imageSaveHint: 'Save Product to upload', imageInvalid: 'Invalid image',
    imageLoadError: 'Image error', imageSaveError: 'Upload error',
  } } },
})

const staticUrl = '/writable/uploads/2026/20261023product5689522.jpg'
const anotherUrl = '/writable/uploads/2026/20261023product5689523.jpg'

function setup(props: { productId?: string; imagePath?: string | null; imageAvailable?: boolean } = {}) {
  const wrapper = mount(ProductImageField, {
    props: { editable: true, ...props },
    global: { plugins: [i18n] },
  })
  const commit = (id: string): Promise<unknown> =>
    (wrapper.vm as unknown as { commit(id: string): Promise<unknown> }).commit(id)
  return { wrapper, commit }
}
async function choose(wrapper: ReturnType<typeof setup>['wrapper']): Promise<File> {
  const file = new File(['valid test bytes for UI selection'], 'selected.jpg', { type: 'image/jpeg' })
  const input = wrapper.get('input[type="file"]')
  Object.defineProperty(input.element, 'files', { configurable: true, value: [file] })
  await input.trigger('change')
  return file
}

beforeEach(() => {
  media.upload.mockReset().mockResolvedValue({ id: 42, image_url: staticUrl, image_available: true })
  media.remove.mockReset().mockResolvedValue({ id: 42, image_url: null, image_available: false })
  const NativeURL = URL
  vi.stubGlobal('URL', class extends NativeURL {
    static createObjectURL(): string { return 'blob:pending-product-preview' }
    static revokeObjectURL(): void {}
  })
})
afterEach(() => { vi.unstubAllGlobals() })

describe('Product image two-step JSON save + upload', () => {
  it('CREATE response changes undefined imagePath to null without discarding the pending file', async () => {
    const { wrapper, commit } = setup()
    const file = await choose(wrapper)
    expect(wrapper.get('img').attributes('src')).toBe('blob:pending-product-preview')
    await wrapper.setProps({ imagePath: null, imageAvailable: false })
    expect(wrapper.get('img').attributes('src')).toBe('blob:pending-product-preview')
    expect(await commit('42')).toMatchObject({ id: 42, image_url: staticUrl })
    expect(media.upload).toHaveBeenCalledTimes(1)
    expect(media.upload).toHaveBeenCalledWith('42', file)
    await wrapper.setProps({ productId: '42', imageAvailable: true, imagePath: staticUrl })
    expect(wrapper.get('img').attributes('src')).toContain(staticUrl)
    wrapper.unmount()
  })

  it('preserves a failed upload after the newly created Product acquires an ID, allowing PATCH retry', async () => {
    const { wrapper, commit } = setup()
    const file = await choose(wrapper)
    media.upload.mockRejectedValueOnce(new Error('network temporarily unavailable'))
    await expect(commit('42')).rejects.toThrow('network temporarily unavailable')
    await wrapper.setProps({ productId: '42', imagePath: null })
    await flushPromises()
    expect(wrapper.get('img').attributes('src')).toBe('blob:pending-product-preview')
    expect(await commit('42')).toMatchObject({ id: 42, image_url: staticUrl })
    expect(media.upload).toHaveBeenCalledTimes(2)
    expect(media.upload).toHaveBeenLastCalledWith('42', file)
    wrapper.unmount()
  })

  it('EDIT metadata refresh does not clear the pending image, but switching Products does', async () => {
    const { wrapper, commit } = setup({ productId: '19', imageAvailable: true, imagePath: staticUrl })
    const file = await choose(wrapper)
    await wrapper.setProps({ imagePath: anotherUrl, imageAvailable: true })
    expect(await commit('19')).toMatchObject({ id: 42, image_url: staticUrl })
    expect(media.upload).toHaveBeenCalledWith('19', file)
    await choose(wrapper)
    await wrapper.setProps({ productId: '20', imagePath: null, imageAvailable: false })
    expect(await commit('20')).toBeNull()
    expect(media.upload).toHaveBeenCalledTimes(1)
    wrapper.unmount()
  })

  it('DELETE uses a persisted validated URL even if the redundant availability flag is missing', async () => {
    const { wrapper, commit } = setup({ productId: '19', imageAvailable: false, imagePath: staticUrl })
    await wrapper.get('.image-remove-button').trigger('click')
    expect(await commit('19')).toMatchObject({ id: 42, image_url: null })
    expect(media.remove).toHaveBeenCalledTimes(1)
    expect(media.remove).toHaveBeenCalledWith('19')
    expect(media.upload).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
