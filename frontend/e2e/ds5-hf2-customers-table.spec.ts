// CSS geometry regression for the Customer table. This is a DOM fixture using
// production Design System CSS, not an authenticated customer API test.
import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const sharedCSS = ['tokens.css', 'base.css', 'buttons.css', 'forms.css', 'tables.css']
  .map((name) => readFileSync(resolve(process.cwd(), 'src/styles', name), 'utf8'))

const columns = ['Code', 'Name', 'Type', 'Group', 'Phone', 'Status', 'Actions']
const example = `<div class="list-table-wrap list-table-wrap--sticky-actions" id="customers-scroll">
  <table class="list-table" id="customers-table"><thead><tr>${columns.map((label) => `<th>${label}</th>`).join('')}</tr></thead>
  <tbody><tr id="customer-row">
    <td><strong>CUS-000019</strong></td><td>Northwind Example Customer</td><td>Organization</td>
    <td>Business</td><td>+84 912 000 000</td><td><span class="table-status table-status--active" aria-label="Active">✓</span> Active</td>
    <td id="customer-actions"><span class="table-row-actions">
      <button id="customer-view" type="button" class="ds-icon-control" aria-label="View">V</button>
      <button id="customer-edit" type="button" class="ds-icon-control" aria-label="Edit">E</button>
      <button id="customer-state" type="button" class="ds-icon-control" aria-label="Change status">S</button>
    </span></td>
  </tr></tbody></table></div>`

async function mountFixture(page: Page, theme: 'light' | 'dark', width: number): Promise<void> {
  await page.setViewportSize({ width, height: 700 })
  await page.setContent(`<html><body><main style="width:100%;max-width:1180px;padding:16px">${example}</main></body></html>`)
  for (const css of sharedCSS) await page.addStyleTag({ content: css })
  await page.evaluate((mode) => { document.documentElement.dataset.dsTheme = mode }, theme)
}

for (const theme of ['light', 'dark'] as const) {
  test(`${theme}: Customer Actions stays visible at 720px and hover/focus do not shift cells`, async ({ page }) => {
    await mountFixture(page, theme, 720)
    const scroll = page.locator('#customers-scroll')
    const actions = page.locator('#customer-actions')
    const table = page.locator('#customers-table')
    await expect(table).toHaveCSS('min-width', '1040px')
    expect(await scroll.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true)

    const geometry = async () => actions.evaluate((el) => {
      const rect = el.getBoundingClientRect()
      return { x: Math.round(rect.x), y: Math.round(rect.y), width: Math.round(rect.width), height: Math.round(rect.height) }
    })
    const original = await geometry()
    const containerRight = await scroll.evaluate((el) => el.getBoundingClientRect().right)
    expect(Math.abs(original.x + original.width - containerRight)).toBeLessThanOrEqual(4)

    await scroll.evaluate((el) => { el.scrollLeft = el.scrollWidth - el.clientWidth })
    expect(await geometry()).toEqual(original)
    await page.locator('#customer-edit').hover()
    const hovered = await geometry()
    expect(hovered).toEqual(original)
    // Table-specific hover is intentionally softer than the button hover token.
    // Sticky surfaces must be opaque immediately; a 140ms intermediate alpha
    // would reveal horizontally scrolled text through the Actions column.
    const hoverColor = theme === 'light' ? 'rgb(234, 242, 252)' : 'rgb(38, 57, 72)'
    await expect(actions).toHaveCSS('background-color', hoverColor)
    await expect(actions).toHaveCSS('transition-duration', '0s')
    await expect(page.locator('#customer-row td').first()).toHaveCSS('transition-duration', '0s')
    await page.locator('#customer-edit').focus()
    expect(await geometry()).toEqual(original)
  })

  test(`${theme}: Customer table has normal background at rest and does not change cell metrics on hover`, async ({ page }) => {
    await mountFixture(page, theme, 1180)
    const actions = page.locator('#customer-actions')
    const before = await actions.evaluate((el) => {
      const rect = el.getBoundingClientRect()
      const style = getComputedStyle(el)
      return { width: rect.width, height: rect.height, border: style.borderBottomWidth, padding: style.padding, sizing: style.boxSizing }
    })
    await page.locator('#customer-row').hover()
    const after = await actions.evaluate((el) => {
      const rect = el.getBoundingClientRect()
      const style = getComputedStyle(el)
      return { width: rect.width, height: rect.height, border: style.borderBottomWidth, padding: style.padding, sizing: style.boxSizing }
    })
    expect(after).toEqual(before)
    expect(before.sizing).toBe('border-box')
    expect(before.border).toBe('0px') // Last row border is removed by the standard table contract.
  })
}

// HF11: table hover is a PAINT transition in ordinary lists, but a sticky
// cell is an opaque overlay. Rapidly alternate two rows at DPR 1.25 and
// assert every cell commits to the same color in the same animation frame.
for (const theme of ['light', 'dark'] as const) {
  for (const width of [375, 720]) {
    test(`${theme} ${width}px: customer sticky row paint is atomic through rapid hover`, async ({ browser, baseURL }) => {
      const context = await browser.newContext({ baseURL, viewport: { width, height: 700 }, deviceScaleFactor: 1.25 })
      try {
        const page = await context.newPage()
        await mountFixture(page, theme, width)
        await page.locator('#customers-table tbody').evaluate((body) => {
          const copy = body.querySelector('tr')!.cloneNode(true) as HTMLTableRowElement
          copy.querySelectorAll('[id]').forEach((el) => el.removeAttribute('id'))
          copy.id = 'customer-row-2'
          body.appendChild(copy)
        })
        const wrap = page.locator('#customers-scroll')
        await wrap.evaluate((el) => { el.scrollLeft = el.scrollWidth - el.clientWidth })
        const rows = [page.locator('#customer-row'), page.locator('#customer-row-2')]
        const expectedHover = theme === 'light' ? 'rgb(234, 242, 252)' : 'rgb(38, 57, 72)'
        const expectedSurface = theme === 'light' ? 'rgb(255, 255, 255)' : 'rgb(24, 37, 50)'
        // Inspect all cells, not merely the rightmost one: otherwise the
        // frozen Actions layer can repaint at a different time from the row.
        const read = async () => page.locator('#customers-table tbody').evaluate((body) =>
          [...body.querySelectorAll('tr')].map((row) =>
            [...row.cells].map((cell) => ({
              background: getComputedStyle(cell).backgroundColor,
              duration: getComputedStyle(cell).transitionDuration,
              bounds: [cell.getBoundingClientRect().x, cell.getBoundingClientRect().y,
                cell.getBoundingClientRect().width, cell.getBoundingClientRect().height],
            }))))
        // A warm-up hover must not start any translucent background animation.
        await rows[0]!.locator('td').last().hover()
        const before = await read()
        for (let step = 0; step < 8; step++) {
          const active = step % 2
          await rows[active]!.locator('td').last().hover()
          const current = await read()
          for (let i = 0; i < current.length; i++) {
            for (let j = 0; j < current[i]!.length; j++) {
              const cell = current[i]![j]!
              expect(cell.duration, `row ${i} col ${j} must have no paint transition`).toBe('0s')
              expect(cell.background, `row ${i} col ${j} paint`).toBe(i === active ? expectedHover :
                j === current[i]!.length - 1 ? expectedSurface : 'rgba(0, 0, 0, 0)')
              expect(cell.bounds, `row ${i} col ${j} geometry`).toEqual(before[i]![j]!.bounds)
            }
          }
        }
      } finally {
        await context.close()
      }
    })
  }
}
