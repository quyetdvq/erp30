/**
 * CSS-A02 / People Positions: real Vue route + two realistic PBAC-scoped rows.
 * Detect geometry/column shifts during repeated hover, rather than relying on
 * screenshots or a synthetic table that does not mount ListTable/RowActions.
 * This is a reproduction gate, not evidence that a live-only defect is fixed.
 */
import { expect, test, type Page, type Route } from '@playwright/test'
import { mockPagination } from './support/mockPagination'
import { mockBusinessWorkflowStates } from './support/mockBusinessWorkflow'

const positions = [
  { id: '21', code: 'P-01', name: 'Trưởng phòng', level: 3, status: 'ACTIVE', allowed_actions: { edit: true, setstate: true } },
  { id: '22', code: 'P-02', name: 'Nhân viên', level: 1, status: 'INACTIVE', allowed_actions: { edit: true, setstate: true } },
]
const positionsFourRows = [
  { id: '31', code: 'GD', name: 'Giám Đốc', level: 1, status: 'ACTIVE', allowed_actions: { edit: true, setstate: true } },
  { id: '32', code: 'NV', name: 'Nhân viên', level: 1, status: 'ACTIVE', allowed_actions: { edit: true, setstate: true } },
  { id: '33', code: 'PP', name: 'Phó phòng', level: 1, status: 'ACTIVE', allowed_actions: { edit: true, setstate: true } },
  { id: '34', code: 'TP', name: 'Trưởng Phòng', level: 1, status: 'ACTIVE', allowed_actions: { edit: true, setstate: true } },
]

async function mockPositions(page: Page, fixtureRows = positions): Promise<void> {
  await mockBusinessWorkflowStates(page)
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ data: { user: {
      id: 7,
      username: 'positions-qa',
      groups: ['orgadmin'],
      base_permissions: ['position.view', 'position.create', 'position.edit', 'position.setstate'],
    } } }),
  }))
  await page.route('**/api/v1/positions**', (route: Route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({
      data: fixtureRows,
      meta: mockPagination(route, fixtureRows.length, 'positions-hover-e2e'),
    }),
  }))
}

type Box = { x: number; y: number; width: number; height: number }
type Snapshot = {
  boxes: Box[]
  columnWidths: number[]
  rowHeights: number[]
  tableScrollWidth: number
  wrapperClientWidth: number
  bodyScrollWidth: number
}

async function snapshot(page: Page): Promise<Snapshot> {
  return page.locator('.list-table-wrap').evaluate((wrap) => {
    const table = wrap.querySelector<HTMLTableElement>('table.list-table')!
    const rows = [...table.querySelectorAll<HTMLTableRowElement>('tbody tr')]
    const rect = (el: Element): Box => {
      const box = el.getBoundingClientRect()
      // Relative position eliminates legitimate viewport scrolling from the
      // measurement; actual row/cell width and height remain checked.
      const origin = wrap.getBoundingClientRect()
      return {
        x: box.x - origin.x,
        y: box.y - origin.y,
        width: box.width,
        height: box.height,
      }
    }
    return {
      boxes: [rect(wrap), rect(table), ...rows.flatMap((r) => [rect(r), ...[...r.cells].map(rect),
        ...[...r.querySelectorAll('button')].map(rect)])],
      columnWidths: [...table.querySelectorAll('thead th')].map((th) => th.getBoundingClientRect().width),
      rowHeights: rows.map((r) => r.getBoundingClientRect().height),
      tableScrollWidth: wrap.scrollWidth,
      wrapperClientWidth: wrap.clientWidth,
      bodyScrollWidth: document.documentElement.scrollWidth,
    }
  })
}

function expectSameGeometry(before: Snapshot, after: Snapshot): void {
  expect(after.tableScrollWidth).toBe(before.tableScrollWidth)
  expect(after.wrapperClientWidth).toBe(before.wrapperClientWidth)
  expect(after.bodyScrollWidth).toBe(before.bodyScrollWidth)
  expect(after.columnWidths).toEqual(before.columnWidths)
  expect(after.rowHeights).toEqual(before.rowHeights)
  expect(after.boxes.length).toBe(before.boxes.length)
  for (let i = 0; i < before.boxes.length; i++) {
    for (const prop of ['x', 'y', 'width', 'height'] as const) {
      expect(after.boxes[i]![prop], `box #${i} shifted ${prop}`)
        .toBeCloseTo(before.boxes[i]![prop], 1)
    }
  }
}

