import { describe, expect, it } from 'vitest'
import { createListQuery } from '@/components/lists/listTypes'
import { scopedContactListQuery } from '@/components/contacts/contactListQuery'

describe('Contact shared single-list owner and status query', () => {
  it('loads all Lifecycle states by default without any status predicate', () => {
    const request = scopedContactListQuery(createListQuery({ page: 1, page_size: 15 }), 'EMPLOYEE', '4')
    expect(request.filter).toEqual({ contact_type: 'EMPLOYEE', contact_ref_id: '4' })
    expect(request.filter).not.toHaveProperty('status')
  })

  it('works for Organization Contact without forcing ACTIVE or DRAFT', () => {
    const request = scopedContactListQuery(createListQuery(), 'ORGANIZATION', '2')
    expect(request.filter).toEqual({ contact_type: 'ORGANIZATION', contact_ref_id: '2' })
  })

  it('scopes SUPPLIER Contacts to the persisted Supplier id with all statuses by default', () => {
    const request = scopedContactListQuery(createListQuery({ page: 2, page_size: 25 }), 'SUPPLIER', '17')
    expect(request.filter).toEqual({ contact_type: 'SUPPLIER', contact_ref_id: '17' })
    expect(request.filter).not.toHaveProperty('status')
  })

  it('keeps Customer contact owner compatibility without granting Supplier permissions', () => {
    const request = scopedContactListQuery(createListQuery(), 'CUSTOMER', '19')
    expect(request.filter).toEqual({ contact_type: 'CUSTOMER', contact_ref_id: '19' })
  })

  it('never uses a caller-supplied owner or company scope for Supplier Contact', () => {
    const request = scopedContactListQuery(
      createListQuery({ filter: { contact_type: 'EMPLOYEE', contact_ref_id: '999', company_id: '987', status: 'CLOSED' } }),
      'SUPPLIER', '17',
    )
    expect(request.filter).toEqual({ contact_type: 'SUPPLIER', contact_ref_id: '17', status: 'CLOSED' })
  })

  it.each(['ACTIVE', 'DRAFT', 'INACTIVE', 'CLOSED', 'WAITING_APPROVAL'])(
    'supports optional status %s on the same server-side list',
    (status) => {
      const request = scopedContactListQuery(createListQuery({ filter: { status } }), 'EMPLOYEE', '4')
      expect(request.filter).toEqual({ contact_type: 'EMPLOYEE', contact_ref_id: '4', status })
    },
  )

  it('discards an empty status and does not submit any status filter', () => {
    const request = scopedContactListQuery(createListQuery({ filter: { status: '' } }), 'EMPLOYEE', '4')
    expect(request.filter).not.toHaveProperty('status')
  })

  it('always binds the persisted owner from props and discards caller-supplied owner filters', () => {
    const request = scopedContactListQuery(
      createListQuery({ filter: { contact_type: 'ORGANIZATION', contact_ref_id: '999', status: 'CLOSED' } }),
      'EMPLOYEE', '4',
    )
    expect(request.filter).toEqual({ contact_type: 'EMPLOYEE', contact_ref_id: '4', status: 'CLOSED' })
  })

  it('preserves server-side search, stable sort and pagination rather than merging three pages', () => {
    const query = createListQuery({ q: 'Thang', page: 3, page_size: 15, sort: '-id' })
    const request = scopedContactListQuery(query, 'ORGANIZATION', '2')
    expect(request).toEqual({ ...query, filter: { contact_type: 'ORGANIZATION', contact_ref_id: '2' } })
    expect(query.filter).toEqual({})
  })
})
