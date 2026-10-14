import type { Policy, PolicyAction } from '@/api/generated/types.gen'

export type ParticipantType = Policy['participant_type']
export type PolicyDraftAction = { resource_action_id: string; effect: PolicyAction['effect'] }
export type ParticipantOption = { id: string; label: string }

export type PolicyDraft = {
  organization_id: string
  resource_id: string
  state_id: string
  participant_type: ParticipantType
  participant_ref_id: string
  actions: PolicyDraftAction[]
}

export const participantTypes: ParticipantType[] = [
  'USER',
  'GROUP',
  'POSITION',
  'DEPARTMENT',
  'ORGANIZATION',
]
export const effects: PolicyAction['effect'][] = ['ALLOW', 'DENY', 'ABSOLUTE_DENY']
