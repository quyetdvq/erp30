import { computed, ref, type Ref } from 'vue'
import type { Employee } from '@/api/generated/types.gen'
import { listSecurityUserPage, type ManagedUser } from '@/services/security'
import { loadLookupPages } from '@/services/listLookups'
import type { EmployeeForm } from './types'

/** User picker uses the existing, user.view-authorized Security Users endpoint. */
export function useEmployeeUserLookup(
  employeeForm: Ref<EmployeeForm>,
  editingEmployeeId: Ref<string | undefined>,
  employees: Ref<Employee[]>,
) {
  const users = ref<ManagedUser[]>([])
  const userLookupLoading = ref(false)
  const userLookupError = ref(false)
  let requestId = 0

  const userSelectOptions = computed(() => {
    // A User can belong to only one Employee. The backend unique check is authoritative.
    const linkedIds = new Set(
      employees.value
        .filter((row) => String(row.id) !== editingEmployeeId.value && row.user_id)
        .map((row) => String(row.user_id)),
    )
    const options = users.value
      .filter((user) => !linkedIds.has(String(user.id)))
      .map((user) => ({
        value: String(user.id),
        label: [user.username, user.name, user.email, `ID: ${String(user.id)}`]
          .filter((part) => typeof part === 'string' && part.trim().length > 0)
          .join(' · '),
      }))

    // Editing must not silently discard an existing link outside the visible PBAC scope.
    const selectedId = employeeForm.value.user_id
    if (selectedId && !options.some((option) => option.value === selectedId)) {
      options.unshift({ value: selectedId, label: `User ID: ${selectedId}` })
    }
    return options
  })

  function resetUserLookup(): void {
    requestId += 1
    users.value = []
    userLookupLoading.value = false
    userLookupError.value = false
  }

  async function loadUserLookup(): Promise<void> {
    const currentRequest = ++requestId
    users.value = []
    userLookupLoading.value = true
    userLookupError.value = false
    try {
      const visibleUsers = await loadLookupPages(listSecurityUserPage, { sort: 'username' })
      if (currentRequest === requestId) users.value = visibleUsers
    } catch {
      if (currentRequest === requestId) userLookupError.value = true
    } finally {
      if (currentRequest === requestId) userLookupLoading.value = false
    }
  }

  return {
    userSelectOptions,
    userLookupLoading,
    userLookupError,
    loadUserLookup,
    resetUserLookup,
  }
}
