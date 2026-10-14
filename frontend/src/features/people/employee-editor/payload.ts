import type {
  EmployeeAssignmentCreate,
  EmployeeAssignmentPatch,
  EmployeeCreate,
} from '@/api/generated/types.gen'
import type { AssignmentForm, EmployeeForm } from './types'

/** Preserve the original nullable fields and omission of user_id when blank. */
export function employeePayload(form: EmployeeForm): EmployeeCreate {
  const payload = {
    employee_code: form.employee_code,
    employee_name: form.employee_name,
    gender: form.gender,
    ...(form.user_id ? { user_id: form.user_id } : {}),
    ...(form.date_of_birth ? { date_of_birth: form.date_of_birth } : { date_of_birth: null }),
    ...(form.place_of_origin
      ? { place_of_origin: form.place_of_origin }
      : { place_of_origin: null }),
    ...(form.hire_date ? { hire_date: form.hire_date } : { hire_date: null }),
    ...(form.manager_employee_id
      ? { manager_employee_id: form.manager_employee_id }
      : { manager_employee_id: null }),
    ...(form.description ? { description: form.description } : { description: null }),
  }
  // Keep the existing wire-format cast: generated BigInt types are not used for
  // converting the original form's string IDs to numbers.
  return payload as unknown as EmployeeCreate
}

/** Existing PATCH omits status and employee_id; CREATE includes both. */
export function assignmentPayload(
  form: AssignmentForm,
  employeeId: string,
  editingAssignmentId?: string,
): EmployeeAssignmentPatch | EmployeeAssignmentCreate {
  const base = {
    organization_id: form.organization_id,
    ...(form.department_id ? { department_id: form.department_id } : {}),
    ...(form.position_id ? { position_id: form.position_id } : {}),
    is_primary: form.is_primary,
    effective_from: form.effective_from,
    effective_to: form.effective_to || null,
    ...(form.description ? { description: form.description } : { description: null }),
  }
  return editingAssignmentId
    ? (base as EmployeeAssignmentPatch)
    : ({
        ...base,
        employee_id: employeeId,
        status: form.status,
      } as EmployeeAssignmentCreate)
}
