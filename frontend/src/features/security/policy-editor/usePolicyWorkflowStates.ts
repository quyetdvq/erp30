import { computed, ref, type Ref } from 'vue'
import { getResourceAvailableStates, type ResourceStateOption } from '@/services/security'
import { reportApiError } from '@/services/errorBoundary'
import { policyHasUnavailableState } from './validation'
import type { PolicyDraft } from './types'

/** State-specific policies must never be silently rewritten as ALL_STATES on lookup failure. */
export function usePolicyWorkflowStates(form: Ref<PolicyDraft>, error: Ref<unknown | null>) {
  const resourceStateOptions = ref<ResourceStateOption[]>([])
  const workflowStatesLoading = ref(false)
  const workflowStatesUnavailable = ref(false)
  let workflowStateRequestId = 0
  const workflowStateOptions = computed(() =>
    resourceStateOptions.value.map((item) => ({
      id: item.id,
      label: `${item.code} · ${item.name}`,
    })),
  )

  async function loadWorkflowStates(resourceId: string): Promise<void> {
    const requestId = ++workflowStateRequestId
    resourceStateOptions.value = []
    workflowStatesUnavailable.value = false
    workflowStatesLoading.value = true
    try {
      const result = resourceId
        ? await getResourceAvailableStates(resourceId)
        : { mode: 'STATELESS' as const, states: [] }
      if (requestId !== workflowStateRequestId) return
      resourceStateOptions.value = result.states
      if (policyHasUnavailableState(form.value, result.states)) {
        // Preserve an invalid existing selection as a visible validation problem;
        // never silently broaden a state-specific Policy to All States on load.
        workflowStatesUnavailable.value = true
      }
    } catch (caught: unknown) {
      if (requestId === workflowStateRequestId) {
        resourceStateOptions.value = []
        workflowStatesUnavailable.value = true
        error.value = caught
        reportApiError(caught)
      }
    } finally {
      if (requestId === workflowStateRequestId) workflowStatesLoading.value = false
    }
  }

  return {
    workflowStateOptions,
    workflowStatesLoading,
    workflowStatesUnavailable,
    loadWorkflowStates,
  }
}
