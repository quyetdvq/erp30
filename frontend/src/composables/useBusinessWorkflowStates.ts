import { computed, onMounted, ref, watch, type Ref } from 'vue'
import type { ListFilterOption } from '@/components/lists/listTypes'
import { getBusinessWorkflowStates, type BusinessWorkflowStates } from '@/services/businessWorkflow'

/** Metadata is a UI hint, not a permission decision. The API rechecks PBAC. */
export function useBusinessWorkflowStates(
  resource: string | Ref<string>,
  fallback: readonly string[] = ['ACTIVE', 'INACTIVE'],
) {
  const metadata = ref<BusinessWorkflowStates | null>(null)
  const resourceName = computed(() => (typeof resource === 'string' ? resource : resource.value))
  let requestId = 0

  async function refresh(): Promise<void> {
    const current = ++requestId
    metadata.value = null
    try {
      const result = await getBusinessWorkflowStates(resourceName.value)
      if (current === requestId) metadata.value = result
    } catch {
      // The state selector must not block the ordinary authorized list if
      // metadata is unavailable. No mutation uses fallback codes.
    }
  }
  const codes = computed(() => metadata.value?.states ?? [...fallback])
  const initial = computed(() => metadata.value?.initial ?? null)
  const options = computed<readonly ListFilterOption[]>(() =>
    codes.value.map((code) => ({ value: code, labelKey: 'workflow.stateCode', label: code })),
  )
  watch(resourceName, () => void refresh())
  onMounted(() => void refresh())
  return { codes, initial, options, refresh }
}
