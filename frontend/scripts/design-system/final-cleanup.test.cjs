// DS5 final source and contrast gates. No server, database or installed UI dependencies.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const root = path.resolve(__dirname, '../..')
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')
const files = (dir) => fs.readdirSync(path.join(root, dir), { withFileTypes: true })
  .flatMap((entry) => entry.isDirectory() ? files(`${dir}/${entry.name}`) : [`${dir}/${entry.name}`])
const vueFiles = files('src').filter((name) => name.endsWith('.vue'))
const cssFiles = files('src/styles').filter((name) => name.endsWith('.css'))
  .concat('src/components/app-shell/styles.css')
const cssImports = [...read('src/styles/global.css').matchAll(/@import '\.\/([^']+\.css)';/g)]
  .map((match) => match[1])
const tokenSrc = read('src/styles/tokens.css')
const [lightSource, darkSource] = tokenSrc.split(":root[data-ds-theme='dark']")
assert.ok(darkSource, 'The two root-level palettes are required for Naive UI portals')
const parseTokens = (source) => Object.fromEntries(
  [...source.matchAll(/(--ds-[a-z0-9-]+)\s*:\s*([^;]+);/g)].map((m) => [m[1], m[2].trim()]),
)
const palettes = { light: parseTokens(lightSource) }
palettes.dark = { ...palettes.light, ...parseTokens(darkSource) }
const luminance = (hex) => {
  assert.match(hex, /^#[0-9a-f]{6}$/i, `Not an opaque sRGB color: ${hex}`)
  const channels = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
    .map((c) => c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4)
  return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722
}
const contrast = (a, b) => {
  const [low, high] = [luminance(a), luminance(b)].sort((x, y) => x - y)
  return (high + 0.05) / (low + 0.05)
}
const check = (palette, foreground, background, min, theme) => {
  const ratio = contrast(palette[`--ds-${foreground}`], palette[`--ds-${background}`])
  assert.ok(ratio >= min, `${theme}: ${foreground} on ${background}: ${ratio.toFixed(2)} < ${min}`)
}

test('All shared CSS imports resolve, remain unique, and global.css no longer owns view selectors', () => {
  const global = read('src/styles/global.css')
  assert.deepEqual(cssImports, ['tokens.css', 'base.css', 'buttons.css', 'forms.css',
    'tables.css', 'detail-workspace.css', 'modal.css', 'utilities.css', 'page-layout.css'])
  assert.equal(new Set(cssImports).size, cssImports.length)
  for (const item of cssImports) assert.ok(fs.existsSync(path.join(root, 'src/styles', item)))
  assert.doesNotMatch(global, /\{[^}]*\}/, 'Global CSS must only import shared contracts')
  assert.match(read('src/main.ts'), /import '\.\/styles\/global\.css'/)
})

