'use strict'

const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { resolve } = require('node:path')

const root = resolve(__dirname, '..', 'src')
const read = (path) => readFileSync(resolve(root, path), 'utf8')
const contact = read('components/contacts/ContactList.vue')
const scoped = read('components/contacts/contactListQuery.ts')
const people = read('features/people/pages/PeopleEmployeesView.vue')
const organizations = read('features/organization/pages/OrganizationsView.vue')
const assertions = [
  ['single Contact table', () => assert.equal((contact.match(/<ListTable\b/g) || []).length, 1)],
  ['single pagination', () => assert.equal((contact.match(/<ListPagination\b/g) || []).length, 1)],
  ['no three status tabs', () => assert.doesNotMatch(contact, /role="tablist"|contact-list__modes|\bmode\.value\b/)],
  ['one status filter', () => assert.match(contact, /:filters="statusFilters"/)],
  ['status options from Lifecycle', () => assert.match(contact, /workflowStates\.codes\.value/)],
  ['default all statuses', () => assert.match(scoped, /typeof status === 'string' && status \?/)],
  ['SQL-first list API', () => assert.match(contact, /listContactPage\(scopedContactListQuery/)],
  ['no client-side merging', () => assert.doesNotMatch(contact, /Promise\.all\(\[\s*listContactPage/)],
  ['row status column', () => assert.match(contact, /key: 'status', labelKey: 'contact\.status'/)],
  ['visible status text and icon', () => assert.match(contact, /<TableStatus :status="row\.status" \/>/)],
  ['PBAC row actions preserved', () => assert.match(contact, /:allowed-actions="rowAllowedActions\(row\)"/)],
  ['Workflow change reloads', () => assert.match(contact, /@state-changed="afterWorkflowChange"/)],
  ['Employee owner unchanged', () => assert.match(people, /<ContactList[^\n]*owner-type="EMPLOYEE"/)],
  ['Organization owner unchanged', () => assert.match(organizations, /<ContactList[^\n]*owner-type="ORGANIZATION"/)],
]
for (const [name, fn] of assertions) {
  fn()
  process.stdout.write(`PASS: ${name}\n`)
}
process.stdout.write(`P2 CONTACT SINGLE LIST: ${assertions.length}/${assertions.length} PASS\n`)
