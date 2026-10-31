// DS5 HF8 regression: viewport width alone is insufficient. Supplier may live
// inside a resizable workspace with a sidebar; measure the filter PANEL width.
// The fixture uses the actual production shared CSS and Supplier scoped CSS,
// with Vue's :deep() syntax lowered as the SFC compiler does at build time.
// No authentication, Supplier API calls, or PBAC claims are made here.
import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const read = (file: string): string => readFileSync(resolve(process.cwd(), file), 'utf8')
const styles = ['tokens.css', 'base.css', 'buttons.css', 'tables.css']
  .map((file) => read(`src/styles/${file}`))
const supplierCSS = read('src/features/supplier/pages/SuppliersView.vue')
  .match(/<style scoped>([\s\S]*?)<\/style>/)?.[1]
  ?.replace(/:deep\(([^()]*)\)/g, '$1') ?? ''

function controls(withCompany: boolean): string {
  return `<main id="workspace">
    <section class="supplier-list-panel" id="supplier-panel">
      <div class="supplier-list-toolbar">
        <form class="list-query-controls" role="search" id="supplier-query">
          <div class="list-query-controls__grid" style="--ds-query-columns: minmax(230px,2.6fr) repeat(3, minmax(140px,1fr))${withCompany ? ' minmax(205px,1.65fr)' : ''}">
          <label class="list-query-controls__field list-query-controls__search" id="search-field">
            <span>Tìm kiếm</span><input type="search" aria-label="Tìm kiếm" />
          </label>
          <label class="list-query-controls__field" id="status-field">
            <span>Trạng thái</span><select aria-label="Trạng thái"><option>Tất cả</option></select>
          </label>
          <label class="list-query-controls__field" id="type-field">
            <span>Loại nhà cung cấp</span><select aria-label="Loại nhà cung cấp"><option>Tất cả</option></select>
          </label>
          <label class="list-query-controls__field" id="group-field">
            <span>Nhóm</span><select aria-label="Nhóm"><option>Tất cả</option></select>
          </label>
          ${withCompany ? `<label class="list-query-controls__field list-query-controls__field--wide supplier-company-filter" id="company-field">
            <span>Lọc theo công ty</span>
            <div class="remote-searchable-select" role="combobox" aria-label="Lọc theo công ty">Tất cả công ty</div>
          </label>` : ''}
          </div>
          <div class="list-query-controls__actions" id="supplier-actions">
            <button type="submit" data-ds-icon="apply" class="ds-button ds-button--primary" id="apply">Áp dụng</button>
            <button type="button" data-ds-icon="reset" class="ds-button ds-button--secondary" id="reset">Đặt lại</button>
            <button type="button" data-ds-icon="refresh" class="secondary-button supplier-toolbar-refresh" id="supplier-refresh">Làm mới</button>
          </div>
        </form>
      </div>
    </section>
  </main>`
}

async function fixture(page: Page, theme: 'light' | 'dark', withCompany = true): Promise<void> {
  await page.setViewportSize({ width: 1950, height: 940 })
  await page.setContent(`<!doctype html><html data-ds-theme="${theme}"><body style="padding:16px">${controls(withCompany)}</body></html>`)
  for (const css of styles) await page.addStyleTag({ content: css })
  await page.addStyleTag({ content: supplierCSS })
  await page.addStyleTag({ content: `
    #workspace { width: 1600px; max-width: 100%; }
    #supplier-panel { border: 1px solid var(--ds-border-subtle); border-radius: 8px; }
    .remote-searchable-select { box-sizing:border-box; width:100%; min-height:36px;
      display:flex; align-items:center; padding:6px 12px; overflow:hidden;
      border:1px solid var(--ds-border); border-radius:6px;
      background:var(--ds-bg-surface); color:var(--ds-text-primary); }
  ` })
}

type Box = { x: number; y: number; width: number; height: number }
async function rect(page: Page, selector: string): Promise<Box> {
  const result = await page.locator(selector).boundingBox()
  expect(result, `Missing geometry for ${selector}`).not.toBeNull()
  return result!
}

