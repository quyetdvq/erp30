// SUP-T06B2 contract review only. This script never edits canonical OpenAPI,
// generated SDK, router, catalog or database. It runs without pnpm/Vitest.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import test from 'node:test'

const root = join(dirname(fileURLToPath(import.meta.url)), '../..')
const read = (path) => readFileSync(join(root, path), 'utf8')
const candidate = JSON.parse(read('contracts/openapi/p2-sup-contact.review.openapi.json'))
const canonical = read('contracts/openapi/openapi.yaml')
const generated = read('frontend/src/api/generated/types.gen.ts')
const backendRules = read('backend/app/Modules/Contact/ContactRules.php')
const backendService = read('backend/app/Modules/Contact/ContactService.php')
const backendSql = read('backend/app/Modules/Contact/ContactSql.php')
const backendParent = read('backend/app/Modules/Contact/SupplierContactParentView.php')
const frontendPolicy = read('frontend/src/components/contacts/contactOwnerPolicy.ts')
const frontendQuery = read('frontend/src/components/contacts/contactListQuery.ts')
const schemas = candidate.components.schemas

const expected = {
  EMPLOYEE: 'employee_contact',
  ORGANIZATION: 'organization_contact',
  CUSTOMER: 'customer_contact',
  SUPPLIER: 'supplier_contact',
}
const expectedOwners = Object.keys(expected)

function block(yaml, start, end) {
  const startMark = `    ${start}:`
  const endMark = `    ${end}:`
  const schemasBegin = yaml.indexOf('\n  schemas:')
  assert.ok(schemasBegin >= 0, 'OpenAPI components.schemas missing')
  const begin = yaml.indexOf(startMark, schemasBegin)
  const stop = yaml.indexOf(endMark, begin + startMark.length)
  assert.ok(begin >= 0 && stop > begin, `Expected canonical OpenAPI blocks ${start} and ${end}`)
  return yaml.slice(begin, stop)
}

function propertiesOf(yamlBlock) {
  return [...yamlBlock.matchAll(/^        ([a-z_]+):/gm)].map((match) => match[1])
}

function parsePhpOwners(source) {
  const own = source.match(/private const OWNERS\s*=\s*\[([\s\S]*?)\];/)
  assert.ok(own, 'ContactRules OWNERS not found')
  return Object.fromEntries([...own[1].matchAll(/'([A-Z]+)'\s*=>\s*'([a-z_]+)'/g)].map((m) => [m[1], m[2]]))
}

function parseTsOwners(source) {
  const own = source.match(/const RESOURCE_BY_OWNER\s*=\s*\{([\s\S]*?)\}\s+as const/)
  assert.ok(own, 'contactOwnerPolicy RESOURCE_BY_OWNER not found')
  return Object.fromEntries([...own[1].matchAll(/\b([A-Z]+):\s*'([a-z_]+)'/g)].map((m) => [m[1], m[2]]))
}

test('candidate is explicitly review-only, never an active OpenAPI route source', () => {
  assert.equal(candidate.openapi, '3.1.0')
  assert.equal(candidate['x-review-only'], true)
  assert.equal(candidate['x-do-not-generate-client'], true)
  assert.deepEqual(candidate.paths, {})
  assert.equal(candidate['x-canonical-path'], 'contracts/openapi/openapi.yaml')
  assert.deepEqual(candidate['x-approval-required'], ['MOD-G2', 'PC-07', 'PC-06'])
})

