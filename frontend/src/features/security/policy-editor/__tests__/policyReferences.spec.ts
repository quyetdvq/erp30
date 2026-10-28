import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import type { Policy, Resource } from '@/api/generated/types.gen'
import type { SecurityAction } from '@/services/security'
import { initialPolicyDraft } from '../model'
import { usePolicyReferences } from '../usePolicyReferences'

const mocks = vi.hoisted(() => ({
  getResourceActions: vi.fn<(id: string) => Promise<SecurityAction[]>>(),
  listResourcePage: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  listAuthorizationGroupPage: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  listSecurityUserPage: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  withTypedClient: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
}))
vi.mock('@/services/security', () => ({
  getResourceActions: mocks.getResourceActions,
  listAuthorizationGroupPage: mocks.listAuthorizationGroupPage,
  listResourcePage: mocks.listResourcePage,
  listSecurityUserPage: mocks.listSecurityUserPage,
}))
vi.mock('@/api/client', () => ({ withTypedClient: mocks.withTypedClient }))
vi.mock('@/api/generated/sdk.gen', () => ({
  listDepartments: vi.fn<(...args: unknown[]) => unknown>(),
  listOrganizations: vi.fn<(...args: unknown[]) => unknown>(),
  listPositions: vi.fn<(...args: unknown[]) => unknown>(),
  listWorkflowStates: vi.fn<(...args: unknown[]) => unknown>(),
}))
vi.mock('@/i18n', () => ({ i18n: { global: { locale: { value: 'vi' } } } }))
vi.mock('@/services/errorBoundary', () => ({
  reportApiError: vi.fn<(...args: unknown[]) => unknown>(),
}))

const asResource = (resource: Record<string, unknown>) => resource as unknown as Resource
const samplePolicy = (actions: Policy['actions']): Policy =>
  ({
    id: '9',
    organization_id: '1',
    resource_id: '2',
    status: 'ACTIVE',
    participant_type: 'USER',
    participant_ref_id: '3',
    actions,
  }) as Policy

describe('PBAC Policy references and effect display (V2.4)', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('filters action choices by the selected resource without coercing ID to number', () => {
    const form = ref({ ...initialPolicyDraft(), resource_id: '9007199254740993' })
    const lookups = usePolicyReferences(form, ref<unknown | null>(null), (key) => key)
    lookups.actionCatalog.value = [
      { id: '12', resource_id: '9007199254740993', code: 'read' },
      { id: '13', resource_id: '9007199254740995', code: 'write' },
    ]
    expect(lookups.actionOptions.value.map(({ id }) => id)).toEqual(['12'])
  })

  it('displays all three effects in stored order, retaining missing-code placeholder', () => {
    const lookups = usePolicyReferences(ref(initialPolicyDraft()), ref(null), (key) => key)
    lookups.actionCatalog.value = [
      { id: '1', resource_id: '2', code: 'list' },
      { id: '2', resource_id: '2', code: 'edit' },
    ]
    expect(
      lookups.effectsSummary(
        samplePolicy([
          { resource_action_id: '1', effect: 'ALLOW' },
          { resource_action_id: '2', effect: 'DENY' },
          { resource_action_id: '3', effect: 'ABSOLUTE_DENY' },
        ]),
      ),
    ).toBe('ALLOW:list, DENY:edit, ABSOLUTE_DENY:list.noValue')
  })

  it('uses the embedded resource actions when available, including an empty action list', async () => {
    const lookups = usePolicyReferences(ref(initialPolicyDraft()), ref(null), (key) => key)
    lookups.resources.value = [asResource({ id: '9', actions: [] })]
    await lookups.loadActions('9')
    expect(lookups.actions.value).toEqual([])
    expect(mocks.getResourceActions).not.toHaveBeenCalled()
  })

  it('fetches actions only if the resource has no embedded list', async () => {
    mocks.getResourceActions.mockResolvedValue([{ id: '10', resource_id: '9', code: 'view' }])
    const lookups = usePolicyReferences(ref(initialPolicyDraft()), ref(null), (key) => key)
    lookups.resources.value = [asResource({ id: '9' })]
    await lookups.loadActions('9')
    expect(mocks.getResourceActions).toHaveBeenCalledWith('9')
    expect(lookups.actions.value[0]?.code).toBe('view')
  })

  it('does not load any catalog for an empty Policy page', () => {
    const lookups = usePolicyReferences(ref(initialPolicyDraft()), ref(null), (key) => key)
    lookups.loadListReferences([])
    expect(mocks.listResourcePage).not.toHaveBeenCalled()
    expect(mocks.withTypedClient).not.toHaveBeenCalled()
  })

  it('loads only references on the visible Policy page and reuses them for the editor', async () => {
    mocks.listResourcePage.mockResolvedValue({ data: [{ id: '2', type: 'policy', actions: [] }] })
    mocks.listAuthorizationGroupPage.mockResolvedValue({ data: [{ id: '25', code: 'OPS', name: 'Ops' }] })
    mocks.withTypedClient.mockResolvedValue({ data: { data: [{ id: '10', name: 'Root' }] } })
    const lookups = usePolicyReferences(ref(initialPolicyDraft()), ref(null), (key) => key)
    lookups.loadListReferences([{
      ...samplePolicy([]), organization_id: '10', participant_type: 'GROUP', participant_ref_id: '25',
    }])
    await vi.waitFor(() => {
      expect(lookups.labelForParticipant({ ...samplePolicy([]), participant_type: 'GROUP', participant_ref_id: '25' })).toBe('Ops')
    })
    expect(mocks.listResourcePage).toHaveBeenCalledTimes(1)
    expect(mocks.listAuthorizationGroupPage).toHaveBeenCalledTimes(1)
    expect(mocks.listSecurityUserPage).not.toHaveBeenCalled()
    expect(mocks.withTypedClient).toHaveBeenCalledTimes(1) // Organization only; no workflow State on this page
    await lookups.loadEditorReferences()
    expect(mocks.listResourcePage).toHaveBeenCalledTimes(1)
    expect(mocks.withTypedClient).toHaveBeenCalledTimes(1)
  })

  it('deduplicates simultaneous lookups and retries a failed reference request', async () => {
    const failure = new Error('temporary lookup error')
    mocks.listResourcePage.mockRejectedValueOnce(failure).mockResolvedValueOnce({ data: [] })
    mocks.withTypedClient.mockResolvedValue({ data: { data: [] } })
    const lookups = usePolicyReferences(ref(initialPolicyDraft()), ref(null), (key) => key)
    const first = await Promise.allSettled([lookups.loadEditorReferences(), lookups.loadEditorReferences()])
    expect(first.map((result) => result.status)).toEqual(['rejected', 'rejected'])
    expect(mocks.listResourcePage).toHaveBeenCalledTimes(1)
    await lookups.loadEditorReferences()
    expect(mocks.listResourcePage).toHaveBeenCalledTimes(2)
    expect(mocks.withTypedClient).toHaveBeenCalledTimes(1)
  })

})
