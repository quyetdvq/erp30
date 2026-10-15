import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { useBasePermission } from '@/composables/useBasePermission'
import { useAuthStore } from '@/stores/auth'
import { getCurrentUser, updateProfile, type AuthUser } from '@/services/auth'

vi.mock('@/services/auth', () => ({
  getCurrentUser: vi.fn<() => Promise<AuthUser>>(),
  login: vi.fn<() => Promise<AuthUser>>(),
  logout: vi.fn<() => Promise<void>>(),
  updateProfile: vi.fn<() => Promise<AuthUser>>(),
}))

const user = (permissions: string[]): AuthUser => ({
  id: 7,
  name: null,
  has_avatar: false,
  username: 'tester',
  email: 'tester@example.test',
  active: true,
  status: 'active',
  groups: ['display-admin'],
  base_permissions: permissions,
})

describe('base permission helper', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('fails closed until a ready snapshot and accepts canonical keys only', () => {
    const auth = useAuthStore()
    const helper = useBasePermission()

    auth.user = user(['display_setting.edit'])
    expect(helper.hasBasePermission('display_setting', 'edit')).toBe(false)

    auth.permissionSnapshotState = 'ready'
    expect(helper.hasBasePermission('display_setting', 'edit')).toBe(true)
    expect(helper.hasBasePermissionKey('DISPLAY_SETTING.EDIT')).toBe(true)
    expect(helper.hasBasePermissionKey('display.setting.edit')).toBe(false)
    expect(helper.hasBasePermissionKey('display_setting.unknown')).toBe(false)

    auth.permissionSnapshotState = 'stale'
    expect(helper.hasBasePermission('display_setting', 'edit')).toBe(false)
  })

  it('replaces the snapshot on session/profile refresh and fails closed on refresh errors', async () => {
    const auth = useAuthStore()
    vi.mocked(getCurrentUser).mockResolvedValueOnce(user(['product.view']))
    await auth.hydrate()
    expect(auth.permissionSnapshotState).toBe('ready')
    expect(auth.user?.base_permissions).toEqual(['product.view'])

    vi.mocked(getCurrentUser).mockResolvedValueOnce(user(['product.edit']))
    auth.markPermissionSnapshotStale()
    await auth.refreshBasePermissions()
    expect(auth.permissionSnapshotState).toBe('ready')
    expect(auth.user?.base_permissions).toEqual(['product.edit'])

    vi.mocked(updateProfile).mockResolvedValueOnce(user(['product.edit', 'product.view']))
    await auth.saveProfile({ username: 'tester', email: 'tester@example.test' })
    expect(auth.permissionSnapshotState).toBe('ready')
    expect(auth.user?.base_permissions).toEqual(['product.edit', 'product.view'])

    vi.mocked(getCurrentUser).mockRejectedValueOnce(new Error('offline'))
    await expect(auth.refreshBasePermissions()).rejects.toThrow('offline')
    expect(auth.permissionSnapshotState).toBe('unavailable')
  })
})
