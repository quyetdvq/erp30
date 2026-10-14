import type { ResourceStateOption } from '@/services/security'
import type { PolicyDraft } from './types'

/** Treat a missing selected state as a blocking error, never broaden to ALL_STATES. */
export function policyHasUnavailableState(
  draft: PolicyDraft,
  availableStates: readonly ResourceStateOption[],
): boolean {
  return Boolean(draft.state_id && !availableStates.some((item) => item.id === draft.state_id))
}

/** Text is presented for API-reported duplicate scopes; it does not enforce client-side uniqueness. */
export function policyScopeSummary(draft: PolicyDraft): string {
  return [
    draft.organization_id,
    draft.resource_id,
    draft.state_id || 'ALL_STATES',
    draft.participant_type,
    draft.participant_ref_id,
  ].join(' / ')
}
