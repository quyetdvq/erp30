import type { Employee, EmployeeAssignment } from '@/api/generated/types.gen'
import type { EmployeeForm, AssignmentForm } from './types'

// The old form used UTC's ISO date (not local midnight). Keep that contract.
export const today = () => new Date().toISOString().slice(0, 10)

export function emptyEmployeeForm(): EmployeeForm {
  return {
    employee_code: '',
    employee_name: '',
    user_id: '',
    gender: null,
    date_of_birth: '',
    place_of_origin: '',
    hire_date: '',
    manager_employee_id: '',
    status: 'ACTIVE',
    description: '',
  }
}

export function employeeToForm(row: Employee): EmployeeForm {
  return {
    employee_code: row.employee_code,
    employee_name: row.employee_name,
    user_id: row.user_id ? String(row.user_id) : '',
    gender: row.gender ?? null,
    date_of_birth: row.date_of_birth ?? '',
    place_of_origin: row.place_of_origin ?? '',
    hire_date: row.hire_date ?? '',
    manager_employee_id: row.manager_employee_id ? String(row.manager_employee_id) : '',
    status: row.status,
    description: row.description ?? '',
  }
}

export function emptyAssignmentForm(): AssignmentForm {
  return {
    organization_id: '',
    department_id: '',
    position_id: '',
    is_primary: false,
    effective_from: today(),
    effective_to: '',
    status: 'ACTIVE',
    description: '',
  }
}

export function assignmentToForm(row: EmployeeAssignment): AssignmentForm {
  return {
    organization_id: String(row.organization_id),
    department_id: row.department_id ? String(row.department_id) : '',
    position_id: row.position_id ? String(row.position_id) : '',
    is_primary: row.is_primary,
    effective_from: row.effective_from,
    effective_to: row.effective_to ?? '',
    status: row.status,
    description: row.description ?? '',
  }
}
