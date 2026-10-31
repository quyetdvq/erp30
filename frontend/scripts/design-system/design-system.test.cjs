// Node-only regression gates: no DOM/network/dependencies required.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const root = path.resolve(__dirname, '../..')
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8')
const tokenSource = read('src/styles/tokens.css')
const [lightBlock, darkSection] = tokenSource.split(":root[data-ds-theme='dark']")
assert.ok(darkSection, 'A root-level dark token palette is required for portals')

function tokens(source) {
  return Object.fromEntries(
    [...source.matchAll(/(--ds-[a-z0-9-]+)\s*:\s*([^;]+);/g)].map((m) => [m[1], m[2].trim()]),
  )
}

const light = tokens(lightBlock)
const dark = { ...light, ...tokens(darkSection) }
function luminance(hex) {
  assert.match(hex, /^#[\da-f]{6}$/i, `Expected an opaque sRGB color: ${hex}`)
  const parts = [1, 3, 5].map((index) => parseInt(hex.slice(index, index + 2), 16) / 255)
  const channels = parts.map((value) =>
    value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4,
  )
  return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722
}
function contrast(a, b) {
  const [low, high] = [luminance(a), luminance(b)].sort((x, y) => x - y)
  return (high + 0.05) / (low + 0.05)
}
function assertContrast(palette, foreground, background, minimum, name) {
  const ratio = contrast(palette[foreground], palette[background])
  assert.ok(ratio >= minimum, `${name} ${foreground}/${background}: ${ratio.toFixed(2)} < ${minimum}`)
}

for (const [name, palette] of [['light', light], ['dark', dark]]) {
  test(`${name} color contrast: text >= 4.5:1 on all shared surfaces`, () => {
    for (const text of ['--ds-text-primary', '--ds-text-secondary', '--ds-text-muted']) {
      for (const surface of ['--ds-bg-surface', '--ds-bg-subtle', '--ds-bg-elevated']) {
        assertContrast(palette, text, surface, 4.5, name)
      }
    }
  })
  test(`${name} color contrast: semantic text and button states >= 4.5:1`, () => {
    for (const semantic of ['primary', 'success', 'warning', 'danger', 'info']) {
      assertContrast(palette, `--ds-${semantic}`, `--ds-${semantic}-soft`, 4.5, name)
      assertContrast(palette, `--ds-${semantic}-contrast`, `--ds-${semantic}`, 4.5, name)
    }
    assertContrast(palette, '--ds-primary-contrast', '--ds-primary-hover', 4.5, name)
    assertContrast(palette, '--ds-danger-contrast', '--ds-danger-hover', 4.5, name)
  })
  test(`${name} color contrast: functional borders + focus >= 3:1`, () => {
    for (const surface of ['--ds-bg-surface', '--ds-bg-elevated']) {
      assertContrast(palette, '--ds-border', surface, 3, name)
      assertContrast(palette, '--ds-focus-ring', surface, 3, name)
    }
  })
}

test('Naive UI palette matches CSS tokens used by portalled components', () => {
  const source = read('src/styles/naiveTheme.ts')
  const [lightSection, darkSection] = source.split('export const naiveDarkOverrides')
  assert.ok(darkSection)
  const assertValues = (section, palette) => {
    const pairs = {
      primaryColor: '--ds-primary',
      primaryColorHover: '--ds-primary-hover',
      primaryColorPressed: '--ds-primary-pressed',
      infoColor: '--ds-info',
      successColor: '--ds-success',
      warningColor: '--ds-warning',
      errorColor: '--ds-danger',
      bodyColor: '--ds-bg-canvas',
      cardColor: '--ds-bg-surface',
      modalColor: '--ds-bg-elevated',
      popoverColor: '--ds-bg-elevated',
      textColor1: '--ds-text-primary',
      textColor2: '--ds-text-secondary',
      textColor3: '--ds-text-muted',
      borderColor: '--ds-border',
    }
    for (const [key, token] of Object.entries(pairs)) {
      const match = section.match(new RegExp(`\\b${key}:\\s*'([^']+)'`))
      assert.equal(match && match[1], palette[token], `Naive ${key} must match ${token}`)
    }
  }
  assertValues(lightSection, light)
  assertValues(darkSection, dark)
})

test('Global CSS imports every shared style exactly once; shell cannot bleed', () => {
  const global = read('src/styles/global.css')
  for (const file of ['tokens', 'base', 'buttons', 'forms', 'tables', 'modal', 'utilities']) {
    assert.equal(global.split(`@import './${file}.css';`).length - 1, 1)
  }
  for (const source of [global, read('src/components/app-shell/styles.css')]) {
    assert.doesNotMatch(source, /#[a-fA-F0-9]{3,8}\b/, 'Hard-coded palette color in shared styles')
  }
  assert.doesNotMatch(global, /^\.brand \{/m)
  assert.doesNotMatch(global, /^\.workspace-search \{/m)
})

test('All CSS custom property references resolve to the shared palette', () => {
  const files = ['base', 'buttons', 'forms', 'tables', 'modal', 'utilities', 'global'].map((name) =>
    `src/styles/${name}.css`,
  ).concat('src/components/app-shell/styles.css')
  for (const file of files) {
    for (const [, key] of read(file).matchAll(/var\((--ds-[a-z0-9-]+)/g)) {
      assert.ok(key in light, `Undefined token ${key} in ${file}`)
    }
  }
})

test('Theme updates the document root for all Naive portals', () => {
  const app = read('src/App.vue')
  assert.match(app, /document\.documentElement\.dataset\.dsTheme\s*=/)
  assert.match(app, /:theme-overrides="naiveThemeOverrides"/)
  assert.match(read('src/components/ui/AppModal.vue'), /<AppDialogHost/)
  assert.match(read('src/components/ui/AppDialogHost.vue'), /<NModal/)
})
