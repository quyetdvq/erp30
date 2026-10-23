import { beforeEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import UserAvatar from '@/components/users/UserAvatar.vue'
import { useAuthStore } from '@/stores/auth'

beforeEach(() => { setActivePinia(createPinia()) })
function login() {
  const auth = useAuthStore()
  auth.user = {
    id: 7, username: 'nguyena', name: 'Nguyễn A', has_avatar: true,
    email: 'a@example.test', active: true, status: 'ACTIVE', groups: [], base_permissions: [],
  }
  return auth
}
const avatarPath = '/writable/uploads/2026/20261023avatar5689523.webp'
describe('static-only User Avatar', () => {
  it('renders URL directly, without a binary API request', () => {
    login()
    const wrapper = mount(UserAvatar, { props: { userId: '7', hasAvatar: true, fallback: 'Nguyễn A', avatarUrl: avatarPath } })
    expect(wrapper.get('img').attributes('src')).toContain(avatarPath)
    wrapper.unmount()
  })

  it('shows initials when URL is absent or legacy', () => {
    login()
    const wrapper = mount(UserAvatar, { props: { userId: '7', hasAvatar: true, fallback: 'Nguyễn A' } })
    expect(wrapper.get('[role="img"]').text()).toBe('N')
    wrapper.unmount()
  })

  it('shows initials on static image failure and after logout; new URL resets error', async () => {
    const auth = login()
    const wrapper = mount(UserAvatar, { props: { userId: '7', hasAvatar: true, fallback: 'Nguyễn A', avatarUrl: avatarPath } })
    await wrapper.get('img').trigger('error')
    expect(wrapper.get('[role="img"]').text()).toBe('N')
    await wrapper.setProps({ avatarUrl: '/writable/uploads/2027/20270105avatar5689524.jpg' })
    expect(wrapper.get('img').attributes('src')).toContain('/writable/uploads/2027/')
    auth.expireSession()
    await flushPromises()
    expect(wrapper.find('img').exists()).toBe(false)
    wrapper.unmount()
  })
})
