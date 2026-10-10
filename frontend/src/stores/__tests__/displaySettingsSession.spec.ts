import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useDisplaySettingsStore } from '@/stores/displaySettings'
import { DEFAULT_RUNTIME, fetchDisplaySettingsRuntime } from '@/services/displaySettings'

vi.mock('@/services/displaySettings', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/services/displaySettings')>()
  return {
    ...actual,
    fetchDisplaySettingsRuntime: vi.fn<typeof actual.fetchDisplaySettingsRuntime>(),
  }
})

function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => {
    resolve = done
  })
  return { promise, resolve }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  window.localStorage.clear()
})

describe('Display Settings session isolation', () => {
  it('ignores an old settings response after logout', async () => {
    const pending = deferred<typeof DEFAULT_RUNTIME>()
    vi.mocked(fetchDisplaySettingsRuntime).mockReturnValue(pending.promise)
    const store = useDisplaySettingsStore()
    const loading = store.load(1)
    store.clearSession()
    pending.resolve(DEFAULT_RUNTIME)
    await loading
    expect(store.activeUserId).toBeNull()
    expect(store.loaded).toBe(false)
    expect(store.loading).toBe(false)
  })

  it('keeps the newest user settings when an older request completes last', async () => {
    const first = deferred<typeof DEFAULT_RUNTIME>()
    vi.mocked(fetchDisplaySettingsRuntime)
      .mockReturnValueOnce(first.promise)
      .mockResolvedValueOnce(DEFAULT_RUNTIME)
    const store = useDisplaySettingsStore()
    const old = store.load(1)
    await store.load(2)
    first.resolve(DEFAULT_RUNTIME)
    await old
    expect(store.activeUserId).toBe('2')
    expect(store.loaded).toBe(true)
  })
})
