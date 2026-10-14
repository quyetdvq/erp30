import { computed, ref, type Ref } from 'vue'
import type { Department, Employee, Organization, Position } from '@/api/generated/types.gen'
import { listOrganizationPage } from '@/services/foundation'
import {
  listDepartmentPage,
  listEmployeePage,
  listPositionPage,
} from '@/services/employeeStructure'
import { loadLookupPages } from '@/services/listLookups'
import { reportApiError } from '@/services/errorBoundary'
import type { EmployeeForm } from './types'

/** Central owner for employee/organization/dept/position lookups and option lists. */
export function useEmployeeLookups(
  selectedId: Ref<string | null>,
  employeeForm: Ref<EmployeeForm>,
  error: Ref<unknown | null>,
) {
  const organizations = ref<Organization[]>([])
  const departments = ref<Department[]>([])
  const positions = ref<Position[]>([])
  const employeeLookup = ref<Employee[]>([])
  const activeEmployees = computed(() =>
    employeeLookup.value.filter((row) => row.status === 'ACTIVE'),
  )
  function upsertEmployeeLookup(row: Employee): void {
    const id = String(row.id)
    const index = employeeLookup.value.findIndex((entry) => String(entry.id) === id)
    if (index === -1) employeeLookup.value = [...employeeLookup.value, row]
    else
      employeeLookup.value = employeeLookup.value.map((entry, current) =>
        current === index ? row : entry,
      )
  }
  const managerOptions = computed(() =>
    activeEmployees.value.filter(
      (row) =>
        String(row.id) !== selectedId.value ||
        String(row.id) === employeeForm.value.manager_employee_id,
    ),
  )
  const managerSelectOptions = computed(() =>
    managerOptions.value.map((row) => ({
      value: String(row.id),
      label: `${row.employee_code} - ${row.employee_name}`,
    })),
  )
  const activeOrganizations = computed(() =>
    organizations.value.filter((row) => row.status === 'ACTIVE'),
  )
  const activeDepartments = computed(() =>
    departments.value.filter((row) => row.status === 'ACTIVE'),
  )
  const activePositions = computed(() => positions.value.filter((row) => row.status === 'ACTIVE'))
  const organizationSelectOptions = computed(() =>
    activeOrganizations.value.map((row) => ({
      value: String(row.id),
      label: `${row.code} - ${row.name}`,
    })),
  )
  const departmentSelectOptions = computed(() =>
    activeDepartments.value.map((row) => ({
      value: String(row.id),
      label: `${row.code} - ${row.name}`,
    })),
  )
  const positionSelectOptions = computed(() =>
    activePositions.value.map((row) => ({
      value: String(row.id),
      label: `${row.code} - ${row.name}`,
    })),
  )
  const organizationName = (id?: string | number | bigint) =>
    organizations.value.find((row) => String(row.id) === String(id))?.name ?? '—'
  const departmentName = (id?: string | number | bigint) =>
    departments.value.find((row) => String(row.id) === String(id))?.name ?? '—'
  const positionName = (id?: string | number | bigint) =>
    positions.value.find((row) => String(row.id) === String(id))?.name ?? '—'

  async function loadLookups(): Promise<void> {
    try {
      const [orgs, deps, poss, employees] = await Promise.all([
        loadLookupPages(listOrganizationPage),
        loadLookupPages(listDepartmentPage),
        loadLookupPages(listPositionPage),
        loadLookupPages(listEmployeePage, { sort: 'employee_name' }),
      ])
      employeeLookup.value = employees
      organizations.value = orgs
      departments.value = deps
      positions.value = poss
    } catch (caught: unknown) {
      error.value = caught
      reportApiError(caught)
    }
  }

  return {
    organizations,
    departments,
    positions,
    employeeLookup,
    managerSelectOptions,
    organizationSelectOptions,
    departmentSelectOptions,
    positionSelectOptions,
    organizationName,
    departmentName,
    positionName,
    loadLookups,
    upsertEmployeeLookup,
  }
}
