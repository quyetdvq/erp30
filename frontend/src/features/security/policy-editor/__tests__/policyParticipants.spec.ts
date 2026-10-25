import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { mount } from '@vue/test-utils'
import type {
  AuthorizationGroup,
  Department,
  Organization,
  Position,
} from '@/api/generated/types.gen'
import type { ManagedUser } from '@/services/security'
import { initialPolicyDraft } from '../model'
import { usePolicyParticipants } from '../usePolicyParticipants'

const mocks = vi.hoisted(() => ({
  listSecurityUserPage: vi.fn<(query: unknown) => Promise<{ data: ManagedUser[] }>>(),
  listAuthorizationGroupPage: vi.fn(),
  reportApiError: vi.fn<(error: unknown) => void>(),
}))
vi.mock('@/services/security', () => ({
  listSecurityUserPage: mocks.listSecurityUserPage,
  listAuthorizationGroupPage: mocks.listAuthorizationGroupPage,
}))
vi.mock('@/services/errorBoundary', () => ({ reportApiError: mocks.reportApiError }))
vi.mock('@/api/client', () => ({ withTypedClient: vi.fn<(...args: unknown[]) => unknown>() }))
vi.mock('@/api/generated/sdk.gen', () => ({
  listDepartments: vi.fn<(...args: unknown[]) => unknown>(),
  listOrganizations: vi.fn<(...args: unknown[]) => unknown>(),
  listPositions: vi.fn<(...args: unknown[]) => unknown>(),
}))
vi.mock('@/i18n', () => ({ i18n: { global: { locale: { value: 'vi' } } } }))

function createHarness() {
  const form = ref(initialPolicyDraft())
  const error = ref<unknown | null>(null)
  const organizations = ref<Organization[]>([])
  const groups = ref<AuthorizationGroup[]>([])
  const users = ref<ManagedUser[]>([])
  const departments = ref<Department[]>([])
  const positions = ref<Position[]>([])
  let policy!: ReturnType<typeof usePolicyParticipants>
  const wrapper = mount(
    defineComponent({
      setup() {
        policy = usePolicyParticipants(form, error, {
          organizations,
          groups,
          users,
          departments,
          positions,
        })
        return () => h('div')
      },
    }),
  )
  return { form, error, groups, users, policy, wrapper }
}

function deferred() {
  let resolve!: (value: { data: ManagedUser[] }) => void
  const promise = new Promise<{ data: ManagedUser[] }>((fulfill) => {
    resolve = fulfill
  })
  return { promise, resolve }
}

describe('PBAC participant selector (V2.4)', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('GROUP reuses cached group catalog and supports remote search beyond page one', async () => {
    const h = createHarness()
    h.groups.value = [{ id: '25', code: 'OPS', name: 'Operations' } as AuthorizationGroup]
    h.form.value.participant_type = 'GROUP'
    await h.policy.loadParticipantOptions('GROUP', '', true)
    expect(h.policy.isRemoteParticipant.value).toBe(true)
    expect(h.policy.participantSelectOptions.value).toEqual([
      { value: '25', label: 'OPS · Operations' },
    ])
    expect(h.form.value.participant_ref_id).toBe('25')
    expect(mocks.listSecurityUserPage).not.toHaveBeenCalled()
    h.wrapper.unmount()
  })

  it('GROUP supports remote searching beyond the cached first page', async () => {
    const h = createHarness()
    h.groups.value = [{ id: '25', code: 'OPS', name: 'Operations' } as AuthorizationGroup]
    h.form.value.participant_type = 'GROUP'
    mocks.listAuthorizationGroupPage.mockResolvedValue({
      data: [{ id: '300', code: 'REMOTE', name: 'Remote group' }],
    })
    await h.policy.loadParticipantOptions('GROUP', 'remote')
    expect(mocks.listAuthorizationGroupPage).toHaveBeenCalledTimes(1)
    expect(h.policy.participantSelectOptions.value).toEqual([
      { value: '300', label: 'REMOTE · Remote group' },
    ])
    h.wrapper.unmount()
  })

  it('USER default options load one page only when no catalog was already fetched', async () => {
    const h = createHarness()
    mocks.listSecurityUserPage.mockResolvedValue({
      data: [{ id: '30', username: 'admin', name: 'Admin' } as ManagedUser],
    })
    await h.policy.loadParticipantOptions('USER', '', true)
    expect(mocks.listSecurityUserPage).toHaveBeenCalledTimes(1)
    expect(h.form.value.participant_ref_id).toBe('30')
    h.wrapper.unmount()
  })

  it('remote USER search protects the newer selection from stale results', async () => {
    const h = createHarness()
    const older = deferred()
    const newer = deferred()
    mocks.listSecurityUserPage.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise)
    const oldRequest = h.policy.loadParticipantOptions('USER', 'old')
    const newRequest = h.policy.loadParticipantOptions('USER', 'new')
    newer.resolve({ data: [{ id: '3', username: 'newuser', name: 'New' } as ManagedUser] })
    await newRequest
    older.resolve({ data: [{ id: '4', username: 'olduser', name: 'Old' } as ManagedUser] })
    await oldRequest
    expect(h.policy.participantSelectOptions.value).toEqual([
      { value: '3', label: 'newuser · New' },
    ])
    expect(h.policy.participantLoading.value).toBe(false)
    expect(h.policy.currentParticipantRequestId()).toBe(2)
    h.wrapper.unmount()
  })

  it('keeps a previously selected participant visible as its #ID when a page omits it', async () => {
    const h = createHarness()
    h.form.value.participant_ref_id = 'missing'
    h.users.value = [{ id: '2', username: 'two', name: 'Two' } as ManagedUser]
    await h.policy.loadParticipantOptions('USER', '', false)
    expect(h.form.value.participant_ref_id).toBe('missing')
    expect(h.policy.participantSelectOptions.value[0]).toEqual({
      value: 'missing',
      label: '#missing',
    })
    h.wrapper.unmount()
  })

  it('changing participant type clears the previous ID and defaults from new options', async () => {
    const h = createHarness()
    h.groups.value = [{ id: '15', code: 'SEC', name: 'Security' } as AuthorizationGroup]
    h.form.value.participant_ref_id = 'old'
    h.form.value.participant_type = 'GROUP'
    h.policy.onParticipantTypeChange('GROUP')
    await Promise.resolve()
    expect(h.form.value.participant_ref_id).toBe('15')
    expect(h.policy.currentParticipantRequestId()).toBe(1)
    h.wrapper.unmount()
  })

  it('discards search results when the participant type changed while fetching', async () => {
    const h = createHarness()
    const older = deferred()
    mocks.listSecurityUserPage.mockReturnValue(older.promise)
    const inFlight = h.policy.loadParticipantOptions('USER', 'x')
    h.form.value.participant_type = 'GROUP'
    older.resolve({ data: [{ id: '4', username: 'x', name: 'X' } as ManagedUser] })
    await inFlight
    expect(h.policy.participantOptions.value).toEqual([])
    h.wrapper.unmount()
  })
})
