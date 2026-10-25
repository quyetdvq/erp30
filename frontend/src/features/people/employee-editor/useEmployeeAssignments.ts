import { computed, ref, type Ref } from 'vue'
import type { EmployeeAssignment } from '@/api/generated/types.gen'
import { listAssignmentPage } from '@/services/employeeStructure'
import { reportApiError } from '@/services/errorBoundary'
import { loadLookupPages } from '@/services/listLookups'
import { today } from './model'
import type { AssignmentMode } from './types'

/** Keeps assignment fetching, selection and current-primary lookups together. */
export function useEmployeeAssignments(
  selectedId: Ref<string | null>,
  assignmentMode: Ref<AssignmentMode>,
) {
  const assignments = ref<EmployeeAssignment[]>([])
  const selectedEmployeeAssignments = ref<EmployeeAssignment[]>([])
  let pageRequestVersion = 0
  let detailRequestVersion = 0
  const selectedAssignments = computed(() =>
    selectedEmployeeAssignments.value.filter((row) =>
      assignmentMode.value === 'active' ? row.status === 'ACTIVE' : row.status !== 'ACTIVE',
    ),
  )
  const currentPrimary = (employeeId: string | number | bigint): EmployeeAssignment | undefined =>
    assignments.value.find(
      (row) =>
        row.status === 'ACTIVE' &&
        row.is_primary &&
        row.effective_from <= today() &&
        (!row.effective_to || row.effective_to >= today()) &&
        String(row.employee_id) === String(employeeId),
    )
  function resetSelectedAssignments(): void {
    ++detailRequestVersion
    selectedEmployeeAssignments.value = []
  }

  /** Fetch assignment history only when its detail tab is opened. */
  async function loadAssignments(): Promise<void> {
    const id = selectedId.value
    const version = ++detailRequestVersion
    if (!id) {
      selectedEmployeeAssignments.value = []
      return
    }
    try {
      const items = await loadLookupPages(listAssignmentPage, { filter: { employee_id: id } })
      if (version === detailRequestVersion && selectedId.value === id) {
        selectedEmployeeAssignments.value = items
      }
    } catch (caught: unknown) {
      if (version === detailRequestVersion) {
        selectedEmployeeAssignments.value = []
        reportApiError(caught)
      }
    }
  }

  /**
   * Table-first projection using the existing PBAC-scoped list endpoint.
   * Never read all assignments of the organization at page entry.
   * The backend's effective_at filter uses the same UTC date as today().
   */
  async function loadPageAssignments(employeeIds: readonly string[]): Promise<void> {
    const version = ++pageRequestVersion
    assignments.value = []
    if (employeeIds.length === 0) return
    try {
      // Cap URL and IN-clause size; 200-row page means at most 4 requests.
      const batches = await Promise.all(Array.from(
        { length: Math.ceil(employeeIds.length / 50) },
        (_, i) => loadLookupPages(listAssignmentPage, {
          filter: {
            employee_id: employeeIds.slice(i * 50, (i + 1) * 50),
            status: 'ACTIVE',
            is_primary: '1',
            effective_at: today(),
          },
        }),
      ))
      if (version === pageRequestVersion) assignments.value = batches.flat()
    } catch (caught: unknown) {
      if (version === pageRequestVersion) {
        assignments.value = []
        reportApiError(caught)
      }
    }
  }

  return {
    assignments,
    selectedEmployeeAssignments,
    selectedAssignments,
    currentPrimary,
    loadAssignments,
    loadPageAssignments,
    resetSelectedAssignments,
  }
}
