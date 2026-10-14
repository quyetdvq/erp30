import type { Organization, Policy, Resource } from '@/api/generated/types.gen'
import type { PolicyDraft } from './types'

export function initialPolicyDraft(): PolicyDraft {
  return {
    organization_id: '',
    resource_id: '',
    state_id: '',
    participant_type: 'USER',
    participant_ref_id: '',
    actions: [],
  }
}

export function createPolicyDraft(
  organizations: readonly Organization[],
  resources: readonly Resource[],
): PolicyDraft {
  return {
    organization_id: organizations[0] ? String(organizations[0].id) : '',
    resource_id: resources[0] ? String(resources[0].id) : '',
    state_id: '',
    participant_type: 'USER',
    participant_ref_id: '',
    actions: [],
  }
}

export function policyToDraft(policy: Policy): PolicyDraft {
  return {
    organization_id: String(policy.organization_id),
    resource_id: String(policy.resource_id),
    state_id: policy.state_id ? String(policy.state_id) : '',
    participant_type: policy.participant_type,
    participant_ref_id: String(policy.participant_ref_id),
    actions: policy.actions.map((action) => ({
      resource_action_id: String(action.resource_action_id),
      effect: action.effect,
    })),
  }
}
