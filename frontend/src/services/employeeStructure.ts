import {
  createDepartment,
  createEmployee,
  createEmployeeAssignment,
  createPosition,
  getEmployee,
  listDepartments,
  listEmployeeAssignments,
  listEmployees,
  listPositions,
  setDepartmentState,
  setEmployeeAssignmentState,
  setEmployeeState,
  setPositionState,
  updateDepartment,
  updateEmployee,
  updateEmployeeAssignment,
  updatePosition,
} from '@/api/generated/sdk.gen'
import type {
  Department,
  DepartmentCreate,
  DepartmentPatch,
  Employee,
  EmployeeAssignment,
  EmployeeAssignmentCreate,
  EmployeeAssignmentPatch,
  EmployeeCreate,
  EmployeePatch,
  Position,
  PositionCreate,
  PositionPatch,
  State,
} from '@/api/generated/types.gen'
import { withTypedClient } from '@/api/client'
import { i18n } from '@/i18n'
import { serializeListQuery, type ListQuery, type ListResponse } from '@/components/lists/listTypes'

function headers() {
  return { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' }
}

function apiQuery(query: ListQuery) {
  return {
    q: query.q || undefined,
    page: query.page,
    page_size: query.page_size,
    sort: query.sort || undefined,
    filter: Object.fromEntries(
      Object.entries(query.filter).map(([key, value]) => [
        key,
        typeof value === 'string' ? value : [...value],
      ]),
    ),
  }
}

function collection<T>(response: {
  data: { data?: T[]; meta: ListResponse<T>['meta'] }
}): ListResponse<T> {
  return { data: response.data.data ?? [], meta: response.data.meta }
}

function resource<T>(response: { data: { data?: T } }): T {
  if (!response.data.data) throw new Error('API response did not contain a resource.')
  return response.data.data
}

export async function listDepartmentPage(query: ListQuery): Promise<ListResponse<Department>> {
  return collection(
    await withTypedClient((client) =>
      listDepartments({ client, headers: headers(), query: apiQuery(query), throwOnError: true }),
    ),
  )
}

export async function saveDepartment(
  payload: DepartmentCreate | DepartmentPatch,
  id?: string,
): Promise<Department> {
  return resource(
    await withTypedClient((client) =>
      id
        ? updateDepartment({
            client,
            headers: headers(),
            path: { id },
            body: payload,
            throwOnError: true,
          })
        : createDepartment({
            client,
            headers: headers(),
            body: payload as DepartmentCreate,
            throwOnError: true,
          }),
    ),
  )
}

export async function changeDepartmentState(
  id: string,
  status: State['status'],
): Promise<Department> {
  return resource(
    await withTypedClient((client) =>
      setDepartmentState({
        client,
        headers: headers(),
        path: { id },
        body: { status },
        throwOnError: true,
      }),
    ),
  )
}

export async function listPositionPage(query: ListQuery): Promise<ListResponse<Position>> {
  return collection(
    await withTypedClient((client) =>
      listPositions({ client, headers: headers(), query: apiQuery(query), throwOnError: true }),
    ),
  )
}

export async function savePosition(
  payload: PositionCreate | PositionPatch,
  id?: string,
): Promise<Position> {
  return resource(
    await withTypedClient((client) =>
      id
        ? updatePosition({
            client,
            headers: headers(),
            path: { id },
            body: payload,
            throwOnError: true,
          })
        : createPosition({
            client,
            headers: headers(),
            body: payload as PositionCreate,
            throwOnError: true,
          }),
    ),
  )
}

export async function changePositionState(id: string, status: State['status']): Promise<Position> {
  return resource(
    await withTypedClient((client) =>
      setPositionState({
        client,
        headers: headers(),
        path: { id },
        body: { status },
        throwOnError: true,
      }),
    ),
  )
}

export async function listEmployeePage(query: ListQuery): Promise<ListResponse<Employee>> {
  return collection(
    await withTypedClient((client) =>
      listEmployees({
        client, headers: headers(), query: apiQuery(query),
        // Generated deepObject serializer cannot encode nested filter[id][] arrays.
        querySerializer: () => serializeListQuery(query),
        throwOnError: true,
      }),
    ),
  )
}

export async function saveEmployee(
  payload: EmployeeCreate | EmployeePatch,
  id?: string,
): Promise<Employee> {
  return resource(
    await withTypedClient((client) =>
      id
        ? updateEmployee({
            client,
            headers: headers(),
            path: { id },
            body: payload,
            throwOnError: true,
          })
        : createEmployee({
            client,
            headers: headers(),
            body: payload as EmployeeCreate,
            throwOnError: true,
          }),
    ),
  )
}

export async function changeEmployeeState(
  id: string,
  status: 'ACTIVE' | 'INACTIVE',
): Promise<Employee> {
  return resource(
    await withTypedClient((client) =>
      setEmployeeState({
        client,
        headers: headers(),
        path: { id },
        body: { status },
        throwOnError: true,
      }),
    ),
  )
}

export async function getEmployeeById(id: string): Promise<Employee> {
  return resource(
    await withTypedClient((client) =>
      getEmployee({ client, headers: headers(), path: { id }, throwOnError: true }),
    ),
  )
}

export async function listAssignmentPage(
  query: ListQuery,
): Promise<ListResponse<EmployeeAssignment>> {
  return collection(
    await withTypedClient((client) =>
      listEmployeeAssignments({
        client,
        headers: headers(),
        query: apiQuery(query),
        querySerializer: () => serializeListQuery(query),
        throwOnError: true,
      }),
    ),
  )
}

export async function saveAssignment(
  payload: EmployeeAssignmentCreate | EmployeeAssignmentPatch,
  id?: string,
): Promise<EmployeeAssignment> {
  return resource(
    await withTypedClient((client) =>
      id
        ? updateEmployeeAssignment({
            client,
            headers: headers(),
            path: { id },
            body: payload,
            throwOnError: true,
          })
        : createEmployeeAssignment({
            client,
            headers: headers(),
            body: payload as EmployeeAssignmentCreate,
            throwOnError: true,
          }),
    ),
  )
}

export async function changeAssignmentState(
  id: string,
  status: 'ACTIVE' | 'INACTIVE',
): Promise<EmployeeAssignment> {
  return resource(
    await withTypedClient((client) =>
      setEmployeeAssignmentState({
        client,
        headers: headers(),
        path: { id },
        body: { status },
        throwOnError: true,
      }),
    ),
  )
}
