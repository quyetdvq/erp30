import { ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Employee, EmployeeAssignment } from '@/api/generated/types.gen'
import type { ListQuery, ListResponse } from '@/components/lists/listTypes'
import { today, emptyEmployeeForm } from '../model'
import { useEmployeeAssignments } from '../useEmployeeAssignments'
import { useEmployeeLookups } from '../useEmployeeLookups'
import type { AssignmentMode } from '../types'

const sources = vi.hoisted(() => ({
  listEmployees: vi.fn<(query: ListQuery) => Promise<unknown>>(), listAssignments: vi.fn<(query: ListQuery) => Promise<unknown>>(),
  listOrganizations: vi.fn<(query: ListQuery) => Promise<unknown>>(), listDepartments: vi.fn<(query: ListQuery) => Promise<unknown>>(), listPositions: vi.fn<(query: ListQuery) => Promise<unknown>>(),
}))
vi.mock('@/services/employeeStructure', () => ({
  listEmployeePage: sources.listEmployees,
  listAssignmentPage: sources.listAssignments,
  listDepartmentPage: sources.listDepartments,
  listPositionPage: sources.listPositions,
}))
vi.mock('@/services/foundation', () => ({ listOrganizationPage: sources.listOrganizations }))
vi.mock('@/services/errorBoundary', () => ({ reportApiError: vi.fn<(...args: unknown[]) => void>() }))

function page<T>(query: ListQuery, data: T[], total = data.length): ListResponse<T> {
  return { data, meta: { page: query.page, page_size: query.page_size, total } }
}
const employee = (id: string, manager_employee_id?: string): Employee => ({
  id, employee_code: 'E' + id, employee_name: 'Name ' + id, status: 'ACTIVE',
  manager_employee_id,
}) as Employee
const assignment = (id: string, employee_id: string): EmployeeAssignment => ({
  id, employee_id, organization_id: '99', is_primary: true,
  effective_from: '2000-01-01', effective_to: null, status: 'ACTIVE',
}) as EmployeeAssignment

beforeEach(() => {
  vi.clearAllMocks()
  sources.listEmployees.mockImplementation(async (query: ListQuery) => {
    const ids = query.filter.id
    const all = [employee('1', '77'), employee('2', '77'), employee('77')]
    const selected = Array.isArray(ids) ? all.filter((item) => ids.includes(item.id)) : all
    return page(query, selected)
  })
  sources.listAssignments.mockImplementation(async (query: ListQuery) => page(query, [assignment('11','1')]))
  for (const source of [sources.listOrganizations, sources.listDepartments, sources.listPositions]) {
    source.mockImplementation(async (query: ListQuery) => page(query, []))
  }
})

describe('Employee table-first request budget', () => {
  it('loads filter catalogs without eagerly paging all employees', async () => {
    const lookups = useEmployeeLookups(ref<string | null>(null), ref(emptyEmployeeForm()), ref<unknown | null>(null))
    await lookups.loadLookups()
    expect(sources.listEmployees).not.toHaveBeenCalled()
    expect(sources.listOrganizations).toHaveBeenCalledTimes(1)
    expect(sources.listDepartments).toHaveBeenCalledTimes(1)
    expect(sources.listPositions).toHaveBeenCalledTimes(1)
  })

  it('uses a single scoped manager-ID lookup and reuses it; editor directory stays lazy', async () => {
    const lookups = useEmployeeLookups(ref<string | null>(null), ref(emptyEmployeeForm()), ref<unknown | null>(null))
    await lookups.loadPageManagerNames([employee('1','77'), employee('2','77')])
    expect(sources.listEmployees).toHaveBeenCalledTimes(1)
    const query = sources.listEmployees.mock.calls[0]![0] as ListQuery
    expect(query.filter.id).toEqual(['77'])
    expect(lookups.managerName('77')).toBe('Name 77')
    expect(lookups.managerDirectoryReady.value).toBe(false)
    await lookups.loadManagerDirectory()
    expect(lookups.managerDirectoryReady.value).toBe(true)
    await lookups.loadManagerDirectory()
    expect(sources.listEmployees).toHaveBeenCalledTimes(2) // One extra full directory read, only in editor.
  })

  it('loads only today-effective primary assignments for current page', async () => {
    const state = useEmployeeAssignments(ref<string | null>('1'), ref<AssignmentMode>('active'))
    await state.loadPageAssignments(['1', '2'])
    expect(sources.listAssignments).toHaveBeenCalledTimes(1)
    const query = sources.listAssignments.mock.calls[0]![0] as ListQuery
    expect(query.filter).toEqual({ employee_id: ['1','2'], status: 'ACTIVE', is_primary: '1', effective_at: today() })
    expect(state.currentPrimary('1')?.id).toBe('11')
    expect(state.currentPrimary('2')).toBeUndefined()
  })

  it('does not fetch history until requested and rejects stale modal results', async () => {
    let resolveFirst!: (response: ListResponse<EmployeeAssignment>) => void
    sources.listAssignments.mockImplementationOnce(() => new Promise<ListResponse<EmployeeAssignment>>((resolve) => {
      resolveFirst = (response) => resolve(response)
    })).mockImplementationOnce(async (query: ListQuery) => page(query, [assignment('22', '2')]))
    const selected = ref<string | null>('1')
    const state = useEmployeeAssignments(selected, ref<AssignmentMode>('active'))
    expect(sources.listAssignments).not.toHaveBeenCalled()
    const old = state.loadAssignments()
    selected.value = '2'
    state.resetSelectedAssignments()
    await state.loadAssignments()
    resolveFirst(page({ q: '', page: 1, page_size: 200, sort: '', filter: {} }, [assignment('11', '1')]))
    await old
    expect(state.selectedEmployeeAssignments.value.map((row) => row.id)).toEqual(['22'])
  })
})