for (const theme of ['light', 'dark'] as const) {
  for (const width of [320, 375, 560, 768, 1440]) {
    test(`Positions ${theme} ${width}px: first two data rows never change geometry on hover`, async ({ page }) => {
      await page.setViewportSize({ width, height: 1200 })
      await mockPositions(page)
      await page.goto('/people/positions')
      const rows = page.locator('.list-table tbody tr')
      await expect(rows).toHaveCount(2)
      await expect(rows.first()).toContainText('Trưởng phòng')
      await expect(rows.nth(1)).toContainText('Nhân viên')
      // One owner for all action icons. Previously workflow used a private
      // 30px skin while View/Edit buttons were 32px on the same table row.
      for (const row of [rows.first(), rows.nth(1)]) {
        const icons = row.locator('button.ds-icon-control')
        await expect(icons).toHaveCount(3)
        for (const icon of await icons.all()) {
          await expect(icon).toHaveCSS('width', '32px')
          await expect(icon).toHaveCSS('height', '32px')
        }
      }
      await page.evaluate((value) => {
        document.documentElement.dataset.dsTheme = value
      }, theme)
      const first = rows.first().locator('td').first()
      const second = rows.nth(1).locator('td').first()
      const expectedColor = await page.evaluate(() => {
        const el = document.createElement('div')
        el.style.backgroundColor = 'var(--ds-table-row-hover)'
        document.body.append(el)
        const color = getComputedStyle(el).backgroundColor
        el.remove()
        return color
      })
      await first.hover()
      await expect(first).toHaveCSS('background-color', expectedColor)
      const before = await snapshot(page)
      for (let i = 0; i < 4; i++) {
        await second.hover()
        await expect(second).toHaveCSS('background-color', expectedColor)
        expectSameGeometry(before, await snapshot(page))
        await first.hover()
        await expect(first).toHaveCSS('background-color', expectedColor)
        expectSameGeometry(before, await snapshot(page))
      }
    })
  }
}

/**
 * HF05: The reported 771px screenshots contain FOUR rows, not the earlier
 * 1200px/two-row fixture. Compare absolute shell widths/positions as well as
 * relative table geometry. The real Naive workspace scroll owner (not body)
 * must not resize when its vertical scrollbar becomes necessary. Do not add
 * a page-level scrollbar or remove the sticky/action behavior to pass this.
 */
type AbsoluteFrame = {
  gutter: string
  ownerClientWidth: number
  ownerRectWidth: number
  widths: Record<string, { x: number; width: number }>
}
async function absoluteFrame(page: Page): Promise<AbsoluteFrame> {
  return page.evaluate(() => {
    const owner = document.querySelector<HTMLElement>('.workspace-layout .n-layout-scroll-container')!
    const selectors = ['.workspace-content', '.app-breadcrumbs', '.page-heading', '.content-panel', '.list-table-wrap']
    return {
      gutter: getComputedStyle(owner).scrollbarGutter,
      ownerClientWidth: owner.clientWidth,
      ownerRectWidth: owner.getBoundingClientRect().width,
      widths: Object.fromEntries(selectors.map((selector) => {
        const el = document.querySelector(selector)!
        const rect = el.getBoundingClientRect()
        return [selector, { x: rect.x, width: rect.width }]
      })),
    }
  })
}
function expectStableFrame(before: AbsoluteFrame, after: AbsoluteFrame): void {
  expect(after.gutter).toBe('stable')
  expect(after.ownerClientWidth).toBe(before.ownerClientWidth)
  expect(after.ownerRectWidth).toBeCloseTo(before.ownerRectWidth, 1)
  for (const selector of Object.keys(before.widths)) {
    expect(after.widths[selector]?.x, `${selector} x`).toBeCloseTo(before.widths[selector]!.x, 1)
    expect(after.widths[selector]?.width, `${selector} width`).toBeCloseTo(before.widths[selector]!.width, 1)
  }
}
for (const theme of ['light', 'dark'] as const) {
  for (const width of [375, 768, 1556]) {
    test(`Positions HF05 ${theme} ${width}x771: shell scrollbar and 4 rows stay stable on hover`, async ({ page }) => {
      await page.setViewportSize({ width, height: 771 })
      await mockPositions(page, positionsFourRows)
      await page.goto('/people/positions')
      const rows = page.locator('.list-table tbody tr')
      await expect(rows).toHaveCount(4)
      await expect(rows.first()).toContainText('Giám Đốc')
      await expect(rows.nth(1)).toContainText('Nhân viên')
      await page.evaluate((value) => { document.documentElement.dataset.dsTheme = value }, theme)
      const owner = page.locator('.workspace-layout .n-layout-scroll-container').first()
      await expect(owner).toHaveCSS('scrollbar-gutter', 'stable')

      // A temporary page-height fixture forces an actual vertical scrollbar.
      // Toggling overflow in the test mimics a scroll owner's empty/full state;
      // it must never change the width of the view as a classic scrollbar does.
      await page.evaluate(() => {
        const spacer = document.createElement('div')
        spacer.id = 'positions-scroll-gutter-fixture'
        spacer.style.cssText = 'height:1500px;pointer-events:none'
        document.querySelector('.workspace-content')!.appendChild(spacer)
        const scrollOwner = document.querySelector<HTMLElement>('.workspace-layout .n-layout-scroll-container')!
        scrollOwner.style.overflowY = 'hidden'
      })
      const before = await absoluteFrame(page)
      await page.evaluate(() => {
        const scrollOwner = document.querySelector<HTMLElement>('.workspace-layout .n-layout-scroll-container')!
        scrollOwner.style.overflowY = 'auto'
      })
      expectStableFrame(before, await absoluteFrame(page))
      const first = rows.first().locator('td').first()
      const second = rows.nth(1).locator('td').first()
      await first.hover()
      const hoveredFrame = await absoluteFrame(page)
      for (let i = 0; i < 5; i++) {
        await second.hover()
        expectStableFrame(hoveredFrame, await absoluteFrame(page))
        await first.hover()
        expectStableFrame(hoveredFrame, await absoluteFrame(page))
      }
    })
  }
}

