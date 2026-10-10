import axios from 'axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const auth = vi.hoisted(() => ({
  markPermissionSnapshotStale: vi.fn<() => void>(),
  refreshBasePermissions: vi.fn<(force?: boolean) => Promise<void>>(() => Promise.resolve()),
  expireSession: vi.fn<() => void>(),
  isAuthenticated: true,
  getSessionGeneration: vi.fn<() => number>(() => 1),
}))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => auth }))
vi.mock('@/services/errorBoundary', () => ({ reportApiError: vi.fn<(error: unknown) => void>() }))

import { apiClient } from '@/services/api'

async function rejectGet(status: number, url = '/api/v1/products'): Promise<void> {
  await expect(
    apiClient.get(url, {
      adapter: async (config) => {
        const failure = new axios.AxiosError('API error', undefined, config)
        failure.response = {
          status,
          statusText: String(status),
          headers: {},
          config,
          data: { error: { code: 'TEST' } },
        }
        throw failure
      },
    }),
  ).rejects.toBeDefined()
}

beforeEach(() => {
  vi.clearAllMocks()
  auth.isAuthenticated = true
  auth.getSessionGeneration.mockReturnValue(1)
})

describe('protected response handling', () => {
  it('does not stale every action merely because a record returns 404', async () => {
    await rejectGet(404)
    expect(auth.markPermissionSnapshotStale).not.toHaveBeenCalled()
    expect(auth.refreshBasePermissions).not.toHaveBeenCalled()
  })

  it('uses only a non-mutating /auth/me refresh after a 403', async () => {
    await rejectGet(403)
    expect(auth.markPermissionSnapshotStale).toHaveBeenCalledOnce()
    expect(auth.refreshBasePermissions).toHaveBeenCalledOnce()
  })

  it('ignores a late 401 from the previous authenticated session', async () => {
    let signalAdapterStarted!: () => void
    let releaseAdapter!: () => void
    const started = new Promise<void>((resolve) => {
      signalAdapterStarted = resolve
    })
    const released = new Promise<void>((resolve) => {
      releaseAdapter = resolve
    })
    const request = apiClient
      .get('/api/v1/products', {
        adapter: async (config) => {
          signalAdapterStarted()
          await released
          const failure = new axios.AxiosError('Unauthorized', undefined, config)
          failure.response = {
            status: 401,
            statusText: 'Unauthorized',
            headers: {},
            config,
            data: {},
          }
          throw failure
        },
      })
      .catch(() => undefined)
    await started
    auth.getSessionGeneration.mockReturnValue(2) // A different user has logged in.
    releaseAdapter()
    await request
    expect(auth.expireSession).not.toHaveBeenCalled()
  })

  it('expires the client session when a protected operation returns 401', async () => {
    await rejectGet(401)
    expect(auth.expireSession).toHaveBeenCalledOnce()
  })
})
