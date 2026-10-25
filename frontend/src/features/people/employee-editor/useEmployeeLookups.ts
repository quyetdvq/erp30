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
  // Only the employee editor needs the complete manager graph for cycle checks.
  // A list page must never paginate through every Employee just to draw 15 rows.
  const managerDirectoryLoading = ref(false)
  const managerDirectoryReady = ref(false)
  const managerDirectoryError = ref(false)
  const pageManagerRows = ref<Employee[]>([])
  let managerDirectoryRequest: Promise<void> | null = null
  let pageManagerVersion = 0
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
  function managerName(id?: string | number | bigint): string {
    if (id === null || id === undefined || id === '') return '—'
    const key = String(id)
    return employeeLookup.value.find((row) => String(row.id) === key)?.employee_name
      ?? pageManagerRows.value.find((row) => String(row.id) === key)?.employee_name
      ?? '—'
  }

  /** Fetch only managers referenced by the *visible page*, using the existing GET /employees. */
  async function loadPageManagerNames(rows: readonly Employee[]): Promise<void> {
    const version = ++pageManagerVersion
    pageManagerRows.value = []
    if (managerDirectoryReady.value) return
    const currentIds = new Set(rows.map((row) => String(row.id)))
    const ids = [...new Set(rows.map((row) => row.manager_employee_id)
      .filter((id): id is string => Boolean(id))
      .map(String))].filter((id) => !currentIds.has(id))
    if (ids.length === 0) return
    try {
      // Bound each GET/IN clause to 50 BIGINT IDs, including page_size=200 lists.
      const batches = await Promise.all(Array.from({ length: Math.ceil(ids.length / 50) }, (_, i) =>
        loadLookupPages(listEmployeePage, { filter: { id: ids.slice(i * 50, (i + 1) * 50) } }, 50),
      ))
      if (version === pageManagerVersion) pageManagerRows.value = batches.flat()
    } catch (caught: unknown) {
      if (version === pageManagerVersion) reportApiError(caught)
    }
  }

  async function loadManagerDirectory(): Promise<void> {
    if (managerDirectoryReady.value) return
    if (managerDirectoryRequest) return managerDirectoryRequest
    managerDirectoryLoading.value = true
    managerDirectoryError.value = false
    managerDirectoryRequest = (async () => {
      try {
        employeeLookup.value = await loadLookupPages(listEmployeePage, { sort: 'employee_name' })
        managerDirectoryReady.value = true
      } catch (caught: unknown) {
        managerDirectoryError.value = true
        reportApiError(caught)
      } finally {
        managerDirectoryLoading.value = false
        managerDirectoryRequest = null
      }
    })()
    return managerDirectoryRequest
  }

  function invalidateManagerDirectory(): void {
    managerDirectoryReady.value = false
  }

  const organizationName = (id?: string | number | bigint) =>
    organizations.value.find((row) => String(row.id) === String(id))?.name ?? '—'
  const departmentName = (id?: string | number | bigint) =>
    departments.value.find((row) => String(row.id) === String(id))?.name ?? '—'
  const positionName = (id?: string | number | bigint) =>
    positions.value.find((row) => String(row.id) === String(id))?.name ?? '—'

  async function loadLookups(): Promise<void> {
    try {
      const [orgs, deps, poss] = await Promise.all([
        loadLookupPages(listOrganizationPage),
        loadLookupPages(listDepartmentPage),
        loadLookupPages(listPositionPage),
      ])
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
    managerDirectoryLoading,
    managerDirectoryReady,
    managerDirectoryError,
    managerName,
    loadPageManagerNames,
    loadManagerDirectory,
    invalidateManagerDirectory,
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
