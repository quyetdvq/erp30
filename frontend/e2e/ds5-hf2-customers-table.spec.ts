// CSS geometry regression for the Customer table. This is a DOM fixture using
// production Design System CSS, not an authenticated customer API test.
import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const sharedCSS = ['tokens.css', 'base.css', 'buttons.css', 'tables.css']
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
    const visual = await actions.evaluate((el) => {
      const root = getComputedStyle(document.documentElement)
      const sample = document.createElement('div')
      sample.style.backgroundColor = root.getPropertyValue('--ds-bg-hover').trim()
      document.body.appendChild(sample)
      const expected = getComputedStyle(sample).backgroundColor
      sample.remove()
      return { actual: getComputedStyle(el).backgroundColor, expected }
    })
    expect(visual.actual).toBe(visual.expected)
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
