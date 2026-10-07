import type { Employee, EmployeeAssignment } from '@/api/generated/types.gen'

export const GENDER_VALUES = ['MALE', 'FEMALE', 'OTHER'] as const

export function wouldCreateManagerCycle(
  employees: readonly Employee[],
  employeeId: string,
  candidateManagerId: string,
): boolean {
  if (!employeeId || !candidateManagerId) return false
  const seen = new Set<string>()
  let cursor = candidateManagerId
  while (cursor) {
    if (cursor === employeeId || seen.has(cursor)) return true
    seen.add(cursor)
    cursor = String(employees.find((row) => String(row.id) === cursor)?.manager_employee_id ?? '')
  }
  return false
}

export function hasPrimaryOverlap(
  assignments: readonly EmployeeAssignment[],
  candidate: Pick<EmployeeAssignment, 'effective_from' | 'effective_to' | 'is_primary'>,
  editingId?: string,
): boolean {
  if (!candidate.is_primary) return false
  const candidateTo = candidate.effective_to || '9999-12-31'
  return assignments.some(
    (row) =>
      String(row.id) !== editingId &&
      row.status === 'ACTIVE' &&
      row.is_primary &&
      row.effective_from <= candidateTo &&
      (!row.effective_to || row.effective_to >= candidate.effective_from),
  )
}