async function inspect(page: Page, width: number, withCompany: boolean): Promise<void> {
  await page.locator('#workspace').evaluate((element, w) => { element.style.width = `${w}px` }, width)
  const panel = await rect(page, '#supplier-panel')
  const form = await rect(page, '#supplier-query')
  const fields = ['#search-field', '#status-field', '#type-field', '#group-field', ...(withCompany ? ['#company-field'] : [])]
  const boxes = await Promise.all(fields.map((selector) => rect(page, selector)))
  const actions = await rect(page, '#supplier-actions')
  const apply = await rect(page, '#apply')
  const reset = await rect(page, '#reset')
  const refresh = await rect(page, '#supplier-refresh')
  const epsilon = 2

  // All filters are always laid out before any action. HF7 failed because the
  // external Company filter could be placed BELOW Apply and Reset at mid-width.
  const filterBottom = Math.max(...boxes.map((b) => b.y + b.height))
  expect(actions.y, `Action row must follow ALL filters at ${width}px`).toBeGreaterThanOrEqual(filterBottom - epsilon)
  expect(reset.y, `Reset aligned with Apply at ${width}px`).toBeCloseTo(apply.y, 0)
  if (width >= 520) {
    expect(refresh.y, `Refresh aligned with Apply at ${width}px`).toBeCloseTo(apply.y, 0)
  } else {
    // On very small panels Refresh may wrap within the same actions section.
    expect(refresh.y).toBeGreaterThanOrEqual(apply.y - epsilon)
  }
  expect(form.width).toBeLessThanOrEqual(panel.width + epsilon)
  for (const [i, box] of boxes.entries()) {
    expect(box.x, `${fields[i]} starts within panel at ${width}`).toBeGreaterThanOrEqual(panel.x - epsilon)
    expect(box.x + box.width, `${fields[i]} fits panel at ${width}`).toBeLessThanOrEqual(panel.x + panel.width + epsilon)
  }
  expect(await page.locator('#supplier-query').evaluate((el) => el.scrollWidth <= el.clientWidth + 2),
    `Query form must not overflow at ${width}px`).toBe(true)

  if (withCompany && width >= 1120) {
    // Five fields share one row when there is enough *panel* width. This is the
    // screenshot case that previously wrapped despite ample room on screen.
    expect(Math.abs(boxes[0].y - boxes[4].y), `Company in same row at ${width}px`).toBeLessThanOrEqual(3)
    expect(boxes[4].x).toBeGreaterThan(boxes[3].x)
  }
}

for (const theme of ['light', 'dark'] as const) {
  test(`${theme}: all five filters share a row across desktop widths, then all three actions follow`, async ({ page }) => {
    await fixture(page, theme)
    for (const width of [1781, 1600, 1500, 1440, 1366, 1280, 1200, 1120]) {
      await inspect(page, width, true)
    }
  })

  test(`${theme}: Company wraps only before the action row at intermediate widths`, async ({ page }) => {
    await fixture(page, theme)
    for (const width of [1100, 1080, 1060, 1000, 920, 900]) {
      await inspect(page, width, true)
    }
  })

  test(`${theme}: narrow panel stacks controls without overflow or action misalignment`, async ({ page }) => {
    await fixture(page, theme)
    for (const width of [880, 860, 800, 720, 640, 560, 520, 480, 390, 340]) {
      await inspect(page, width, true)
    }
  })

  test(`${theme}: sidebar-constrained workspace responds to panel rather than viewport`, async ({ page }) => {
    await fixture(page, theme)
    // Viewport stays 1950px; the main panel is narrowed by layout/sidebar.
    for (const width of [1600, 1050, 800, 480]) {
      await inspect(page, width, true)
    }
  })

  test(`${theme}: actual mobile viewport rules coexist with panel container queries`, async ({ page }) => {
    await fixture(page, theme)
    for (const viewport of [720, 640, 430, 360]) {
      await page.setViewportSize({ width: viewport, height: 820 })
      await inspect(page, viewport - 32, true)
    }
  })

  test(`${theme}: no organization.view permission hides Company without an empty grid column`, async ({ page }) => {
    await fixture(page, theme, false)
    await expect(page.locator('#company-field')).toHaveCount(0)
    for (const width of [1600, 1024, 800, 480]) {
      await inspect(page, width, false)
    }
    // At desktop width the four base fields still consume the entire first row.
    await page.locator('#workspace').evaluate((el) => { el.style.width = '1600px' })
    const last = await rect(page, '#group-field')
    const form = await rect(page, '#supplier-query')
    expect(Math.abs(last.x + last.width - (form.x + form.width))).toBeLessThan(4)
  })
}

// The moved action slot must not change the shared form's submit contract.
test('Supplier Refresh and Reset remain type=button; Apply alone submits the query form', async ({ page }) => {
  await fixture(page, 'light')
  await page.locator('#supplier-query').evaluate((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault()
      form.setAttribute('data-submissions', String(Number(form.getAttribute('data-submissions') ?? '0') + 1))
    })
  })
  await page.locator('#supplier-refresh').click()
  await page.locator('#reset').click()
  await expect(page.locator('#supplier-query')).not.toHaveAttribute('data-submissions', /./)
  await page.locator('#apply').click()
  await expect(page.locator('#supplier-query')).toHaveAttribute('data-submissions', '1')
})
