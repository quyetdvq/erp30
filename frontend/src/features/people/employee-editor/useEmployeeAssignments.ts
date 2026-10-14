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
  async function loadAssignments(): Promise<void> {
    if (!selectedId.value) {
      selectedEmployeeAssignments.value = []
      return
    }
    try {
      selectedEmployeeAssignments.value = await loadLookupPages(listAssignmentPage, {
        filter: { employee_id: selectedId.value },
      })
    } catch (caught: unknown) {
      selectedEmployeeAssignments.value = []
      reportApiError(caught)
    }
  }
  async function loadAllAssignments(): Promise<void> {
    try {
      assignments.value = await loadLookupPages(listAssignmentPage)
    } catch (caught: unknown) {
      assignments.value = []
      reportApiError(caught)
    }
  }
  return {
    assignments,
    selectedEmployeeAssignments,
    selectedAssignments,
    currentPrimary,
    loadAssignments,
    loadAllAssignments,
  }
}
