import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import type { ResourceAvailableStates } from '@/services/security'
import { initialPolicyDraft } from '../model'

const mocks = vi.hoisted(() => ({
  fetchAvailableStates: vi.fn<(id: string) => Promise<ResourceAvailableStates>>(),
  reportApiError: vi.fn<(error: unknown) => void>(),
}))
vi.mock('@/services/security', () => ({ getResourceAvailableStates: mocks.fetchAvailableStates }))
vi.mock('@/services/errorBoundary', () => ({ reportApiError: mocks.reportApiError }))

import { usePolicyWorkflowStates } from '../usePolicyWorkflowStates'

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (error: Error) => void
  const promise = new Promise<T>((fulfill, fail) => {
    resolve = fulfill
    reject = fail
  })
  return { promise, resolve, reject }
}

describe('PBAC workflow State lookup (V2.4)', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('loads allowed State catalog without changing selected State ID', async () => {
    const form = ref({ ...initialPolicyDraft(), state_id: '21' })
    const error = ref<unknown | null>(null)
    mocks.fetchAvailableStates.mockResolvedValue({
      mode: 'LIFECYCLE',
      states: [{ id: '21', code: 'APPROVED', name: 'Approved' }],
    })
    const lookup = usePolicyWorkflowStates(form, error)
    await lookup.loadWorkflowStates('resource-1')
    expect(form.value.state_id).toBe('21')
    expect(lookup.workflowStateOptions.value).toEqual([{ id: '21', label: 'APPROVED · Approved' }])
    expect(lookup.workflowStatesUnavailable.value).toBe(false)
    expect(lookup.workflowStatesLoading.value).toBe(false)
  })

  it('fails closed when the saved State is absent, without silently selecting All States', async () => {
    const form = ref({ ...initialPolicyDraft(), state_id: 'old-state' })
    const error = ref<unknown | null>(null)
    mocks.fetchAvailableStates.mockResolvedValue({ mode: 'NATIVE', states: [] })
    const lookup = usePolicyWorkflowStates(form, error)
    await lookup.loadWorkflowStates('resource-1')
    expect(lookup.workflowStatesUnavailable.value).toBe(true)
    expect(form.value.state_id).toBe('old-state')
  })

  it('does not fetch for a blank resource but keeps a stale selected State visibly invalid', async () => {
    const form = ref({ ...initialPolicyDraft(), state_id: 'stale' })
    const lookup = usePolicyWorkflowStates(form, ref<unknown | null>(null))
    await lookup.loadWorkflowStates('')
    expect(mocks.fetchAvailableStates).not.toHaveBeenCalled()
    expect(lookup.workflowStatesUnavailable.value).toBe(true)
    expect(form.value.state_id).toBe('stale')
  })

  it('keeps failure visible and reports it without modifying the draft', async () => {
    const form = ref({ ...initialPolicyDraft(), state_id: '42' })
    const error = ref<unknown | null>(null)
    const failure = new Error('catalog not available')
    mocks.fetchAvailableStates.mockRejectedValue(failure)
    const lookup = usePolicyWorkflowStates(form, error)
    await lookup.loadWorkflowStates('resource-1')
    expect(lookup.workflowStatesUnavailable.value).toBe(true)
    expect(lookup.workflowStateOptions.value).toEqual([])
    expect(error.value).toBe(failure)
    expect(mocks.reportApiError).toHaveBeenCalledWith(failure)
    expect(form.value.state_id).toBe('42')
  })

  it('ignores a slower response from an earlier resource selection', async () => {
    const first = deferred<{
      mode: 'NATIVE'
      states: { id: string; code: string; name: string }[]
    }>()
    const second = deferred<{
      mode: 'NATIVE'
      states: { id: string; code: string; name: string }[]
    }>()
    mocks.fetchAvailableStates
      .mockReturnValueOnce(first.promise)
      .mockReturnValueOnce(second.promise)
    const form = ref(initialPolicyDraft())
    const lookup = usePolicyWorkflowStates(form, ref<unknown | null>(null))
    const slow = lookup.loadWorkflowStates('old-resource')
    const fast = lookup.loadWorkflowStates('new-resource')
    second.resolve({ mode: 'NATIVE', states: [{ id: '22', code: 'NEW', name: 'New' }] })
    await fast
    first.resolve({ mode: 'NATIVE', states: [{ id: '11', code: 'OLD', name: 'Old' }] })
    await slow
    expect(lookup.workflowStateOptions.value).toEqual([{ id: '22', label: 'NEW · New' }])
    expect(lookup.workflowStatesLoading.value).toBe(false)
  })

  it('resets the unavailable flag when a subsequent lookup succeeds', async () => {
    const form = ref({ ...initialPolicyDraft(), state_id: '42' })
    mocks.fetchAvailableStates.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce({
      mode: 'NATIVE',
      states: [{ id: '42', code: 'READY', name: 'Ready' }],
    })
    const lookup = usePolicyWorkflowStates(form, ref<unknown | null>(null))
    await lookup.loadWorkflowStates('resource-1')
    expect(lookup.workflowStatesUnavailable.value).toBe(true)
    await lookup.loadWorkflowStates('resource-1')
    expect(lookup.workflowStatesUnavailable.value).toBe(false)
  })
})
