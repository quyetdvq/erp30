// DS5 HF1: isolated browser geometry + text-selection contrast checks.
// No API, database, login or production settings required.
import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const sharedStyles = [
  'tokens.css', 'base.css', 'buttons.css', 'forms.css', 'tables.css',
  'detail-workspace.css', 'modal.css', 'utilities.css', 'page-layout.css',
].map((name) => readFileSync(resolve(process.cwd(), 'src/styles', name), 'utf8'))

const html = `<!doctype html><html><body>
  <main style="padding:12px;display:grid;gap:12px;max-width:650px">
    <p id="copy-text">Selection must contrast clearly with hover highlighting.</p>
    <div style="display:flex;gap:10px;align-items:center">
      <button id="primary" class="primary-button" data-ds-icon="save">Save</button>
      <button id="secondary" class="secondary-button" data-ds-icon="cancel">Cancel</button>
      <button id="danger" class="ds-button ds-button--danger-outline">Remove</button>
      <button id="icon" class="ds-icon-control" data-ds-icon="next" aria-label="Next"></button>
    </div>
    <div class="ds-dialog-host" style="width:420px">
      <section class="ds-dialog-surface" role="dialog" aria-label="Demo">
        <header><span>Demo dialog</span><button id="close" class="modal-card-button-close" data-ds-icon="close" aria-label="Close"></button></header>
        <div class="ds-dialog-stack-body">
          <label>Code<input id="native-input" value="Some selectable text" /></label>
          <div class="n-base-selection" style="min-height:36px"><input id="internal-input" value="Naive UI inner input" style="border:0;background:transparent" /></div>
        </div>
      </section>
    </div>
    <label class="list-query-controls__field">Search<input id="filter" /></label>
    <div class="ds-detail-tabs" role="tablist"><button id="tab" role="tab">Overview</button></div>
    <div class="list-table-wrap"><table class="list-table"><tbody><tr id="row"><td>Hover this row</td></tr></tbody></table></div>
  </main>
</body></html>`

async function start(page: Page, theme: 'light' | 'dark'): Promise<void> {
  await page.setViewportSize({ width: 1000, height: 850 })
  await page.setContent(html)
  for (const css of sharedStyles) await page.addStyleTag({ content: css })
  await page.evaluate((value) => { document.documentElement.dataset.dsTheme = value }, theme)
}

type Geometry = {
  width: number
  height: number
  top: number
  left: number
  padding: string
  border: string
  sizing: string
}
async function dimensions(page: Page, selector: string): Promise<Geometry> {
  return page.locator(selector).evaluate((el) => {
    const box = el.getBoundingClientRect()
    const cs = getComputedStyle(el)
    return {
      width: box.width,
      height: box.height,
      top: box.top,
      left: box.left,
      padding: cs.padding,
      border: [cs.borderTopWidth, cs.borderRightWidth, cs.borderBottomWidth, cs.borderLeftWidth].join(' '),
      sizing: cs.boxSizing,
    }
  })
}

for (const theme of ['light', 'dark'] as const) {
  test(`${theme}: hover/focus never changes shared button, form, modal, tab or row geometry`, async ({ page }) => {
    await start(page, theme)
    const targets = ['#primary', '#secondary', '#danger', '#icon', '#close', '#native-input', '#filter', '#tab', '#row']
    for (const selector of targets) {
      const before = await dimensions(page, selector)
      expect(before.sizing, `${selector}: unexpected sizing`).toBe('border-box')
      await page.locator(selector).hover()
      const during = await dimensions(page, selector)
      expect(during, `${theme}: ${selector} changed size or position on hover`).toEqual(before)
      if (selector !== '#row') {
        await page.locator(selector).focus()
        const focused = await dimensions(page, selector)
        expect(focused, `${theme}: ${selector} changed size or position on focus`).toEqual(before)
      }
    }
    // Naive UI owns its own nested input. The modal reset must not add a second border.
    await expect(page.locator('#internal-input')).toHaveCSS('border-top-width', '0px')
    await expect(page.locator('#native-input')).toHaveCSS('border-top-width', '1px')
  })

  test(`${theme}: browser selection differs from hover/selected surfaces and has readable text`, async ({ page }) => {
    await start(page, theme)
    const selected = await page.locator('#copy-text').evaluate((el) => {
      const range = document.createRange()
      range.selectNodeContents(el)
      window.getSelection()?.removeAllRanges()
      window.getSelection()?.addRange(range)
      const rules = getComputedStyle(document.documentElement)
      const selection = getComputedStyle(el, '::selection')
      const resolveColor = (value: string): string => {
        const probe = document.createElement('div')
        probe.style.backgroundColor = value
        el.appendChild(probe)
        const result = getComputedStyle(probe).backgroundColor
        probe.remove()
        return result
      }
      return {
        content: window.getSelection()?.toString() ?? '',
        text: selection.color,
        background: selection.backgroundColor,
        tokenBg: resolveColor(rules.getPropertyValue('--ds-selection-bg').trim()),
        tokenText: resolveColor(rules.getPropertyValue('--ds-selection-text').trim()),
        hover: resolveColor(rules.getPropertyValue('--ds-bg-hover').trim()),
        active: resolveColor(rules.getPropertyValue('--ds-bg-selected').trim()),
      }
    })
    expect(selected.content).toContain('Selection must contrast clearly')
    expect(selected.background).toBe(selected.tokenBg)
    expect(selected.text).toBe(selected.tokenText)
    expect(selected.background).not.toBe(selected.hover)
    expect(selected.background).not.toBe(selected.active)
  })
}
