import {
  createContact,
  createOrganization,
  createProvince,
  createWard,
  getContact,
  getOrganization,
  getOrganizationTree,
  getProvince,
  getWard,
  listContacts,
  listOrganizations,
  listProvinces,
  listWards,
  setContactState,
  setOrganizationState,
  setProvinceState,
  setWardState,
  updateContact,
  updateOrganization,
  updateProvince,
  updateWard,
} from '@/api/generated/sdk.gen'
import type {
  Contact,
  ContactCreate,
  ContactPatch,
  Organization,
  OrganizationCreate,
  Province,
  ProvinceCreate,
  ProvincePatch,
  State,
  Ward,
  WardCreate,
  WardPatch,
} from '@/api/generated/types.gen'
import { withTypedClient } from '@/api/client'
import { i18n } from '@/i18n'
import type { ListQuery, ListResponse } from '@/components/lists/listTypes'

/** Contact ownership is immutable after creation; PATCH only carries editable fields. */
export type ContactUpdate = Omit<ContactCreate, 'contact_type' | 'contact_ref_id' | 'status'>
/** PATCH must never carry state; only the set-state endpoint may mutate it. */
export type ProvinceUpdate = Omit<ProvinceCreate, 'status'>
export type WardUpdate = Omit<WardCreate, 'status'>

function headers() {
  return { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' }
}

function apiQuery(query: ListQuery): {
  q?: string
  page: number
  page_size: number
  sort?: string
  filter: Record<string, string | string[]>
} {
  return {
    q: query.q || undefined,
    page: query.page,
    page_size: query.page_size,
    sort: query.sort || undefined,
    filter: Object.fromEntries(
      Object.entries(query.filter).map(([key, value]) => [
        key,
        typeof value === 'string' ? value : Array.from(value),
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

export async function listProvincePage(query: ListQuery): Promise<ListResponse<Province>> {
  const response = await withTypedClient((client) =>
    listProvinces({ client, headers: headers(), query: apiQuery(query), throwOnError: true }),
  )
  return collection(response)
}

export async function saveProvince(
  payload: ProvinceCreate | ProvinceUpdate,
  id?: string,
): Promise<Province> {
  const response = await withTypedClient((client) =>
    id
      ? updateProvince({
          client,
          headers: headers(),
          path: { id },
          // Generated SDK types are from the previous OpenAPI snapshot until regeneration.
          body: payload as ProvincePatch,
          throwOnError: true,
        })
      : createProvince({
          client,
          headers: headers(),
          body: payload as ProvinceCreate,
          throwOnError: true,
        }),
  )
  return resource(response)
}

export async function changeProvinceState(id: string, status: State['status']): Promise<Province> {
  const response = await withTypedClient((client) =>
    setProvinceState({
      client,
      headers: headers(),
      path: { id },
      body: { status },
      throwOnError: true,
    }),
  )
  return resource(response)
}

export async function getProvinceById(id: string): Promise<Province> {
  const response = await withTypedClient((client) =>
    getProvince({ client, headers: headers(), path: { id }, throwOnError: true }),
  )
  return resource(response)
}

export async function listWardPage(query: ListQuery): Promise<ListResponse<Ward>> {
  const response = await withTypedClient((client) =>
    listWards({ client, headers: headers(), query: apiQuery(query), throwOnError: true }),
  )
  return collection(response)
}

export async function saveWard(payload: WardCreate | WardUpdate, id?: string): Promise<Ward> {
  const response = await withTypedClient((client) =>
    id
      ? updateWard({
          client,
          headers: headers(),
          path: { id },
          body: payload as WardPatch,
          throwOnError: true,
        })
      : createWard({
          client,
          headers: headers(),
          body: payload as WardCreate,
          throwOnError: true,
        }),
  )
  return resource(response)
}

export async function changeWardState(id: string, status: State['status']): Promise<Ward> {
  const response = await withTypedClient((client) =>
    setWardState({
      client,
      headers: headers(),
      path: { id },
      body: { status },
      throwOnError: true,
    }),
  )
  return resource(response)
}

export async function getWardById(id: string): Promise<Ward> {
  const response = await withTypedClient((client) =>
    getWard({ client, headers: headers(), path: { id }, throwOnError: true }),
  )
  return resource(response)
}

export async function listOrganizationPage(query: ListQuery): Promise<ListResponse<Organization>> {
  const response = await withTypedClient((client) =>
    listOrganizations({ client, headers: headers(), query: apiQuery(query), throwOnError: true }),
  )
  return collection(response)
}

export async function listOrganizationTree(query: ListQuery): Promise<ListResponse<Organization>> {
  const response = await withTypedClient((client) =>
    getOrganizationTree({ client, headers: headers(), query: apiQuery(query), throwOnError: true }),
  )
  return collection(response)
}

export async function getOrganizationById(id: string): Promise<Organization> {
  const response = await withTypedClient((client) =>
    getOrganization({ client, headers: headers(), path: { id }, throwOnError: true }),
  )
  return resource(response)
}

export async function saveOrganization(
  payload: OrganizationCreate | Omit<OrganizationCreate, 'status'>,
  id?: string,
): Promise<Organization> {
  const response = await withTypedClient((client) =>
    id
      ? updateOrganization({
          client,
          headers: headers(),
          path: { id },
          body: payload as OrganizationCreate,
          throwOnError: true,
        })
      : createOrganization({
          client,
          headers: headers(),
          body: payload as OrganizationCreate,
          throwOnError: true,
        }),
  )
  return resource(response)
}

export async function changeOrganizationState(
  id: string,
  status: State['status'],
): Promise<Organization> {
  const response = await withTypedClient((client) =>
    setOrganizationState({
      client,
      headers: headers(),
      path: { id },
      body: { status },
      throwOnError: true,
    }),
  )
  return resource(response)
}

export async function listContactPage(query: ListQuery): Promise<ListResponse<Contact>> {
  const response = await withTypedClient((client) =>
    listContacts({ client, headers: headers(), query: apiQuery(query), throwOnError: true }),
  )
  return collection(response)
}

export async function getContactById(id: string): Promise<Contact> {
  const response = await withTypedClient((client) =>
    getContact({ client, headers: headers(), path: { id }, throwOnError: true }),
  )
  return resource(response)
}

export async function saveContact(
  payload: ContactCreate | ContactUpdate,
  id?: string,
): Promise<Contact> {
  const response = await withTypedClient((client) =>
    id
      ? updateContact({
          client,
          headers: headers(),
          path: { id },
          // The generated OpenAPI alias still includes create-only owner fields.
          // API-010 rejects those fields on update, so keep the runtime PATCH narrow.
          body: payload as ContactPatch,
          throwOnError: true,
        })
      : createContact({
          client,
          headers: headers(),
          body: payload as ContactCreate,
          throwOnError: true,
        }),
  )
  return resource(response)
}

export async function changeContactState(id: string, status: State['status']): Promise<Contact> {
  const response = await withTypedClient((client) =>
    setContactState({
      client,
      headers: headers(),
      path: { id },
      body: { status },
      throwOnError: true,
    }),
  )
  return resource(response)
}