test('candidate is self-contained for the five source schema dependencies', () => {
  const refs = JSON.stringify(schemas).match(/#\/components\/schemas\/\w+/g) ?? []
  for (const ref of refs) assert.ok(schemas[ref.split('/').at(-1)], `Unresolved ${ref}`)
})

test('contact owner enum is exactly the four authorized owner kinds', () => {
  assert.deepEqual(schemas.ContactOwnerType.enum, expectedOwners)
  assert.equal(schemas.ContactOwnerType.type, 'string')
  assert.equal(new Set(schemas.ContactOwnerType.enum).size, expectedOwners.length)
})

test('backend ContactRules four-owner resource mapping matches candidate exactly', () => {
  assert.deepEqual(parsePhpOwners(backendRules), expected)
})

test('frontend owner permission mapping matches candidate exactly', () => {
  assert.deepEqual(parseTsOwners(frontendPolicy), expected)
})

test('frontend unknown owner cannot inherit generic or employee permission', () => {
  assert.match(frontendPolicy, /return ''/)
  assert.ok(!/contactOwnerResource\([\s\S]*?\)\s*\{[\s\S]*?\?\?\s*'employee_contact'/.test(frontendPolicy))
})

test('read DTO and create DTO reference one canonical four-owner enum', () => {
  assert.deepEqual(schemas.Contact.properties.contact_type, { $ref: '#/components/schemas/ContactOwnerType' })
  assert.deepEqual(schemas.ContactCreate.properties.contact_type, { $ref: '#/components/schemas/ContactOwnerType' })
})

test('read Contact keeps required ID/owner/status and BIGINT string shape', () => {
  assert.deepEqual(schemas.Contact.required, ['id', 'contact_type', 'contact_ref_id', 'status'])
  assert.deepEqual(schemas.Contact.properties.contact_ref_id, { $ref: '#/components/schemas/BigInt' })
  assert.deepEqual(schemas.Contact.properties.id, { $ref: '#/components/schemas/BigInt' })
  assert.equal(schemas.BigInt.type, 'string')
})

test('candidate does not remove or rename any existing Contact response fields', () => {
  const canonicalFields = propertiesOf(block(canonical, 'Contact', 'ContactCreate'))
  const legacy = canonicalFields.filter((name) => name !== 'allowed_actions')
  assert.deepEqual(Object.keys(schemas.Contact.properties).filter((name) => name !== 'allowed_actions'), legacy)
})

test('create DTO keeps all existing fields and required owner/reference', () => {
  const legacy = propertiesOf(block(canonical, 'ContactCreate', 'ContactPatch'))
  assert.deepEqual(Object.keys(schemas.ContactCreate.properties), legacy)
  assert.deepEqual(schemas.ContactCreate.required, ['contact_type', 'contact_ref_id'])
})

test('candidate neither accepts status in ContactCreate nor invents Supplier-specific fields', () => {
  assert.equal(schemas.ContactCreate.properties.status, undefined)
  for (const key of ['company_id', 'group_organization_id', 'supplier_id', 'created_by']) {
    assert.equal(schemas.ContactCreate.properties[key], undefined)
  }
})

test('create description matches actual Lifecycle initial-state fail-closed behavior', () => {
  assert.match(schemas.ContactCreate.description, /WF_INITIAL_STATE_REQUIRED/)
  assert.match(backendService, /initialState\(\$owner\['resource'\]\)/)
  assert.match(backendService, /WF_INITIAL_STATE_REQUIRED/)
  assert.doesNotMatch(schemas.ContactCreate.description, /defaults?\s+to\s+native\s+ACTIVE/i)
})

test('ContactPatch exact legacy keys remain and cannot transfer owner/change status', () => {
  assert.deepEqual(Object.keys(schemas.ContactPatch.properties), propertiesOf(block(canonical, 'ContactPatch', 'Department')))
  for (const field of ['contact_type', 'contact_ref_id', 'status']) {
    assert.equal(schemas.ContactPatch.properties[field], undefined)
  }
  assert.match(backendRules, /CONTACT_OWNER_TRANSFER_UNSUPPORTED/)
  assert.match(backendRules, /CONTACT_STATE_REQUIRES_SETSTATE/)
})

test('read Contact adds optional boolean-only effective row action hints', () => {
  const hints = schemas.Contact.properties.allowed_actions
  assert.equal(hints.type, 'object')
  assert.equal(hints.additionalProperties, false)
  assert.deepEqual(Object.keys(hints.properties), ['edit', 'setstate'])
  for (const action of Object.keys(hints.properties)) assert.equal(hints.properties[action].type, 'boolean')
  assert.ok(!schemas.Contact.required.includes('allowed_actions'), 'write responses may omit hints')
})

test('Contact operation IDs stay identical to the existing five endpoints', () => {
  const old = block(canonical, 'Contact', 'ContactCreate')
  assert.match(old, /contact_type:/)
  for (const operationId of candidate['x-existing-operations-must-remain']) {
    assert.match(canonical, new RegExp(`operationId: ${operationId}\\b`))
  }
  assert.equal(candidate['x-existing-operations-must-remain'].length, 5)
})

test('list wire uses existing nested Contact filter, not flat Supplier list keys', () => {
  assert.match(canonical, /operationId: listContacts[\s\S]*?components\/parameters\/Filter'/)
  assert.match(backendSql, /private const FILTER\s*=\s*\['contact_type',\s*'contact_ref_id',\s*'status'\]/)
  assert.match(frontendQuery, /contact_type: ownerType/)
  assert.match(frontendQuery, /contact_ref_id: ownerId/)
  assert.doesNotMatch(frontendQuery, /filter:\s*\{[\s\S]*?company_id:/)
})

test('backend list does not default to ACTIVE and keeps the existing 200-row cap', () => {
  assert.match(backendSql, /page_size'\]\s*\?\?\s*'50'/)
  assert.match(backendSql, /\$size > 200/)
  assert.ok(!/filter\['status'\]\s*=\s*'ACTIVE'/.test(backendSql))
})

test('Supplier parent VIEW is independent from Supplier Contact rights', () => {
  assert.match(backendService, /supplierParentView/)
  assert.match(backendParent, /canView\('supplier'/)
  assert.match(backendSql, /\$supplierContact->predicate\('c'\)/)
  assert.match(backendSql, /\$supplierMaster->predicate\('su'\)/)
  assert.match(backendSql, /su\.group_organization_id = \?/)
})

test('candidate never narrows any legacy owner in pre-freeze or canonical four-owner mode', () => {
  for (const section of [block(canonical, 'Contact', 'ContactCreate'), block(canonical, 'ContactCreate', 'ContactPatch')]) {
    const original = section.match(/contact_type:\s*\{type: string, enum:\s*\[([^\]]+)\]\}/)
    const canonicalRef = section.match(/contact_type:\s*\{\$ref:\s*['"]#\/components\/schemas\/ContactOwnerType['"]\}/)
    assert.ok(original || canonicalRef, 'Canonical Contact owner schema absent')
    const owners = original
      ? original[1].split(',').map((x) => x.trim())
      : (canonical.match(/    ContactOwnerType:[\s\S]*?enum: \[([^\]]+)\]/)?.[1] ?? '').split(',').map((x) => x.trim())
    assert.ok(owners.length >= 2)
    for (const owner of owners) {
      assert.ok(expectedOwners.includes(owner), `Candidate removes canonical ${owner}`)
    }
    for (const owner of ['EMPLOYEE', 'ORGANIZATION']) assert.ok(owners.includes(owner), `Legacy owner ${owner} removed`)
  }
})

test('generated types remain a valid subset pending authorized MOD-G2 regeneration', () => {
  for (const typeName of ['Contact', 'ContactCreate']) {
    const typeBlock = generated.match(new RegExp(`export type ${typeName} = \\{([\\s\\S]*?)\\};`))
    assert.ok(typeBlock, `${typeName} generated type not found`)
    const match = typeBlock[1].match(/contact_type:\s*([^;]+);/)
    assert.ok(match)
    const actual = [...match[1].matchAll(/'([A-Z]+)'/g)].map((m) => m[1])
    if (match[1].trim() === 'ContactOwnerType') {
      assert.match(generated, /export type ContactOwnerType\s*=/)
    } else {
      assert.ok(actual.length >= 2)
      assert.ok(actual.every((owner) => expectedOwners.includes(owner)))
    }
  }
})
