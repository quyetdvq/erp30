import { describe, expect, it } from 'vitest'
import type { Employee, EmployeeAssignment } from '@/api/generated/types.gen'
import { hasPrimaryOverlap } from '@/modules/employeeRules'
import {
  assignmentToForm,
  emptyAssignmentForm,
  emptyEmployeeForm,
  employeeToForm,
  today,
} from '../model'
import { assignmentPayload, employeePayload } from '../payload'
import { assignmentErrorKey, employeeManagerErrorKey } from '../validation'

describe('Employees V2.3 — form, wire format and validation contracts', () => {
  const employee = (overrides: Record<string, unknown> = {}): Employee =>
    ({
      id: '10',
      employee_code: 'E10',
      employee_name: 'Employee Ten',
      status: 'ACTIVE',
      ...overrides,
    }) as unknown as Employee

  const assignment = (overrides: Record<string, unknown> = {}): EmployeeAssignment =>
    ({
      id: '20',
      employee_id: '10',
      organization_id: '1',
      is_primary: true,
      effective_from: '2025-01-01',
      effective_to: null,
      status: 'ACTIVE',
      ...overrides,
    }) as unknown as EmployeeAssignment

  it('creates independent forms with the original defaults', () => {
    const first = emptyEmployeeForm()
    const second = emptyEmployeeForm()
    first.employee_name = 'mutated'
    expect(second.employee_name).toBe('')
    expect(second.gender).toBeNull()
    expect(second.status).toBe('ACTIVE')
    expect(emptyAssignmentForm()).toMatchObject({
      organization_id: '',
      is_primary: false,
      status: 'ACTIVE',
      effective_to: '',
    })
  })

  it('keeps the UTC ISO day convention from V2.2.1', () => {
    expect(today()).toBe(new Date().toISOString().slice(0, 10))
    expect(emptyAssignmentForm().effective_from).toBe(today())
  })

  it('maps null employee fields to empty inputs, retains status and manager ID', () => {
    expect(
      employeeToForm(
        employee({
          user_id: '77',
          gender: 'OTHER',
          date_of_birth: null,
          manager_employee_id: '22',
          description: null,
          status: 'INACTIVE',
        }),
      ),
    ).toMatchObject({
      user_id: '77',
      gender: 'OTHER',
      date_of_birth: '',
      manager_employee_id: '22',
      description: '',
      status: 'INACTIVE',
    })
  })

  it('maps existing assignment nullables without coercing ID strings', () => {
    expect(
      assignmentToForm(
        assignment({
          department_id: null,
          position_id: '44',
          effective_to: null,
          description: null,
          status: 'INACTIVE',
        }),
      ),
    ).toMatchObject({
      organization_id: '1',
      department_id: '',
      position_id: '44',
      effective_to: '',
      description: '',
      status: 'INACTIVE',
    })
  })

  it('keeps employee null/omitted distinctions and excludes status on save', () => {
    expect(employeePayload(emptyEmployeeForm())).toStrictEqual({
      employee_code: '',
      employee_name: '',
      gender: null,
      date_of_birth: null,
      place_of_origin: null,
      hire_date: null,
      manager_employee_id: null,
      description: null,
    })
  })

  it('keeps original string identifiers, optional user ID, and date values', () => {
    expect(
      employeePayload({
        ...emptyEmployeeForm(),
        employee_code: 'E11',
        employee_name: 'Eleven',
        user_id: '12345678901234567890',
        manager_employee_id: '10',
        gender: 'MALE',
        hire_date: '2025-07-01',
        description: 'note',
      }),
    ).toMatchObject({
      user_id: '12345678901234567890',
      manager_employee_id: '10',
      gender: 'MALE',
      hire_date: '2025-07-01',
      date_of_birth: null,
      description: 'note',
    })
  })

  it('CREATE assignment includes owner/status; PATCH excludes them', () => {
    const form = {
      ...emptyAssignmentForm(),
      organization_id: '999999999999999',
      status: 'INACTIVE' as const,
    }
    const create = assignmentPayload(form, '10')
    expect(create).toMatchObject({
      employee_id: '10',
      status: 'INACTIVE',
      organization_id: '999999999999999',
    })
    const edit = assignmentPayload(form, '10', '22')
    expect(edit).not.toHaveProperty('employee_id')
    expect(edit).not.toHaveProperty('status')
    expect(edit).toMatchObject({
      is_primary: false,
      effective_to: null,
      description: null,
    })
  })

  it('preserves optional omission instead of sending cleared department/position', () => {
    const base = assignmentPayload(emptyAssignmentForm(), '10')
    expect(base).not.toHaveProperty('department_id')
    expect(base).not.toHaveProperty('position_id')
    expect(
      assignmentPayload(
        {
          ...emptyAssignmentForm(),
          department_id: '3',
          position_id: '4',
          description: 'role',
        },
        '10',
      ),
    ).toMatchObject({ department_id: '3', position_id: '4', description: 'role' })
  })

  it('rejects self-manager before cycles or inactive-manager errors', () => {
    const people = [
      employee({ id: '10', manager_employee_id: '20' }),
      employee({ id: '20', manager_employee_id: '10', status: 'INACTIVE' }),
    ]
    const form = { ...emptyEmployeeForm(), manager_employee_id: '10' }
    expect(employeeManagerErrorKey(people, form, '10')).toBe('people.managerSelfError')
  })

  it('rejects cyclic manager and inactive manager using unchanged precedence', () => {
    const people = [employee({ id: '10' }), employee({ id: '20', manager_employee_id: '10' })]
    const form = { ...emptyEmployeeForm(), manager_employee_id: '20' }
    expect(employeeManagerErrorKey(people, form, '10')).toBe('people.managerCycleError')
    expect(employeeManagerErrorKey([employee({ id: '20', status: 'INACTIVE' })], form, '10')).toBe(
      'people.managerInactiveError',
    )
  })

  it('allows unassigned manager, including create, without a false self-error', () => {
    expect(employeeManagerErrorKey([], emptyEmployeeForm(), undefined)).toBeNull()
    expect(employeeManagerErrorKey([], emptyEmployeeForm(), '10')).toBeNull()
  })

  it('preserves assignment validation order and existing primary overlap rule', () => {
    expect(assignmentErrorKey(true, emptyAssignmentForm())).toBe('people.primaryOverlap')
    expect(assignmentErrorKey(false, emptyAssignmentForm())).toBe('people.organizationRequired')
    expect(assignmentErrorKey(false, { ...emptyAssignmentForm(), organization_id: '1' })).toBeNull()
    expect(
      hasPrimaryOverlap([assignment()], {
        effective_from: '2025-02-01',
        effective_to: '',
        is_primary: true,
      }),
    ).toBe(true)
  })
})
