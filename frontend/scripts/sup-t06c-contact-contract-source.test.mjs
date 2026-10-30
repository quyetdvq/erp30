// SUP-T06C source contract gates: no pnpm, database or code generator required.
// This checks the proposed canonical/frontend delta, NOT PC-06/PC-07/MOD-G2 signoff.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import test from 'node:test'

const root = join(dirname(fileURLToPath(import.meta.url)), '../..')
const read = (name) => readFileSync(join(root, name), 'utf8')
const canonical = read('contracts/openapi/openapi.yaml')
const review = JSON.parse(read('contracts/openapi/p2-sup-contact.review.openapi.json'))
const list = read('frontend/src/components/contacts/ContactList.vue')
const form = read('frontend/src/components/contacts/ContactFormModal.vue')
const policy = read('frontend/src/components/contacts/contactOwnerPolicy.ts')
const query = read('frontend/src/components/contacts/contactListQuery.ts')
const backend = read('backend/app/Modules/Contact/ContactRules.php')
const parent = read('backend/app/Modules/Contact/SupplierContactParentView.php')
const sql = read('backend/app/Modules/Contact/ContactSql.php')
const service = read('backend/app/Modules/Contact/ContactService.php')
const expected = ['EMPLOYEE', 'ORGANIZATION', 'CUSTOMER', 'SUPPLIER']

function schemaBlock(name, nextName) {
  const anchor = canonical.indexOf('\n  schemas:')
  assert.ok(anchor >= 0, 'components.schemas missing')
  const begin = canonical.indexOf(`    ${name}:`, anchor)
  const end = canonical.indexOf(`    ${nextName}:`, begin + name.length + 5)
  assert.ok(begin > anchor && end > begin, `missing or reordered schema ${name}`)
  return canonical.slice(begin, end)
}
const owner = schemaBlock('ContactOwnerType', 'Contact')
const contact = schemaBlock('Contact', 'ContactCreate')
const create = schemaBlock('ContactCreate', 'ContactPatch')
const patch = schemaBlock('ContactPatch', 'Department')
const fields = (block) => [...block.matchAll(/^        ([a-z_]+):/gm)].map((m) => m[1])

