import { ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Employee } from '@/api/generated/types.gen'
import type { ManagedUser } from '@/services/security'
import { loadLookupPages } from '@/services/listLookups'
import { emptyEmployeeForm } from '../model'
import { useEmployeeUserLookup } from '../useEmployeeUserLookup'

vi.mock('@/services/listLookups', () => ({ loadLookupPages: vi.fn() }))

const user = (id: string, username: string, name?: string, email?: string): ManagedUser => ({
  id,
  username,
  name,
  email: email ?? null,
  status: 'ACTIVE',
  active: true,
  created_at: null,
  groups: [],
})
const employee = (id: string, userId?: string): Employee =>
  ({
    id,
    employee_code: `E-${id}`,
    employee_name: `Employee ${id}`,
    user_id: userId,
  }) as unknown as Employee

beforeEach(() => vi.mocked(loadLookupPages).mockReset())

describe('Employee User picker', () => {
  it('shows searchable labels and excludes Users linked to other Employees', async () => {
    vi.mocked(loadLookupPages).mockResolvedValue([
      user('99', 'linked'),
      user('101', 'alice', 'Alice Smith', 'alice@example.com'),
      user('102', 'bob'),
    ])
    const lookups = useEmployeeUserLookup(
      ref(emptyEmployeeForm()),
      ref<string | undefined>(),
      ref([employee('1', '99')]),
    )
    await lookups.loadUserLookup()
    expect(lookups.userSelectOptions.value).toEqual([
      { value: '101', label: 'alice · Alice Smith · alice@example.com · ID: 101' },
      { value: '102', label: 'bob · ID: 102' },
    ])
    expect(lookups.userLookupError.value).toBe(false)
  })

  it('preserves the Employee own linked User and a selected ID hidden by authorization', async () => {
    vi.mocked(loadLookupPages).mockResolvedValue([user('99', 'linked'), user('100', 'taken')])
    const form = ref(emptyEmployeeForm())
    form.value.user_id = '99'
    const lookups = useEmployeeUserLookup(
      form,
      ref('1'),
      ref([employee('1', '99'), employee('2', '100')]),
    )
    await lookups.loadUserLookup()
    expect(lookups.userSelectOptions.value).toEqual([{ value: '99', label: 'linked · ID: 99' }])

    form.value.user_id = '99999999999999999999'
    expect(lookups.userSelectOptions.value[0]).toEqual({
      value: '99999999999999999999',
      label: 'User ID: 99999999999999999999',
    })
  })

  it('reports lookup failure without erasing an existing association', async () => {
    // Reject after loadUserLookup() has attached its error handler, like an API response.
    let rejectLookup!: (error: Error) => void
    const pendingPage = new Promise<ManagedUser[]>((_, reject) => {
      rejectLookup = reject
    })
    vi.mocked(loadLookupPages).mockReturnValueOnce(pendingPage)
    const form = ref(emptyEmployeeForm())
    form.value.user_id = '77'
    const lookups = useEmployeeUserLookup(form, ref('1'), ref<Employee[]>([]))
    const pendingLoad = lookups.loadUserLookup()
    expect(lookups.userLookupLoading.value).toBe(true)
    rejectLookup(new Error('Forbidden'))
    await expect(pendingLoad).resolves.toBeUndefined()
    expect(lookups.userLookupLoading.value).toBe(false)
    expect(lookups.userLookupError.value).toBe(true)
    expect(lookups.userSelectOptions.value).toEqual([{ value: '77', label: 'User ID: 77' }])
  })

  it('ignores stale responses after a modal closes or a newer lookup starts', async () => {
    let resolveOld!: (users: ManagedUser[]) => void
    const older = new Promise<ManagedUser[]>((resolve) => {
      resolveOld = resolve
    })
    vi.mocked(loadLookupPages).mockReturnValueOnce(older).mockResolvedValueOnce([user('2', 'new')])
    const lookups = useEmployeeUserLookup(
      ref(emptyEmployeeForm()),
      ref<string | undefined>(),
      ref<Employee[]>([]),
    )
    const pending = lookups.loadUserLookup()
    lookups.resetUserLookup()
    await lookups.loadUserLookup()
    resolveOld([user('1', 'old')])
    await pending
    expect(lookups.userSelectOptions.value).toEqual([{ value: '2', label: 'new · ID: 2' }])
    expect(lookups.userLookupLoading.value).toBe(false)
  })
})
