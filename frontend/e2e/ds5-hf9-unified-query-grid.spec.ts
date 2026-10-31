// DS5 HF9: measure the actual shared CSS at intermediate panel widths.
// Fixtures deliberately avoid backend data or PBAC bypass. The production
// ListQueryControls component owns the same markup/class contract.
import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const sharedCSS = ['tokens.css', 'base.css', 'buttons.css', 'tables.css']
  .map((name) => readFileSync(resolve(process.cwd(), 'src/styles', name), 'utf8'))

type Variant = { name: string; filters: number; company?: boolean }
const variants: Variant[] = [
  { name: 'tree/organization: search only', filters: 0 },
  { name: 'customer/catalog: search and status', filters: 1 },
  { name: 'supplier: three filters and PBAC company', filters: 3, company: true },
  { name: 'security/import: six filters (dense)', filters: 6 },
]
function markup({ filters, company }: Variant): string {
  const regular = Array.from({ length: filters }, (_, i) => `
    <label id="filter-${i}" class="list-query-controls__field">
      <span>Filter ${i + 1}</span><select><option>All</option></select>
    </label>`).join('')
  const search = '<label class="list-query-controls__field list-query-controls__search" id="search"><span>Search</span><input type="search" /></label>'
  const companyLabel = company ? '<label class="list-query-controls__field list-query-controls__field--wide" id="company"><span>Company</span><div class="mock-remote">All companies</div></label>' : ''
  const tracks = ['minmax(230px, 2.6fr)', ...Array.from({ length: filters }, () => 'minmax(140px, 1fr)'), ...(company ? ['minmax(205px, 1.65fr)'] : [])].join(' ')
  return `<section id="panel" style="width:1500px; padding:20px; border:1px solid var(--ds-border-subtle)">
    <form class="list-query-controls${filters + Number(Boolean(company)) > 5 ? ' list-query-controls--dense' : ''}" id="query" role="search">
      <div class="list-query-controls__grid" style="--ds-query-columns: ${tracks}">${search}${regular}${companyLabel}</div>
      <div class="list-query-controls__actions" id="actions">
        <button class="ds-button ds-button--primary" data-ds-icon="apply" id="apply" type="submit">Apply</button>
        <button class="ds-button ds-button--secondary" data-ds-icon="reset" id="reset" type="button">Reset</button>
        <button class="ds-button ds-button--secondary" data-ds-icon="refresh" id="refresh" type="button">Refresh</button>
      </div>
    </form>
  </section>`
}

