import type { Employee } from '@/api/generated/types.gen'

export type Gender = 'MALE' | 'FEMALE' | 'OTHER' | null
export type EmployeeDetailTab = 'overview' | 'assignments' | 'contacts' | 'audit'
export type AssignmentMode = 'active' | 'history'

export type EmployeeForm = {
  employee_code: string
  employee_name: string
  user_id: string
  gender: Gender
  date_of_birth: string
  place_of_origin: string
  hire_date: string
  manager_employee_id: string
  status: Employee['status']
  description: string
}

export type AssignmentForm = {
  organization_id: string
  department_id: string
  position_id: string
  is_primary: boolean
  effective_from: string
  effective_to: string
  status: 'ACTIVE' | 'INACTIVE'
  description: string
}
