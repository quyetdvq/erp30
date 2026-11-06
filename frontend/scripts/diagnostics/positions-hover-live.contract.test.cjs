const { test } = require('node:test')
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const { execFileSync } = require('node:child_process')

const frontend = join(__dirname, '..', '..')
const view = readFileSync(join(frontend, 'src/features/people/pages/PeoplePositionsView.vue'), 'utf8')
const path = join(frontend, 'public/diagnostics/positions-hover-live.js')
const probe = readFileSync(path, 'utf8')

test('Positions trace is loaded ONLY via a URL opt-in on the intended route', () => {
  assert.match(view, /new URLSearchParams\(window\.location\.search\)\.get\('positions_trace'\) !== '1'/)
  assert.match(view, /script\.src = '\/diagnostics\/positions-hover-live\.js'/)
  assert.match(probe, /location\.pathname\.endsWith\(PATH\)/)
  assert.match(view, /onMounted\(\(\) => void load\(\)\)/)
})

test('Trace is read-only, does not modify table/CSS or call app backend', () => {
  assert.doesNotMatch(probe, /\bfetch\(|\bXMLHttpRequest\b|\.post\(|localStorage|sessionStorage/)
  assert.match(probe, /getBoundingClientRect\(/)
  assert.match(probe, /window\.devicePixelRatio/)
  assert.match(probe, /PerformanceObserver/)
  assert.match(probe, /MutationObserver/)
  assert.match(probe, /requestAnimationFrame/)
  assert.match(probe, /ELEMENT_GEOMETRY/)
  assert.match(probe, /SCROLL_OR_SIZE/)
  assert.match(probe, /Export JSON/)
})

test('Standalone browser script has valid JavaScript syntax', () => {
  execFileSync(process.execPath, ['--check', path], { stdio: 'pipe' })
})

// HF08: prior trace could show a native 46.4px scroll but not its trigger.
test('Trace preserves input-to-scroll attribution while remaining URL opt-in', () => {
  assert.match(probe, /const inputEvents = \[\]/)
  assert.match(probe, /const scrollAttributions = \[\]/)
  assert.match(probe, /document\.addEventListener\('wheel', onWheel/)
  assert.match(probe, /document\.addEventListener\('keydown', onKeyDown/)
  assert.match(probe, /instrument\(Element\.prototype, 'scrollIntoView'\)/)
  assert.match(probe, /instrument\(HTMLElement\.prototype, 'focus'\)/)
  assert.match(probe, /methodRestores\.reverse\(\)\.forEach/)
  assert.match(probe, /scrollAttributions, frames: entries/)
  assert.doesNotMatch(probe, /\bfetch\(|\bXMLHttpRequest\b|localStorage|sessionStorage/)
})