test('Retired AppFrame and command palette cannot cascade over current AppShell/Naive UI', () => {
  const retired = /\.(?:app-frame|topbar|sidebar--collapsed|sidebar-collapse|sidebar-resize|command-palette|command-heading|command-item|command-footer|user-chip|workspace-tab|menu-trigger|language-button)\b/
  const pageCSS = read('src/styles/page-layout.css')
  assert.doesNotMatch(pageCSS, retired)
  assert.doesNotMatch(pageCSS, /\.overlay\s*\{|z-index\s*:/)
  for (const live of ['page-heading', 'content-panel', 'empty-state', 'metric-grid',
    'metric-card', 'dashboard-blank', 'auth-screen', 'profile-layout', 'profile-summary', 'form-panel']) {
    assert.match(pageCSS, new RegExp(`\\.${live}\\s*\\{`), `Lost active page layout ${live}`)
  }
  assert.match(read('src/styles/tables.css'), /max-width:\s*700px[\s\S]*?\.data-table\s*\{\s*min-width:\s*680px/)
})

test('Every CSS variable used in shared, AppShell and SFC styles resolves in both palettes', () => {
  for (const file of [...cssFiles, ...vueFiles]) {
    const content = read(file)
    const styles = file.endsWith('.vue')
      ? [...content.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/g)].map((m) => m[1]).join('\n')
      : content
    for (const [, key] of styles.matchAll(/var\((--ds-[a-z0-9-]+)/g)) {
      assert.ok(key in palettes.light && key in palettes.dark, `${file}: undefined ${key}`)
    }
  }
})

test('All 81 Vue styles avoid light-only hex colors and document-global body overrides', () => {
  assert.equal(vueFiles.length, 81, 'Review scope if the Frontend component inventory changes')
  for (const file of vueFiles) {
    const src = read(file)
    for (const [, style] of src.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/g)) {
      assert.doesNotMatch(style, /#[\da-f]{3,8}\b/i, `${file}: use semantic tokens, not fixed light colors`)
      assert.doesNotMatch(style, /:global\(\s*(?:body|html|\*)\s*\)/,
        `${file}: only base.css may modify body or html globally`)
    }
  }
})

test('Profile, Avatar, Breadcrumb and Dashboard derive contrast from shared tokens', () => {
  const expected = {
    'src/components/AppBreadcrumbs.vue': ['--ds-text-muted', '--ds-text-secondary', '--ds-text-primary', '--ds-focus-ring'],
    'src/components/users/UserAvatar.vue': ['--ds-primary-soft', '--ds-primary'],
    'src/features/account/pages/ProfileView.vue': ['--ds-text-muted', '--ds-success'],
    'src/features/workspace/pages/DashboardView.vue': ['--ds-primary-soft', '--ds-success'],
  }
  for (const [file, values] of Object.entries(expected)) {
    const source = read(file)
    for (const key of values) assert.ok(source.includes(`var(${key})`), `${file}: missing ${key}`)
  }
  assert.doesNotMatch(read('src/components/AppBreadcrumbs.vue'), /:global\(\.erp-shell\.theme-dark\)/)
})

test('Connectivity, import flows, notifications and settings have no private palette/button owner', () => {
  const connectivity = read('src/features/settings/pages/ConnectivityView.vue')
  assert.match(connectivity, /class="check-button primary-button"/)
  assert.match(connectivity, /@click="checkConnection"/)
  assert.doesNotMatch(connectivity, /:global\(body\)/)
  for (const file of ['src/features/import/shared/ImportWizard.vue',
    'src/features/product-import/pages/ProductImportView.vue',
    'src/features/product-import/pages/ProductImportJobsView.vue',
    'src/features/product-import/pages/ProductImportJobDetailView.vue']) {
    const style = read(file).split('<style scoped>')[1] || ''
    assert.doesNotMatch(style, /\.(?:primary-button|secondary-button)\s*\{/,
      `${file}: do not override action buttons with scoped CSS`)
  }
  for (const file of ['src/features/account/pages/DisplayPreferencesView.vue',
    'src/features/settings/pages/DisplaySettingsView.vue']) {
    const source = read(file)
    assert.match(source, /class="m11-tabs ds-settings-tabs"/)
    assert.match(source, /role="tablist"/)
    assert.match(source, /:aria-selected=/)
    assert.doesNotMatch(source, /\.m11-tabs\s*(?:\{|button)/, `${file}: duplicated tab CSS`)
  }
  assert.match(read('src/styles/detail-workspace.css'), /\.ds-settings-tabs\s*>\s*button\[aria-selected='true'\]/)
  assert.match(read('src/styles/detail-workspace.css'), /\.ds-settings-tabs\s*>\s*button:focus-visible/)
})

for (const [theme, palette] of Object.entries(palettes)) {
  test(`${theme}: breadcrumbs, statuses and account content meet WCAG AA on the canvas`, () => {
    for (const fg of ['text-muted', 'text-secondary', 'text-primary', 'primary', 'success', 'danger']) {
      check(palette, fg, 'bg-canvas', 4.5, theme)
    }
    for (const fg of ['primary', 'success', 'warning', 'danger']) {
      check(palette, fg, `${fg}-soft`, 4.5, theme)
    }
  })
  test(`${theme}: fixed login spotlight text/accent meets WCAG AA on the brand surface`, () => {
    for (const fg of ['auth-brand-text', 'auth-brand-subtle', 'auth-brand-accent', 'auth-brand-success']) {
      check(palette, fg, 'auth-brand-bg', 4.5, theme)
    }
    const login = read('src/features/auth/pages/LoginView.vue')
    assert.match(login, /background:\s*var\(--ds-auth-brand-bg\)/)
    assert.match(login, /background:\s*var\(--ds-bg-surface\)/)
    assert.match(login, /background:\s*var\(--ds-bg-canvas\)/)
  })
}

test('Reduced motion keeps all transitions configurable from one shared stylesheet', () => {
  assert.match(read('src/styles/base.css'), /prefers-reduced-motion:\s*reduce[\s\S]*?--ds-motion-fast:\s*0ms;[\s\S]*?--ds-motion-normal:\s*0ms;/)
  assert.match(read('src/features/settings/pages/DisplaySettingsDisplayTab.vue'), /prefers-reduced-motion:\s*reduce[\s\S]*?animation:\s*none;/)
})
