import type { Employee } from '@/api/generated/types.gen'
import { wouldCreateManagerCycle } from '@/modules/employeeRules'
import type { AssignmentForm, EmployeeForm } from './types'

/** Return a translation key, keeping V2.2.1 priority and checks unchanged. */
export function employeeManagerErrorKey(
  employees: readonly Employee[],
  form: EmployeeForm,
  editingEmployeeId?: string,
): string | null {
  if (form.manager_employee_id === editingEmployeeId) return 'people.managerSelfError'
  if (wouldCreateManagerCycle(employees, editingEmployeeId ?? '', form.manager_employee_id))
    return 'people.managerCycleError'
  const selectedManager = employees.find((row) => String(row.id) === form.manager_employee_id)
  if (selectedManager && selectedManager.status !== 'ACTIVE') return 'people.managerInactiveError'
  return null
}

export function assignmentErrorKey(overlap: boolean, form: AssignmentForm): string | null {
  if (overlap) return 'people.primaryOverlap'
  if (!form.organization_id) return 'people.organizationRequired'
  return null
}