// The screenshots have approximately 125%-scaled controls (32 CSS px icons
// render around 40 image pixels). Reproduce 1556x771 physical pixels using
// 1245x617 CSS px at DPR 1.25, rather than assuming 100% desktop scaling.
for (const theme of ['light', 'dark'] as const) {
  test(`Positions HF05 ${theme} at DPR 1.25: four rows and shell width are stable`, async ({ browser, baseURL }) => {
    const context = await browser.newContext({
      baseURL,
      viewport: { width: 1245, height: 617 },
      deviceScaleFactor: 1.25,
    })
    try {
      const page = await context.newPage()
      await mockPositions(page, positionsFourRows)
      await page.goto('/people/positions')
      const rows = page.locator('.list-table tbody tr')
      await expect(rows).toHaveCount(4)
      await page.evaluate((value) => { document.documentElement.dataset.dsTheme = value }, theme)
      await expect(page.locator('.workspace-layout .n-layout-scroll-container').first())
        .toHaveCSS('scrollbar-gutter', 'stable')
      expect(await page.evaluate(() => window.devicePixelRatio)).toBe(1.25)
      await rows.first().locator('td').first().hover()
      const before = await absoluteFrame(page)
      for (let n = 0; n < 5; n++) {
        await rows.nth(1).locator('td').first().hover()
        expectStableFrame(before, await absoluteFrame(page))
        await rows.first().locator('td').first().hover()
        expectStableFrame(before, await absoluteFrame(page))
      }
    } finally {
      await context.close()
    }
  })
}

/**
 * HF07: Reproduce the live DEV trace that the old hover-only tests missed.
 * A previously clicked action in NV kept :focus-within, tinting NV even while
 * the pointer hovered GD. Only a keyboard-visible focus may retain row paint.
 * Reduced-motion alone must never be the cause or the workaround.
 */
for (const theme of ['light', 'dark'] as const) {
  for (const reducedMotion of ['reduce', 'no-preference'] as const) {
    test(`Positions HF07 ${theme}/${reducedMotion}: pointer and keyboard focus do not mix row highlights`, async ({ page }) => {
      await page.setViewportSize({ width: 1245, height: 617 })
      await page.emulateMedia({ reducedMotion })
      await mockPositions(page, positionsFourRows)
      await page.goto('/people/positions')
      await page.evaluate((mode) => { document.documentElement.dataset.dsTheme = mode }, theme)
      const rows = page.locator('.list-table tbody tr')
      await expect(rows).toHaveCount(4)
      const gd = rows.nth(0).locator('td').first()
      const nv = rows.nth(1).locator('td').first()
      const nvAction = rows.nth(1).locator('button.ds-icon-control').first()
      const hoverColor = await page.evaluate(() => {
        const el = document.createElement('div')
        el.style.backgroundColor = 'var(--ds-table-row-hover)'
        document.body.appendChild(el)
        const value = getComputedStyle(el).backgroundColor
        el.remove()
        return value
      })

      // Use a real POINTER click to establish focus modality. Programmatic
      // locator.focus() after keyboard events may yield :focus-visible=true,
      // which does not represent a mouse click and made HF07 tests fail.
      // Block only this single action's click handler in capture phase, so the
      // browser still focuses the button but no modal/API mutation is invoked.
      await nvAction.evaluate((el) => {
        el.addEventListener('click', (event) => {
          event.preventDefault()
          event.stopImmediatePropagation()
        }, { capture: true, once: true })
      })
      await nvAction.click()
      await expect(nvAction).toBeFocused()
      expect(await nvAction.evaluate((el) => el.matches(':focus-visible'))).toBe(false)
      const before = await snapshot(page)
      for (let step = 0; step < 3; step++) {
        await gd.hover()
        await expect(gd).toHaveCSS('background-color', hoverColor)
        await expect(nv).not.toHaveCSS('background-color', hoverColor)
        expectSameGeometry(before, await snapshot(page))
        await nv.hover()
        await expect(nv).toHaveCSS('background-color', hoverColor)
        await expect(gd).not.toHaveCSS('background-color', hoverColor)
        expectSameGeometry(before, await snapshot(page))
      }

      // True keyboard focus is intentionally distinct: the focused action
      // retains its outline and the NV row highlights for accessibility.
      await page.keyboard.press('Shift+Tab')
      await page.keyboard.press('Tab')
      await expect(nvAction).toBeFocused()
      expect(await nvAction.evaluate((el) => el.matches(':focus-visible'))).toBe(true)
      await page.mouse.move(1, 1)
      await expect(nv).toHaveCSS('background-color', hoverColor)
      expectSameGeometry(before, await snapshot(page))
    })
  }
}
