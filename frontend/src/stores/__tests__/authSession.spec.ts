import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'
import { useDisplaySettingsStore } from '@/stores/displaySettings'
import { getCurrentUser, login, logout } from '@/services/auth'
import type { AuthUser } from '@/services/auth'

vi.mock('@/services/auth', () => ({
  getCurrentUser: vi.fn<typeof getCurrentUser>(),
  login: vi.fn<typeof login>(),
  logout: vi.fn<typeof logout>(),
  updateProfile: vi.fn<typeof import('@/services/auth').updateProfile>(),
}))

function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => {
    resolve = done
  })
  return { promise, resolve }
}
const sampleUser = (id: number): AuthUser => ({
  id,
  username: `user${id}`,
  email: `user${id}@example.test`,
  active: true,
  status: 'ACTIVE',
  groups: [],
  base_permissions: ['product.view'],
})

beforeEach(() => {
  setActivePinia(createPinia())
  // clearAllMocks retains queued mockReturnValueOnce implementations across tests.
  // Reset those queues so an old user's response cannot leak into the next test.
  vi.resetAllMocks()
  vi.mocked(logout).mockResolvedValue()
  window.localStorage.clear()
})

describe('session-bound async responses', () => {
  it('cannot resurrect a user after logout while hydrate was pending', async () => {
    const request = deferred<AuthUser>()
    vi.mocked(getCurrentUser).mockReturnValue(request.promise)
    const auth = useAuthStore()
    const hydrate = auth.hydrate()
    await auth.logout()
    request.resolve(sampleUser(1))
    await hydrate
    expect(auth.user).toBeNull()
    expect(auth.permissionSnapshotState).toBe('unavailable')
    expect(useWorkspaceStore().activeUserId).toBeNull()
  })

  it('coalesces permission refresh and rejects old snapshots after a user switches', async () => {
    vi.mocked(login).mockResolvedValueOnce(sampleUser(1)).mockResolvedValueOnce(sampleUser(2))
    const auth = useAuthStore()
    await auth.login('first@example.test', 'password', false)
    const old = deferred<AuthUser>()
    vi.mocked(getCurrentUser).mockReturnValue(old.promise)
    const a = auth.refreshBasePermissions()
    const b = auth.refreshBasePermissions()
    // Pinia wraps returned promises, so referential equality is not guaranteed.
    // Deduplication is proven by the single underlying GET /auth/me call.
    expect(getCurrentUser).toHaveBeenCalledTimes(1)
    await auth.logout()
    await auth.login('second@example.test', 'password', false)
    old.resolve(sampleUser(1))
    await Promise.all([a, b])
    expect(auth.user?.id).toBe(2)
    expect(auth.permissionSnapshotState).toBe('ready')
    expect(useWorkspaceStore().activeUserId).toBe('2')
  })

  it('forces a fresh permission snapshot after an administrative permission change', async () => {
    vi.mocked(login).mockResolvedValueOnce(sampleUser(1))
    const auth = useAuthStore()
    await auth.login('first@example.test', 'password', false)
    const old = deferred<AuthUser>()
    vi.mocked(getCurrentUser)
      .mockReturnValueOnce(old.promise)
      .mockResolvedValueOnce({
        ...sampleUser(1),
        base_permissions: ['product.edit'],
      })
    const beforeMutation = auth.refreshBasePermissions()
    await auth.refreshBasePermissions(true)
    old.resolve(sampleUser(1))
    await beforeMutation
    expect(auth.user?.base_permissions).toEqual(['product.edit'])
    expect(auth.permissionSnapshotState).toBe('ready')
    expect(getCurrentUser).toHaveBeenCalledTimes(2)
  })

  it('does not let a late display-settings load restore data after sign-out', async () => {
    const displaySettings = useDisplaySettingsStore()
    // Logout must invalidate an existing user's local presentation state too.
    displaySettings.activateUser(99)
    const auth = useAuthStore()
    await auth.logout()
    expect(displaySettings.activeUserId).toBeNull()
    expect(displaySettings.loaded).toBe(false)
  })
})
