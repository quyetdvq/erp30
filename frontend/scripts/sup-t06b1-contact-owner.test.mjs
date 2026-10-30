// Portable offline gate for T06B1, runnable without pnpm/Vitest or a browser.
// The real frontend type/lint/build and authenticated browser gate are separate.
import assert from 'node:assert/strict'
import test from 'node:test'
import { contactOwnerResource } from '../src/components/contacts/contactOwnerPolicy.ts'
import { scopedContactListQuery } from '../src/components/contacts/contactListQuery.ts'

const expected = Object.freeze({
  EMPLOYEE: 'employee_contact',
  ORGANIZATION: 'organization_contact',
  CUSTOMER: 'customer_contact',
  SUPPLIER: 'supplier_contact',
})

for (const [owner, resource] of Object.entries(expected)) {
  test(`canonical ${owner} resource remains explicit`, () => {
    assert.equal(contactOwnerResource(owner), resource)
  })
}

for (const owner of ['', 'Supplier', 'supplier', 'SUPPLIER ', 'UNKNOWN', null, undefined]) {
  test(`unknown owner ${String(owner)} fails closed`, () => {
    assert.equal(contactOwnerResource(owner), '')
  })
}

for (const owner of Object.keys(expected)) {
  test(`owner ${owner} is pinned from trusted view props before submission`, () => {
    const query = {
      q: 'Unicode Đặng', page: 3, page_size: 25, sort: '-id',
      filter: { contact_type: 'EMPLOYEE', contact_ref_id: '999', company_id: '333', status: 'CLOSED' },
    }
    const output = scopedContactListQuery(query, owner, '18')
    assert.deepEqual(output, {
      q: 'Unicode Đặng', page: 3, page_size: 25, sort: '-id',
      filter: { contact_type: owner, contact_ref_id: '18', status: 'CLOSED' },
    })
    assert.equal(query.filter.contact_ref_id, '999', 'input must remain unmodified')
  })

  test(`owner ${owner} requests all status states by default`, () => {
    const output = scopedContactListQuery({ page: 1, page_size: 25, q: '', sort: '-id', filter: {} }, owner, '18')
    assert.deepEqual(output.filter, { contact_type: owner, contact_ref_id: '18' })
  })
}
