import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { i18n } from '@/i18n'
import ProductEditorFeedback from '../components/ProductEditorFeedback.vue'
import ProductEditorPageHeading from '../components/ProductEditorPageHeading.vue'

describe('Product Editor presentational components', () => {
  it('keeps the create/edit heading and emits the back navigation intent', async () => {
    const wrapper = mount(ProductEditorPageHeading, {
      props: { editing: false },
      global: { plugins: [i18n] },
    })
    expect(wrapper.find('.page-heading h1').text()).toBe(i18n.global.t('product.createTitle'))
    await wrapper.setProps({ editing: true })
    expect(wrapper.find('.page-heading h1').text()).toBe(i18n.global.t('product.editTitle'))
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('back')).toHaveLength(1)
  })

  it('renders the same fallback and validation alerts without an enclosing DOM element', () => {
    const wrapper = mount(ProductEditorFeedback, {
      props: {
        error: new Error('test'),
        validationError: null,
        isParentRole: false,
        imagePartialError: false,
      },
      global: { plugins: [i18n] },
    })
    expect(wrapper.findAll('[role="alert"]')).toHaveLength(1)
    expect(wrapper.get('[role="alert"]').text()).toBe(i18n.global.t('product.requestError'))
    return wrapper.setProps({ validationError: 'Required field', error: null }).then(() => {
      expect(wrapper.get('[role="alert"]').text()).toBe('Required field')
    })
  })

  it('keeps parent restriction and partial-image-save status accessible', () => {
    const wrapper = mount(ProductEditorFeedback, {
      props: {
        error: null,
        validationError: null,
        isParentRole: true,
        imagePartialError: true,
      },
      global: { plugins: [i18n] },
    })
    expect(wrapper.get('[role="status"]').text()).toBe(i18n.global.t('product.parentRestriction'))
    expect(wrapper.get('[role="alert"]').text()).toBe(i18n.global.t('product.imagePartialSave'))
  })
})
