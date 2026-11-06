// PR01: Profile presentation is feature-scoped. Business logic and shared controls remain intact.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const root = path.resolve(__dirname, '../..')
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')
const profile = read('src/features/account/pages/ProfileView.vue')
const avatar = read('src/components/users/UserAvatarManager.vue')
const pageLayout = read('src/styles/page-layout.css')
const style = profile.split('<style scoped>')[1]?.split('</style>')[0] || ''

test('Profile presents account summary and edit/security sections without duplicating upload logic', () => {
  assert.match(profile, /class="profile-workspace"/)
  assert.match(profile, /<aside class="content-panel profile-summary"/)
  assert.match(profile, /class="content-panel form-panel"/)
  assert.match(profile, /id="profile-account-heading"/)
  assert.match(profile, /id="profile-security-heading"/)
  assert.match(profile, /class="profile-summary__access"/)
  assert.match(profile, /v-for="group in auth\.user\?\.groups \|\| \[\]"/)
  assert.match(profile, /<UserAvatarManager[\s\S]*?@updated="void avatarUpdated\(\)"/)
  assert.match(avatar, /<slot name="identity"\s*\/>/)
  assert.match(avatar, /@change="chooseFile"/)
  assert.match(avatar, /@click="void upload\(\)"/)
  assert.match(avatar, /@click="void remove\(\)"/)
})

test('Profile retains one editable form, four fields, dirty guard and existing API contract', () => {
  assert.equal([...profile.matchAll(/<form\b/g)].length, 1)
  assert.match(profile, /<form class="profile-form" @submit\.prevent="save">/)
  for (const field of ['profile-username', 'profile-fullname', 'profile-email', 'profile-password']) {
    assert.match(profile, new RegExp(`id="${field}"`))
    assert.match(profile, new RegExp(`for="${field}"`))
  }
  assert.match(profile, /profileNamePatch\(auth\.user\?\.name \?\? null, name\.value\)/)
  assert.match(profile, /await auth\.saveProfile\(\{[\s\S]*?username: username\.value,[\s\S]*?email: email\.value,[\s\S]*?\.\.\.displayPatch,[\s\S]*?password: password\.value \|\| undefined/)
  assert.match(profile, /const unsaved = useUnsavedChanges/)
  assert.match(profile, /unsaved\.markClean\(\)/)
  assert.match(profile, /data-ds-icon="save" class="primary-button" type="submit" :disabled="busy"/)
  assert.match(profile, /v-if="nameError"/)
  assert.match(profile, /role="status">Đã cập nhật profile\./)
})

test('All Profile input chrome remains owned by forms.css and action buttons by buttons.css', () => {
  assert.doesNotMatch(style, /(?:\binput\s*\{|\btextarea\s*\{|\bselect\s*\{|\.field-input\s*\{|\.primary-button\s*\{)/)
  assert.match(profile, /class="field-input"/)
  assert.match(style, /var\(--ds-control-label-color\)/)
  assert.match(style, /var\(--ds-text-muted\)/)
  assert.match(style, /var\(--ds-success\)/)
  assert.doesNotMatch(style, /#[\da-f]{3,8}\b/i)
})

test('Layout is owned by ProfileView, with container-based desktop/tablet/mobile variants', () => {
  assert.match(style, /\.profile-workspace\s*\{[^}]*container-type:\s*inline-size;/)
  assert.match(style, /\.profile-layout\s*\{[^}]*grid-template-columns:\s*minmax\(270px, 316px\) minmax\(0, 1fr\)/)
  assert.match(style, /@container \(max-width: 950px\)[\s\S]*?\.profile-layout\s*\{\s*grid-template-columns:\s*minmax\(0, 1fr\)/)
  assert.match(style, /@container \(max-width: 640px\)[\s\S]*?\.profile-form__grid\s*\{\s*grid-template-columns:\s*minmax\(0, 1fr\)/)
  assert.match(style, /@container \(min-width: 700px\) and \(max-width: 950px\)/)
  assert.doesNotMatch(pageLayout, /\.(?:profile-layout|profile-summary|form-panel)\s*\{/,
    'Profile must not have a second layout owner in shared page CSS')
})

test('Profile retains accessible labels, feedback, and password guidance', () => {
  assert.match(profile, /aria-label="Tổng quan hồ sơ"/)
  assert.match(profile, /aria-labelledby="profile-groups-heading"/)
  assert.match(profile, /:aria-invalid="Boolean\(nameError\)"/)
  assert.match(profile, /:aria-describedby="nameError \? 'profile-name-error' : undefined"/)
  assert.match(profile, /autocomplete="new-password"/)
  assert.match(profile, /minlength="8"/)
  assert.match(profile, /aria-describedby="profile-password-help"/)
  assert.match(profile, /class="profile-form__unsaved"/)
})
