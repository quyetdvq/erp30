import { describe, expect, it } from 'vitest'
import type { Organization, Policy, Resource } from '@/api/generated/types.gen'
import { createPolicyDraft, initialPolicyDraft, policyToDraft } from '../model'
import { policyPayload } from '../payload'
import { effects, participantTypes } from '../types'
import { policyHasUnavailableState, policyScopeSummary } from '../validation'

const activePolicy: Policy = {
  id: '9007199254740993',
  organization_id: '9007199254740995',
  resource_id: '9007199254740997',
  state_id: '9007199254740999',
  participant_type: 'GROUP',
  participant_ref_id: '9007199254741001',
  status: 'ACTIVE',
  actions: [
    { resource_action_id: '100', effect: 'ALLOW' },
    { resource_action_id: '101', effect: 'DENY' },
    { resource_action_id: '102', effect: 'ABSOLUTE_DENY' },
  ],
}

const asOrganization = (id: string) => ({ id }) as unknown as Organization
const asResource = (id: string) => ({ id }) as unknown as Resource

describe('Policy form mapping and API contract (V2.4)', () => {
  it('preserves the original blank USER draft and zero actions', () => {
    expect(initialPolicyDraft()).toEqual({
      organization_id: '',
      resource_id: '',
      state_id: '',
      participant_type: 'USER',
      participant_ref_id: '',
      actions: [],
    })
  })

  it('selects only the first organization and resource for a new draft', () => {
    expect(
      createPolicyDraft([asOrganization('22'), asOrganization('23')], [asResource('44')]),
    ).toMatchObject({ organization_id: '22', resource_id: '44', state_id: '' })
    expect(createPolicyDraft([], [])).toMatchObject({ organization_id: '', resource_id: '' })
  })

  it('keeps BIGINT identifiers as strings and effect ordering during edit', () => {
    const draft = policyToDraft(activePolicy)
    expect(draft.organization_id).toBe(activePolicy.organization_id)
    expect(draft.resource_id).toBe(activePolicy.resource_id)
    expect(draft.participant_ref_id).toBe(activePolicy.participant_ref_id)
    expect(draft.state_id).toBe(activePolicy.state_id)
    expect(draft.actions).toEqual(activePolicy.actions)
    expect(draft.actions).not.toBe(activePolicy.actions)
  })

  it('maps NULL existing State to All States without changing the participant', () => {
    const draft = policyToDraft({ ...activePolicy, state_id: null })
    expect(draft.state_id).toBe('')
    expect(draft.participant_type).toBe('GROUP')
  })

  it('sends explicit null when clearing State on UPDATE and CREATE', () => {
    const body = policyPayload({ ...policyToDraft(activePolicy), state_id: '' })
    expect(body).toHaveProperty('state_id', null)
  })

  it('preserves state ID exactly when editing State-specific policy', () => {
    expect(policyPayload(policyToDraft(activePolicy)).state_id).toBe(activePolicy.state_id)
  })

  it('preserves ALLOW, DENY and ABSOLUTE_DENY as distinct effects in order', () => {
    const body = policyPayload(policyToDraft(activePolicy))
    expect(effects).toEqual(['ALLOW', 'DENY', 'ABSOLUTE_DENY'])
    expect(body.actions.map(({ effect }) => effect)).toEqual(effects)
    expect(body.actions.map(({ resource_action_id }) => resource_action_id)).toEqual([
      '100',
      '101',
      '102',
    ])
  })

  it('never sends status or server-managed policy ID in save payload', () => {
    const body = policyPayload(policyToDraft(activePolicy))
    expect(body).not.toHaveProperty('status')
    expect(body).not.toHaveProperty('id')
  })

  it('keeps the same actions array (including duplicates), deferring rejection to backend', () => {
    const draft = policyToDraft(activePolicy)
    draft.actions.push({ ...draft.actions[0]! })
    expect(policyPayload(draft).actions).toBe(draft.actions)
    expect(policyPayload(draft).actions).toHaveLength(4)
  })

  it('does not add client-side policy denial or change available participant types', () => {
    expect(participantTypes).toEqual(['USER', 'GROUP', 'POSITION', 'DEPARTMENT', 'ORGANIZATION'])
  })

  it('blocks a state-specific policy if the state is no longer offered', () => {
    const draft = policyToDraft(activePolicy)
    expect(policyHasUnavailableState(draft, [{ id: '9', code: 'DRAFT', name: 'Draft' }])).toBe(true)
    expect(
      policyHasUnavailableState(draft, [{ id: draft.state_id, code: 'ACTIVE', name: 'Active' }]),
    ).toBe(false)
  })

  it('does not block an All States policy merely because the catalog is empty', () => {
    expect(policyHasUnavailableState({ ...policyToDraft(activePolicy), state_id: '' }, [])).toBe(
      false,
    )
  })

  it('keeps the exact five-part duplicate scope summary and All States token', () => {
    expect(policyScopeSummary(policyToDraft(activePolicy))).toBe(
      '9007199254740995 / 9007199254740997 / 9007199254740999 / GROUP / 9007199254741001',
    )
    expect(policyScopeSummary({ ...policyToDraft(activePolicy), state_id: '' })).toContain(
      ' / ALL_STATES / ',
    )
  })
})
