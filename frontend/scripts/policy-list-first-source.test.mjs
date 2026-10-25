import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { resolve, dirname } from 'node:path'
import test from 'node:test'

const src = resolve(dirname(fileURLToPath(import.meta.url)), '../src')
const view = readFileSync(resolve(src, 'features/security/pages/SecurityPoliciesView.vue'), 'utf8')
const refs = readFileSync(resolve(src, 'features/security/policy-editor/usePolicyReferences.ts'), 'utf8')
const participants = readFileSync(resolve(src, 'features/security/policy-editor/usePolicyParticipants.ts'), 'utf8')

test('Policy list is not blocked by the reference catalogs', () => {
  assert.match(view, /onMounted\(\(\) => \{\s*void load\(\)/)
  assert.match(view, /await state\.apply\(\)\s*loadListReferences\(state\.rows\.value\)/)
  assert.doesNotMatch(view, /await loadEditorReferences\(\)\s*await load\(\)/)
})

test('list labels load only for resources referenced on the page', () => {
  assert.match(refs, /if \(!rows\.length\) return/)
  assert.match(refs, /participantCatalog\[row\.participant_type\]/)
  assert.match(refs, /Promise\.allSettled/)
})

test('reference requests are per-view deduplicated with failure retries', () => {
  assert.match(refs, /const completed = new Set/)
  assert.match(refs, /const inFlight = new Map/)
  assert.match(refs, /\.finally\(\(\) => \{ inFlight\.delete\(key\) \}\)/)
  assert.doesNotMatch(refs, /sessionStorage|localStorage/)
})

test('View is a separate read-only modal with no mutation controls', () => {
  assert.match(view, /@view="openView\(row\)"/)
  assert.match(view, /@edit="openEdit\(row\)"/)
  const readOnly = view.split('v-if="viewingPolicy"')[1]?.split('v-if="modalOpen"')[0] ?? ''
  assert.match(readOnly, /policyView/)
  assert.doesNotMatch(readOnly, /v-model=|@submit|savePolicy|addAction|removeAction|type="submit"/)
})

test('Edit, Save and SetState fail closed on effective per-row PBAC', () => {
  assert.match(view, /rowAllowedActions\(policy\)\?\.edit !== true/)
  assert.match(view, /!canWriteForm\.value/)
  assert.match(view, /rowAllowedActions\(row\)\?\.setstate !== true/)
})

test('Participant options remain remote-searchable and race guarded', () => {
  assert.match(participants, /isRemoteParticipant = computed\(\(\) => true\)/)
  assert.match(participants, /listAuthorizationGroupPage\(listQuery\)/)
  assert.match(participants, /requestId !== participantRequestId/)
})
