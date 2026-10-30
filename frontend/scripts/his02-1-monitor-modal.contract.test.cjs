'use strict'
const { test } = require('node:test')
const { strict: assert } = require('node:assert')
const fs = require('node:fs')
const path = require('node:path')
const dir = path.join(__dirname, '..', 'src')
const view = fs.readFileSync(path.join(dir, 'features/history/pages/HistoryMonitorView.vue'), 'utf8')
const columns = fs.readFileSync(path.join(dir, 'components/lists/TableRowActions.vue'), 'utf8')
const detail = fs.readFileSync(path.join(dir, 'components/lists/TableDetailModal.vue'), 'utf8')
const service = fs.readFileSync(path.join(dir, 'services/history.ts'), 'utf8')
const moduleSource = fs.readFileSync(path.join(__dirname, '..', '..', 'backend/app/Modules/History/HistoryRecorder.php'), 'utf8')
const customerSource = fs.readFileSync(path.join(__dirname, '..', '..', 'backend/app/Modules/Customer/CustomerService.php'), 'utf8')

test('HIS monitor only opens redacted authorized page records via standard view icon', () => {
  assert.match(view, /<TableRowActions\s+authorization-mode="effective"\s+:view-scope-verified="true"/)
  assert.match(view, /view-permission="business_history_monitor\.view"/)
  assert.match(view, /@view="selected = row"/)
  assert.match(columns, /<NIcon :component="EyeOutline"/)
  assert.match(view, /if \(!event \|\| !allowed\.value\) return \[\]/)
  assert.doesNotMatch(view, /secondary-button" @click="selected = row"/)
})
test('HIS event detail uses standard teleported modal with focus and escape handling', () => {
  assert.match(view, /<Teleport to="body">\s*<TableDetailModal/)
  assert.match(view, /:open="allowed && selected !== null"/)
  assert.match(view, /@close="selected = null"/)
  assert.doesNotMatch(view, /<section v-if="selected" class="content-panel history-monitor-details"/)
  assert.match(detail, /role="dialog"/)
  assert.match(detail, /aria-modal="true"/)
  assert.match(detail, /event\.key === 'Escape'/)
  assert.match(detail, /restoreFocus\(\)/)
})
test('HIS keeps formatter, redaction, SYSTEM scope and no direct arbitrary ID API', () => {
  assert.match(view, /formatters\.formatDateTime\(event\.created_at\)/)
  assert.match(view, /historyChangeDisplay\(selected\.value, t\('history\.redactedChange'\)\)/)
  assert.match(view, /history\.invalidChangesFormat/)
  assert.match(view, /monitorBusinessHistory\(/)
  assert.doesNotMatch(service, /history\/monitor\/\$\{/)
  assert.match(customerSource, /HistoryRecorder/)
  assert.match(moduleSource, /'customer'|\$resource/)
  for (const locale of ['vi', 'en']) {
    const translations = fs.readFileSync(path.join(dir, `i18n/locales/${locale}.ts`), 'utf8')
    for (const key of ['monitorAction', 'monitorChanges', 'monitorCheckpointRecorded', 'invalidChangesFormat']) {
      assert.match(translations, new RegExp(`\\b${key}:`))
    }
  }
})
