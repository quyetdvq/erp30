import { describe, expect, it } from 'vitest'

import type { Employee, EmployeeAssignment } from '@/api/generated/types.gen'
import { hasPrimaryOverlap, wouldCreateManagerCycle } from '@/modules/employeeRules'

const employee = (id: string, manager_employee_id?: string): Employee => ({
  id: id as unknown as string,
  employee_code: `E-${id}`,
  employee_name: `Employee ${id}`,
  status: 'ACTIVE',
    ...(manager_employee_id ? { manager_employee_id: manager_employee_id as unknown as string } : {}),
})

const assignment = (
  id: string,
  from: string,
  to: string | null,
  primary = true,
): EmployeeAssignment => ({
  id: id as unknown as string,
  employee_id: '10' as unknown as string,
  organization_id: '20' as unknown as string,
  is_primary: primary,
  effective_from: from,
  effective_to: to,
  status: 'ACTIVE',
})

describe('employee invariants', () => {
  it('rejects self and reciprocal manager cycles while allowing an unrelated manager', () => {
    const employees = [employee('1', '2'), employee('2')]
    expect(wouldCreateManagerCycle(employees, '1', '1')).toBe(true)
    expect(wouldCreateManagerCycle(employees, '2', '1')).toBe(true)
    expect(wouldCreateManagerCycle(employees, '1', '2')).toBe(false)
  })

  it('detects only overlapping primary periods and permits secondary assignments', () => {
    const existing = [assignment('1', '2026-01-01', '2026-06-30')]
    expect(hasPrimaryOverlap(existing, assignment('new', '2026-06-30', null))).toBe(true)
    expect(hasPrimaryOverlap(existing, assignment('new', '2026-07-01', null))).toBe(false)
    expect(hasPrimaryOverlap(existing, assignment('new', '2026-03-01', null, false))).toBe(false)
  })
})
