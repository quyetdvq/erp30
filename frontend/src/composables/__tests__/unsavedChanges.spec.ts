import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { defineComponent, h, nextTick, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { useWorkspaceStore } from '@/stores/workspace'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'

vi.mock('vue-router', () => ({ useRoute: () => ({ name: 'profile', path: '/profile' }) }))

beforeEach(() => {
  setActivePinia(createPinia())
  window.localStorage.clear()
})

describe('unsaved form guard', () => {
  it('marks the workspace tab dirty on user edits and clears on save or unmount', async () => {
    const workspace = useWorkspaceStore()
    workspace.openTab({
      key: 'profile',
      path: '/profile',
      labelKey: 'shell.profile',
      closable: true,
    })
    const state = ref({ name: '' })
    let guard!: ReturnType<typeof useUnsavedChanges>
    const Host = defineComponent({
      setup() {
        guard = useUnsavedChanges(state)
        return () => h('div')
      },
    })
    const wrapper = mount(Host)
    expect(workspace.isDirty('profile')).toBe(false)
    state.value.name = 'Updated'
    await nextTick()
    expect(workspace.isDirty('profile')).toBe(true)
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false)
    expect(guard.confirmDiscard()).toBe(false)
    confirm.mockReturnValue(true)
    expect(guard.confirmDiscard()).toBe(true)
    guard.markClean()
    expect(workspace.isDirty('profile')).toBe(false)
    state.value.name = 'another edit'
    await nextTick()
    expect(workspace.isDirty('profile')).toBe(true)
    wrapper.unmount()
    expect(workspace.isDirty('profile')).toBe(false)
    confirm.mockRestore()
  })

  it('does not mark a closed modal dirty and resets on reopening', async () => {
    const workspace = useWorkspaceStore()
    workspace.openTab({
      key: 'profile',
      path: '/profile',
      labelKey: 'shell.profile',
      closable: true,
    })
    const form = ref({ code: '' })
    const open = ref(false)
    const Host = defineComponent({
      setup() {
        useUnsavedChanges(form, open)
        return () => h('div')
      },
    })
    const wrapper = mount(Host)
    form.value.code = 'DRAFT'
    await nextTick()
    expect(workspace.isDirty('profile')).toBe(false)
    open.value = true
    await nextTick()
    expect(workspace.isDirty('profile')).toBe(true)
    open.value = false
    await nextTick()
    expect(workspace.isDirty('profile')).toBe(false)
    wrapper.unmount()
  })
})
