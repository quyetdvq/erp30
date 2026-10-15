import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import UserAvatar from '@/components/users/UserAvatar.vue'
import { useAuthStore } from '@/stores/auth'
import { readUserAvatar } from '@/services/userAvatar'

vi.mock('@/services/userAvatar', () => ({
  readUserAvatar: vi.fn(),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
}))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
  Object.defineProperty(URL, 'createObjectURL', { configurable: true, value: vi.fn(() => 'blob:avatar-1') })
  Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, value: vi.fn() })
})

describe('private User Avatar', () => {
  it('shows initials when no private avatar exists without fetching', async () => {
    const wrapper = mount(UserAvatar, { props: { userId: '7', hasAvatar: false, fallback: 'Nguyễn A' } })
    expect(wrapper.get('[role="img"]').text()).toBe('N')
    expect(readUserAvatar).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('revokes private object URL when the user logs out', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 7, username: 'nguyena', name: 'Nguyễn A', has_avatar: true,
      email: 'a@example.test', active: true, status: 'ACTIVE', groups: [], base_permissions: [],
    }
    vi.mocked(readUserAvatar).mockResolvedValue(new Blob(['avatar'], { type: 'image/png' }))
    const wrapper = mount(UserAvatar, { props: { userId: '7', hasAvatar: true, fallback: 'Nguyễn A' } })
    await flushPromises()
    expect(wrapper.get('img').attributes('src')).toBe('blob:avatar-1')
    auth.expireSession()
    await flushPromises()
    expect(wrapper.find('img').exists()).toBe(false)
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:avatar-1')
    wrapper.unmount()
  })
})
