// NFX-D1 vendor-free smoke: run with Node >=22.18 using
// node --experimental-strip-types scripts/notification-settings-ux-pure.mjs
// Vitest remains the authoritative Vue component/interaction test runner.
import assert from 'node:assert/strict'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import { pathToFileURL } from 'node:url'

const base = dirname(fileURLToPath(import.meta.url))
const helper = await import(pathToFileURL(resolve(base, '../src/features/notifications/notificationSettingsPresentation.ts')).href)
let checks = 0
function same(actual, expected) { assert.deepEqual(actual, expected); checks++ }
const names = [
  'import.completed.in_app', 'import.completed_with_errors.in_app', 'import.failed.in_app',
  'workflow.action_required.in_app', 'security.permission_changed.in_app',
  'import.completed.email', 'import.completed_with_errors.email', 'import.failed.email',
  'import.completed.push', 'import.completed_with_errors.push', 'import.failed.push', 'workflow.action_required.push', 'future.sms',
]
const groups = helper.groupedNotificationRows(names.map((key) => ({ key })))
same(groups.map(({ channel, items }) => [channel, items.length]), [
  ['in_app', 5], ['email', 3], ['push', 4], ['other', 1],
])
same(groups.flatMap(({ items }) => items.map(({ key }) => key)).length, names.length)
same(helper.notificationLabelKey('import.failed.email'), 'importFailedEmail')
same(helper.notificationLabelKey('future.sms'), null)
same(helper.notificationChannel('future.sms'), 'other')
same(helper.isExplicitConsentChannel('import.failed.email'), true)
same(helper.isExplicitConsentChannel('import.completed_with_errors.email'), true)
same(helper.isExplicitConsentChannel('import.completed.push'), true)
same(helper.isExplicitConsentChannel('import.failed.in_app'), false)
same(helper.preferenceSelection(null), 'inherit')
same(helper.preferenceSelection(true), 'on')
same(helper.preferenceSelection(false), 'off')
const userRow = { key: 'import.failed.email', user_override: null }
same(helper.preferenceChanged(userRow, 'inherit'), false)
same(helper.preferenceChanged(userRow, 'on'), true)
same(helper.preferenceChanged(userRow, 'invalid'), false)
const systemRow = {
  key: 'import.failed.email', capability: 'AVAILABLE', mandatory: false,
  default_value: false, allow_user_override: true,
}
same(helper.policyCanSubmit(systemRow, { default_value: true, allow_user_override: true }), false)
same(helper.policyCanSubmit(systemRow, { default_value: false, allow_user_override: false }), true)
same(helper.policyCanSubmit({ ...systemRow, capability: 'UNAVAILABLE' }, {
  default_value: false, allow_user_override: false,
}), false)
same(helper.policyCanSubmit({ ...systemRow, mandatory: true }, {
  default_value: false, allow_user_override: false,
}), false)
same(helper.policyChanged(systemRow, { default_value: false, allow_user_override: true }), false)
for (const source of ['USER', 'SYSTEM_DEFAULT', 'SYSTEM_LOCKED', 'MANDATORY', 'CHANNEL_UNAVAILABLE']) {
  same(helper.preferenceSourceLabel(source).startsWith('source'), true)
}
same(helper.preferenceSourceLabel('from-server-${untrusted}'), 'sourceUnknown')
console.log(`NFX-D1 NOTIFICATION SETTINGS PURE SMOKE: ${checks} checks PASS`)
