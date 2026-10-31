// DS5 presentation regression: isolated visual fixtures, no API/login fixture required.
// These are NOT a replacement for authenticated feature/browser acceptance.
import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const load = (path: string): string => readFileSync(resolve(process.cwd(), path), 'utf8')
const sharedStyles = [
  'tokens.css', 'base.css', 'buttons.css', 'forms.css', 'tables.css',
  'detail-workspace.css', 'modal.css', 'utilities.css', 'page-layout.css',
].map((name) => load(`src/styles/${name}`))
const vueStyle = (component: string): string =>
  [...load(component).matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/g)]
    .map((match) => match[1]).join('\n')

const breadcrumbCSS = vueStyle('src/components/AppBreadcrumbs.vue')
const avatarCSS = vueStyle('src/components/users/UserAvatar.vue')
const wizardCSS = vueStyle('src/features/import/shared/ImportWizard.vue')
const loginCSS = vueStyle('src/features/auth/pages/LoginView.vue')

async function addStyles(page: import('@playwright/test').Page, extras: string[] = []): Promise<void> {
  for (const style of [...sharedStyles, ...extras]) await page.addStyleTag({ content: style })
}

async function rootColor(page: import('@playwright/test').Page, name: string): Promise<string> {
  return page.evaluate((variable) => {
    const color = getComputedStyle(document.documentElement).getPropertyValue(variable).trim()
    const probe = document.createElement('div')
    probe.style.color = color
    document.body.appendChild(probe)
    const result = getComputedStyle(probe).color
    probe.remove()
    return result
  }, name)
}

for (const theme of ['light', 'dark']) {
  test(`${theme}: Breadcrumb, avatar, settings tabs and import form use the same palette`, async ({ page }) => {
    await page.setViewportSize({ width: 1120, height: 850 })
    await page.setContent(`<!doctype html><html><body>
      <nav class="app-breadcrumbs" aria-label="Breadcrumb"><ol>
        <li><a class="breadcrumb-link" href="#">Products</a></li>
        <li><span class="breadcrumb-separator">/</span><span class="breadcrumb-current">Price Lists</span></li>
      </ol></nav>
      <span class="user-avatar"><span role="img" aria-label="Account">A</span></span>
      <div class="ds-settings-tabs" role="tablist" aria-label="Settings">
        <button id="selected-tab" type="button" role="tab" aria-selected="true">Display</button>
        <button id="unselected-tab" type="button" role="tab" aria-selected="false">Notifications</button>
      </div>
      <section class="import-wizard" aria-label="Import wizard">
        <div class="download-templates"><a class="template-link" href="#">Template</a></div>
        <p class="remote-select-feedback">Select a file to import</p>
        <p class="inline-error">Invalid file</p>
        <button class="primary-button" type="button" data-ds-icon="upload">Upload</button>
      </section>
    </body></html>`)
    await addStyles(page, [breadcrumbCSS, avatarCSS, wizardCSS])
    await page.evaluate((mode) => { document.documentElement.dataset.dsTheme = mode }, theme)

    await expect(page.locator('.app-breadcrumbs')).toHaveCSS('color', await rootColor(page, '--ds-text-muted'))
    await expect(page.locator('.breadcrumb-link')).toHaveCSS('color', await rootColor(page, '--ds-text-secondary'))
    await expect(page.locator('.breadcrumb-current')).toHaveCSS('color', await rootColor(page, '--ds-text-primary'))
    await expect(page.locator('.user-avatar')).toHaveCSS('color', await rootColor(page, '--ds-primary'))
    await expect(page.locator('#selected-tab')).toHaveCSS('color', await rootColor(page, '--ds-primary-contrast'))
    await expect(page.locator('#unselected-tab')).toHaveCSS('color', await rootColor(page, '--ds-text-primary'))
    await expect(page.locator('.inline-error')).toHaveCSS('color', await rootColor(page, '--ds-danger'))
    await expect(page.locator('.import-wizard')).toHaveCSS('background-color',
      await page.evaluate(() => {
        const source = getComputedStyle(document.documentElement).getPropertyValue('--ds-bg-surface').trim()
        const el = document.createElement('div')
        el.style.backgroundColor = source
        document.body.appendChild(el)
        const result = getComputedStyle(el).backgroundColor
        el.remove()
        return result
      }))
  })

  test(`${theme}: login's form follows the theme while the branded spotlight is readable`, async ({ page }) => {
    await page.setViewportSize({ width: 1160, height: 850 })
    await page.setContent(`<!doctype html><html><body>
      <main class="auth-screen"><div class="auth-layout">
        <aside class="auth-spotlight"><p class="spotlight-lead">Welcome to the ERP workspace</p></aside>
        <section class="auth-panel"><header class="auth-panel-header">Workspace</header>
          <div class="auth-form-wrap"><h1>Sign in</h1><p class="auth-lead">Continue</p>
            <input aria-label="Email" /><button type="button" class="primary-button">Sign in</button>
          </div>
        </section>
      </div></main>
    </body></html>`)
    await addStyles(page, [loginCSS])
    await page.evaluate((mode) => { document.documentElement.dataset.dsTheme = mode }, theme)
    await expect(page.locator('.auth-form-wrap h1')).toHaveCSS('color', await rootColor(page, '--ds-text-primary'))
    await expect(page.locator('.auth-lead')).toHaveCSS('color', await rootColor(page, '--ds-text-secondary'))
    await expect(page.locator('.spotlight-lead')).toHaveCSS('color', await rootColor(page, '--ds-auth-brand-subtle'))
    await expect(page.locator('.auth-screen')).toHaveCSS('background-color',
      theme === 'dark' ? 'rgb(15, 23, 33)' : 'rgb(244, 247, 251)')
    await page.setViewportSize({ width: 375, height: 740 })
    await expect(page.locator('.auth-spotlight')).toBeHidden()
    await expect(page.locator('.auth-form-wrap h1')).toBeVisible()
  })
}

test('Reduced motion policy disables shared animation timing tokens', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.setContent('<!doctype html><html><body><button type="button">Save</button></body></html>')
  await addStyles(page)
  expect(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--ds-motion-fast').trim())).toBe('0ms')
  expect(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--ds-motion-normal').trim())).toBe('0ms')
})