for (const [name, block] of [['Contact', contact], ['ContactCreate', create]]) {
  test(`${name}: exact shared ContactOwnerType reference`, () => {
    assert.match(block, /contact_type: \{\$ref: '#\/components\/schemas\/ContactOwnerType'\}/)
    assert.doesNotMatch(block, /contact_type: \{type: string, enum:/)
  })
}
test('canonical owner enum exactly matches Backend/CUS/SUP candidate (no extra or missing kind)', () => {
  const match = owner.match(/enum: \[([^\]]+)\]/)
  assert.ok(match)
  const actual = match[1].split(',').map((x) => x.trim())
  assert.deepEqual(actual, expected)
  assert.deepEqual(actual, review.components.schemas.ContactOwnerType.enum)
  for (const value of expected) assert.match(backend, new RegExp(`'${value}'\\s*=>`))
})
test('owner identity is a type, never a permission grant', () => {
  assert.match(owner, /never confers PBAC/)
  assert.match(policy, /contactOwnerResource/)
  assert.match(policy, /return ''/)
  assert.match(parent, /canView\('supplier'/)
})
test('Contact mandatory fields and BIGINT ref shape preserved', () => {
  assert.match(contact, /required: \[id, contact_type, contact_ref_id, status\]/)
  assert.match(contact, /contact_ref_id: \{\$ref: '#\/components\/schemas\/BigInt'\}/)
  assert.match(contact, /id: \{\$ref: '#\/components\/schemas\/BigInt'\}/)
})
test('legacy Contact read fields retained exactly; hints are additive only', () => {
  assert.deepEqual(fields(contact), Object.keys(review.components.schemas.Contact.properties))
  assert.equal(review.components.schemas.Contact.required.includes('allowed_actions'), false)
  assert.match(contact, /allowed_actions:/)
})
test('read row action hints boolean-only, optional and non-granting', () => {
  const hints = contact.match(/        allowed_actions:\r?\n([\s\S]*)/)
  assert.ok(hints)
  assert.match(hints[1], /additionalProperties: false/)
  assert.match(hints[1], /edit: \{type: boolean\}/)
  assert.match(hints[1], /setstate: \{type: boolean\}/)
  assert.doesNotMatch(hints[1], /required:/)
  assert.match(hints[1], /reauthorizes every mutation/)
})
test('ContactCreate required legacy owner keys retained, no status/usage/bank fields', () => {
  assert.match(create, /required: \[contact_type, contact_ref_id\]/)
  assert.deepEqual(fields(create), Object.keys(review.components.schemas.ContactCreate.properties))
  assert.doesNotMatch(create, /^        (status|company_id|supplier_id|group_organization_id):/m)
})
test('actual initial-state failure is described, no ACTIVE fallback', () => {
  assert.match(create, /WF_INITIAL_STATE_REQUIRED/)
  assert.doesNotMatch(create, /defaults? to native ACTIVE/i)
  assert.match(service, /WF_INITIAL_STATE_REQUIRED/)
})
test('ContactPatch remains exactly the legacy field set without changing owner/state', () => {
  assert.deepEqual(fields(patch), Object.keys(review.components.schemas.ContactPatch.properties))
  for (const forbidden of ['contact_type', 'contact_ref_id', 'status']) {
    assert.doesNotMatch(patch, new RegExp(`^        ${forbidden}:`, 'm'))
  }
})
test('preserve five existing Contact API operationIds', () => {
  for (const id of review['x-existing-operations-must-remain']) {
    assert.match(canonical, new RegExp(`operationId: ${id}\\b`))
  }
  assert.equal(review['x-existing-operations-must-remain'].length, 5)
})
test('old Contact filters and cap stay distinct from Supplier future flat list', () => {
  assert.match(canonical, /operationId: listContacts[\s\S]*?components\/parameters\/Filter'/)
  assert.match(sql, /private const FILTER\s*=\s*\['contact_type',\s*'contact_ref_id',\s*'status'\]/)
  assert.match(query, /contact_type: ownerType/)
  assert.match(query, /contact_ref_id: ownerId/)
  assert.match(sql, /\$size > 200/)
})
test('frontend map has exact owners with SDK exhaustiveness (no fallback)', () => {
  assert.match(policy, /satisfies Record<ApiContactOwnerType, string>/)
  assert.match(policy, /return ''/)
  const mapping = [...policy.matchAll(/^  (EMPLOYEE|ORGANIZATION|CUSTOMER|SUPPLIER):/gm)].map((m) => m[1])
  assert.deepEqual([...mapping].sort(), [...expected].sort())
})
test('ContactList and Form consume same four-owner typed policy', () => {
  assert.match(list, /ownerType\?: ContactOwnerType/)
  assert.match(form, /ownerType: ContactOwnerType/)
  assert.match(list, /:owner-type="props\.ownerType"/)
  assert.match(form, /contact_type: props\.ownerType/)
})
test('frontend does not invent a Supplier route or transfer owner via PATCH', () => {
  assert.match(form, /if \(props\.contact\) \{[\s\S]*?emit\('save', editablePayload\)/)
  assert.doesNotMatch(form, /supplier_contact\.\w+|\/suppliers\/.*\/contacts/)
  assert.doesNotMatch(list, /\/suppliers\/.*\/contacts/)
})
test('Supplier contact read predicate remains dual-PBAC + group-root, pre-LIMIT', () => {
  assert.match(sql, /\$supplierContact->predicate\('c'\)/)
  assert.match(sql, /\$supplierMaster->predicate\('su'\)/)
  assert.match(sql, /su\.group_organization_id = \?/)
})
test('review-only candidate remains protected; not active SDK input', () => {
  assert.equal(review['x-do-not-generate-client'], true)
  assert.equal(review['x-review-only'], true)
  assert.deepEqual(review.paths, {})
  const config = read('frontend/openapi-ts.config.ts')
  assert.match(config, /\.\.\/contracts\/openapi\/openapi\.yaml/)
  assert.doesNotMatch(config, /p2-sup-contact\.review/)
})
