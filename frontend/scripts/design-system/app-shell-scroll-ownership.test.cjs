// CSS-A02-HF09: AppShell owns ONE vertical workspace scrollport.
// Regression for the 46.4 CSS-px whole-page movement measured in DEV:
// an otherwise fitting four-row list must not gain ~47px of scroll range
// solely because of decorative bottom padding.
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const { test } = require('node:test')
const root = path.resolve(__dirname, '../..')
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')
const shellCss = read('src/components/app-shell/styles.css')
const template = read('src/components/AppShell.vue')
const tableCss = read('src/styles/tables.css')

function cssBody(selector) {
  const escape = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const rule = shellCss.match(new RegExp(`${escape(selector)}\\s*\\{([^}]+)\\}`))
  assert.ok(rule, `Missing rule: ${selector}`)
  return rule[1]
}

test('Naive workspace NLayout remains the sole vertical owner (sidebar is independent)', () => {
  assert.match(template, /<NLayout class="workspace-layout">[\s\S]*?<main class="workspace-content">[\s\S]*?<RouterView\s*\/>[\s\S]*?<\/main>[\s\S]*?<\/NLayout>/)
  assert.match(cssBody('.workspace-layout :deep(.n-layout-scroll-container)'), /scrollbar-gutter:\s*stable;/)
  assert.match(cssBody('.shell-body'), /overflow:\s*hidden;/)
})

test('main clips horizontally without becoming a second vertical scroll container', () => {
  const main = cssBody('.workspace-content')
  assert.match(main, /display:\s*flow-root;/, 'preserve original BFC and child margin containment')
  assert.match(main, /overflow-x:\s*clip;/, 'hidden would compute visible y overflow as auto')
  assert.match(main, /overflow-y:\s*visible;/)
  assert.doesNotMatch(main, /overflow-[xy]:\s*(?:auto|scroll|hidden);/)
})

test('desktop and mobile remove artificial bottom whitespace without changing top/inline gutters', () => {
  const desktop = cssBody('.workspace-content')
  assert.match(desktop, /padding:\s*26px clamp\(18px, 2\.2vw, 36px\) 0;/)
  const mobileSection = shellCss.split('@media (max-width: 800px)')[1]
  assert.ok(mobileSection, 'mobile shell breakpoint missing')
  assert.match(mobileSection, /\.workspace-content\s*\{\s*padding:\s*18px 14px 0;/)
  assert.doesNotMatch(mobileSection, /\.workspace-content\s*\{[^}]*padding:[^;}]*36px;/s)
})

test('Positions and Departments stay on the same shared table and horizontal scroll wrapper', () => {
  const positions = read('src/features/people/pages/PeoplePositionsView.vue')
  const departments = read('src/features/people/pages/PeopleDepartmentsView.vue')
  for (const [name, source] of [['Positions', positions], ['Departments', departments]]) {
    assert.match(source, /<ListTable\b/, `${name}: shared ListTable missing`)
    assert.match(source, /<ListPagination\b/, `${name}: pagination missing`)
    assert.match(source, /<ListQueryControls\b/, `${name}: filters missing`)
  }
  assert.match(tableCss, /\.list-table-wrap,\s*\.ds-table-scroll\s*\{[^}]*overflow-x:\s*auto;/s,
    'retain intentional mobile/horizontal table scroll')
})
