import type { PolicyCreate } from '@/api/generated/types.gen'
import type { PolicyDraft } from './types'

/** Preserve ALL_STATES as explicit null, even on PATCH (omission retains the old state). */
export function policyPayload(form: PolicyDraft): PolicyCreate {
  return {
    organization_id: form.organization_id,
    resource_id: form.resource_id,
    // Explicit null clears a State when editing; omission would retain the old scope.
    state_id: form.state_id || null,
    participant_type: form.participant_type,
    participant_ref_id: form.participant_ref_id,
    actions: form.actions,
  }
}
