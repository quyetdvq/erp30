import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const read = (file) => readFileSync(path.join(root, file), 'utf8').replaceAll('\r\n', '\n')
const page = read('frontend/src/features/supplier/pages/SuppliersView.vue')
const shell = read('frontend/src/components/AppShell.vue')
const css = read('frontend/src/components/app-shell/styles.css')
const vi = read('frontend/src/i18n/locales/vi.ts')
const en = read('frontend/src/i18n/locales/en.ts')
const controller = read('backend/app/Modules/Supplier/SupplierController.php')
const workflow = read('backend/app/Modules/Workflow/Execution/BusinessWorkflowService.php')

test('Supplier list must authorize successfully BEFORE optional lifecycle metadata is requested', () => {
  assert.match(page, /useBusinessWorkflowStates\('supplier', \[\], false\)/)
  const load = page.slice(page.indexOf('async function load():'), page.indexOf('function applyQuery('))
  assert.match(load, /await state\.apply\(\)/)
  assert.match(load, /if \(!stateMetadataRequested\)/)
  assert.match(load, /void refreshStatusOptions\(\)/)
  assert.ok(load.indexOf('await state.apply()') < load.indexOf('void refreshStatusOptions()'))
  assert.ok(load.indexOf('void refreshStatusOptions()') < load.indexOf('} catch'))
})

test('Supplier Lifecycle metadata remains server-derived; no fixed UI state options', () => {
  assert.match(page, /statusOptions\.value/)
  assert.doesNotMatch(page, /\{ value: '(?:DRAFT|ACTIVE|INACTIVE)', labelKey:/)
  assert.match(workflow, /\$this->states->definitionFor\(\$type\)/)
})

test('Supplier authentication and PBAC remain; obsolete server activation gate is retired', () => {
  assert.match(controller, /auth\(\)->user\(\)/)
  assert.match(workflow, /throw new DomainException\('AUTH_POLICY_DENIED'/)
  assert.doesNotMatch(controller, /SUPPLIER_API_NOT_ENABLED|ACCESS_MUTATION_NOT_ENABLED/)
  assert.doesNotMatch(page + shell, /grantPermission|db:seed/)
})

test('Supplier heading uses a real existing translation key in both locales', () => {
  assert.match(page, /t\('supplier\.desc'\)/)
  assert.match(vi, /supplier: \{[\s\S]*?desc: '/)
  assert.match(en, /supplier: \{[\s\S]*?desc: '/)
  assert.doesNotMatch(page, /supplier\.description/)
})

test('Supplier feature-disabled error has distinct bilingual meaning, not generic PBAC title', () => {
  assert.match(shell, /notice\.code === 'SUPPLIER_API_NOT_ENABLED'/)
  for (const messages of [vi, en]) {
    assert.match(messages, /apiNotEnabledTitle: '/)
    assert.match(messages, /apiNotEnabledMessage: '/)
  }
  assert.match(shell, /t\('supplier\.apiNotEnabledTitle'\)/)
  assert.match(shell, /t\('supplier\.apiNotEnabledMessage'\)/)
  assert.match(shell, /t\(`errors\.status\.\$\{notice\.status\}`\)/)
})

test('Error banner title and message are visually separated and retain diagnostic identifiers', () => {
  assert.match(shell, /class="error-copy"/)
  assert.match(css, /\.error-copy \{\n  display: block;/)
  assert.match(shell, /notice\.code \|\| t\('errors\.unknownCode'\)/)
  assert.match(shell, /notice\.requestId/)
})
