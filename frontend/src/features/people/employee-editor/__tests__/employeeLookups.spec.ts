import { ref } from 'vue'
import { describe, expect, it } from 'vitest'
import type { Employee, Organization, Department, Position } from '@/api/generated/types.gen'
import { emptyEmployeeForm } from '../model'
import { useEmployeeLookups } from '../useEmployeeLookups'

describe('Employees V2.3 — reactive lookup ownership', () => {
  const employee = (id: string, status = 'ACTIVE'): Employee =>
    ({ id, employee_code: `E${id}`, employee_name: `Name ${id}`, status }) as unknown as Employee

  it('keeps active options and manager self-filter with retained selected option', () => {
    const selectedId = ref<string | null>('1')
    const form = ref(emptyEmployeeForm())
    const error = ref<unknown | null>(null)
    const lookups = useEmployeeLookups(selectedId, form, error)
    lookups.employeeLookup.value = [employee('1'), employee('2'), employee('3', 'INACTIVE')]
    expect(lookups.managerSelectOptions.value).toEqual([{ value: '2', label: 'E2 - Name 2' }])
    form.value.manager_employee_id = '1'
    expect(lookups.managerSelectOptions.value).toEqual([
      { value: '1', label: 'E1 - Name 1' },
      { value: '2', label: 'E2 - Name 2' },
    ])
  })

  it('upserts employee lookup without duplicates or numeric coercion', () => {
    const lookups = useEmployeeLookups(
      ref<string | null>(null),
      ref(emptyEmployeeForm()),
      ref<unknown | null>(null),
    )
    lookups.upsertEmployeeLookup(employee('100000000000000000000'))
    lookups.upsertEmployeeLookup(employee('100000000000000000000', 'INACTIVE'))
    expect(lookups.employeeLookup.value).toHaveLength(1)
    expect(lookups.employeeLookup.value[0]?.status).toBe('INACTIVE')
  })

  it('retains organization/department/position display and active dropdown filtering', () => {
    const lookups = useEmployeeLookups(
      ref<string | null>(null),
      ref(emptyEmployeeForm()),
      ref<unknown | null>(null),
    )
    lookups.organizations.value = [
      { id: '10', code: 'O1', name: 'Org one', status: 'ACTIVE' },
      { id: '11', code: 'O2', name: 'Org old', status: 'INACTIVE' },
    ] as Organization[]
    lookups.departments.value = [
      { id: '20', code: 'D1', name: 'Dept one', status: 'ACTIVE' },
    ] as Department[]
    lookups.positions.value = [
      { id: '30', code: 'P1', name: 'Position one', status: 'INACTIVE' },
    ] as Position[]
    expect(lookups.organizationSelectOptions.value).toEqual([
      { value: '10', label: 'O1 - Org one' },
    ])
    expect(lookups.departmentSelectOptions.value).toEqual([{ value: '20', label: 'D1 - Dept one' }])
    expect(lookups.positionSelectOptions.value).toEqual([])
    expect(lookups.organizationName('11')).toBe('Org old')
    expect(lookups.departmentName('20')).toBe('Dept one')
    expect(lookups.positionName('30')).toBe('Position one')
    expect(lookups.organizationName(undefined)).toBe('—')
  })
})