type Rect = { x: number; y: number; width: number; height: number }
async function rect(page: Page, selector: string): Promise<Rect> {
  const box = await page.locator(selector).boundingBox()
  expect(box, selector).not.toBeNull()
  return box!
}
async function fixture(page: Page, theme: 'light' | 'dark', variant: Variant): Promise<void> {
  await page.setViewportSize({ width: 1920, height: 1000 })
  await page.setContent(`<html data-ds-theme="${theme}"><body style="padding:12px">${markup(variant)}</body></html>`)
  for (const css of sharedCSS) await page.addStyleTag({ content: css })
  await page.addStyleTag({ content: '.mock-remote { width:100%;min-height:36px;border:1px solid var(--ds-border);background:var(--ds-bg-surface);padding:7px 10px;border-radius:6px; }' })
}
async function assertLayout(page: Page, width: number, variant: Variant): Promise<void> {
  await page.locator('#panel').evaluate((node, w) => { node.style.width = `${w}px` }, width)
  const form = await rect(page, '#query')
  const panel = await rect(page, '#panel')
  const selectors = ['#search', ...Array.from({ length: variant.filters }, (_, i) => `#filter-${i}`), ...(variant.company ? ['#company'] : [])]
  const fields = await Promise.all(selectors.map((selector) => rect(page, selector)))
  const actions = await rect(page, '#actions')
  const apply = await rect(page, '#apply')
  const reset = await rect(page, '#reset')
  const refresh = await rect(page, '#refresh')
  const fieldBottom = Math.max(...fields.map((field) => field.y + field.height))
  expect(actions.y, `Actions always follow every field at ${width}`).toBeGreaterThanOrEqual(fieldBottom - 2)
  expect(Math.abs(reset.y - apply.y), `Reset aligned with Apply at ${width}`).toBeLessThanOrEqual(2)
  expect(refresh.y, `Refresh stays in actions row, never with filters at ${width}`).toBeGreaterThanOrEqual(actions.y - 2)
  expect(form.width, 'Form stays inside panel').toBeLessThanOrEqual(panel.width + 2)
  for (const [i, field] of fields.entries()) {
    expect(field.x, `${selectors[i]} left edge`).toBeGreaterThanOrEqual(form.x - 2)
    expect(field.x + field.width, `${selectors[i]} right edge`).toBeLessThanOrEqual(form.x + form.width + 2)
  }
  const overflow = await page.locator('#query').evaluate((node) => node.scrollWidth - node.clientWidth)
  expect(overflow, `No horizontal overflow at ${width}, ${variant.name}`).toBeLessThanOrEqual(2)
  if (variant.company && width >= 1120) {
    expect(Math.abs(fields[0].y - fields[fields.length - 1].y), 'Company aligns with Search on desktop').toBeLessThanOrEqual(3)
  }
  if (width >= 560) expect(Math.abs(refresh.y - apply.y), 'Refresh shares the action row').toBeLessThanOrEqual(3)
  if (width <= 480) {
    for (const [i, field] of fields.entries()) {
      expect(Math.abs(field.width - form.width), `${selectors[i]} fills mobile panel`).toBeLessThanOrEqual(3)
    }
  } else if (width <= 860) {
    expect(Math.abs(fields[0].width - form.width), 'Search fills narrow panel').toBeLessThanOrEqual(3)
  }
}

for (const theme of ['light', 'dark'] as const) {
  for (const variant of variants) {
    test(`${theme}: ${variant.name} remains field-first at all responsive widths`, async ({ page }) => {
      await fixture(page, theme, variant)
      for (const width of [1600, 1366, 1280, 1200, 1120, 1100, 1060, 900, 800, 640, 520, 480, 360]) {
        await assertLayout(page, width, variant)
      }
    })
  }
  test(`${theme}: bespoke query grid puts buttons after labels at desktop and mobile`, async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 850 })
    await page.setContent(`<html data-ds-theme="${theme}"><body><main style="padding:16px">
      <div class="ds-filter-container" id="container" style="width: 1400px">
        <form id="custom-query" class="ds-filter-form" role="search">
          <div class="ds-filter-fields" id="custom-fields">
            ${Array.from({ length: 6 }, (_, i) => `<label id="custom-${i}"><span>Field ${i}</span><input /></label>`).join('')}
          </div>
          <div class="ds-filter-actions" id="custom-actions"><button type="submit">Apply</button><button type="button" id="custom-reset">Reset</button></div>
        </form>
      </div></main></body></html>`)
    for (const css of sharedCSS) await page.addStyleTag({ content: css })
    for (const width of [1400, 1000, 800, 620, 480, 320]) {
      await page.locator('#container').evaluate((node, w) => { node.style.width = `${w}px` }, width)
      const last = await rect(page, '#custom-5')
      const actions = await rect(page, '#custom-actions')
      expect(actions.y, `Bespoke action row at ${width}`).toBeGreaterThanOrEqual(last.y + last.height - 2)
      const overflow = await page.locator('#custom-query').evaluate((el) => el.scrollWidth - el.clientWidth)
      expect(overflow).toBeLessThanOrEqual(2)
    }
    await page.locator('#custom-query').evaluate((form) => {
      form.addEventListener('submit', (event) => { event.preventDefault(); form.setAttribute('data-submitted', 'yes') })
    })
    await page.locator('#custom-reset').click()
    await expect(page.locator('#custom-query')).not.toHaveAttribute('data-submitted', 'yes')
    await page.locator('#custom-query button[type=submit]').click()
    await expect(page.locator('#custom-query')).toHaveAttribute('data-submitted', 'yes')
  })
}
