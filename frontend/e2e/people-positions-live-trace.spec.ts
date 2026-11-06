/**
 * Diagnostic-only HF06: verify opt-in loader and sensitivity to a controlled
 * layout shift; no production CSS geometry or API code is modified.
 */
import { expect, test, type Page, type Route } from '@playwright/test'
import { mockPagination } from './support/mockPagination'
import { mockBusinessWorkflowStates } from './support/mockBusinessWorkflow'

async function mockPositions(page: Page): Promise<void> {
  await mockBusinessWorkflowStates(page)
  await page.route('**/api/v1/auth/me', route => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ data: { user: {
      id: 7, username: 'positions-qa', groups: ['orgadmin'],
      base_permissions: ['position.view', 'position.create', 'position.edit', 'position.setstate'],
    } } }),
  }))
  await page.route('**/api/v1/positions**', (route: Route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({
      data: [
        { id: '31', code: 'GD', name: 'Giám Đốc', level: 1, status: 'ACTIVE', allowed_actions: { edit: true, setstate: true } },
        { id: '32', code: 'NV', name: 'Nhân viên', level: 1, status: 'ACTIVE', allowed_actions: { edit: true, setstate: true } },
        { id: '33', code: 'PP', name: 'Phó phòng', level: 1, status: 'ACTIVE', allowed_actions: { edit: true, setstate: true } },
        { id: '34', code: 'TP', name: 'Trưởng Phòng', level: 1, status: 'ACTIVE', allowed_actions: { edit: true, setstate: true } },
      ],
      meta: mockPagination(route, 4, 'positions-live-trace'),
    }),
  }))
}

test('HF06 opt-in trace detects 7px synthetic layout movement and downloads report', async ({ page }) => {
  await page.setViewportSize({ width: 1245, height: 617 })
  await mockPositions(page)
  await page.goto('/people/positions?positions_trace=1')
  const rows = page.locator('.list-table tbody tr')
  await expect(rows).toHaveCount(4)
  const controls = page.locator('#dink-positions-trace-controls')
  await expect(controls).toBeVisible()
  const row1 = rows.first().locator('td').first()
  const row2 = rows.nth(1).locator('td').first()
  for (let i = 0; i < 3; i++) {
    await row1.hover()
    await row2.hover()
  }
  await page.waitForTimeout(700)
  const ordinary = await page.evaluate(() => (window as unknown as {
    __dinkPositionsHoverTrace: { report(): { summary: { frames: number, eventBatches: number } } }
  }).__dinkPositionsHoverTrace.report().summary)
  expect(ordinary.frames).toBeGreaterThan(4)
  // Real application may update async shell information during the baseline.
  // Verify the controlled shift creates a *new* geometry event instead.

  // This mutation is TEST ONLY: prove the instrumentation catches real movement.
  await page.evaluate(() => {
    document.querySelector<HTMLElement>('.workspace-content')!.style.transform = 'translateX(7px)'
  })
  await row1.hover()
  await page.waitForTimeout(150)
  const hasShift = await page.evaluate(() => {
    const probe = (window as unknown as {
      __dinkPositionsHoverTrace: { report(): { events: Array<{ deltas: Array<{ kind: string, delta?: number }> }> } }
    }).__dinkPositionsHoverTrace
    return probe.report().events.some(e => e.deltas.some(d => d.kind === 'ELEMENT_GEOMETRY' && Math.abs(d.delta ?? 0) >= 7))
  })
  expect(hasShift).toBe(true)

  const downloadPromise = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Download Positions hover geometry diagnostics' }).click()
  const download = await downloadPromise
  expect(download.suggestedFilename()).toMatch(/^DinkERP_Positions_Hover_Trace_.*\.json$/)
})

test('HF06 probe is absent during normal Positions navigation', async ({ page }) => {
  await mockPositions(page)
  await page.goto('/people/positions')
  await expect(page.locator('.list-table tbody tr')).toHaveCount(4)
  await expect(page.locator('#dink-positions-trace-controls')).toHaveCount(0)
  expect(await page.evaluate(() => typeof (window as unknown as { __dinkPositionsHoverTrace?: unknown }).__dinkPositionsHoverTrace)).toBe('undefined')
})

/** HF08: Existing DEV traces showed one 46.4px workspace scroll, but the
 * original probe recorded no wheel/keyboard/focus or JavaScript call evidence.
 * Verify attribution with two deliberately DIFFERENT scroll causes on the
 * actual Vue route. This test does not assert that native wheel scroll is a
 * defect; it proves the next DEV report can identify the triggering input. */
test('HF08 live trace distinguishes wheel scrolling from script scroll calls', async ({ page }) => {
  await page.setViewportSize({ width: 1245, height: 617 })
  await mockPositions(page)
  await page.goto('/people/positions?positions_trace=1')
  await expect(page.locator('.list-table tbody tr')).toHaveCount(4)
  await expect(page.locator('#dink-positions-trace-controls')).toBeVisible()

  type ScrollAttribution = { classification: string, delta: number }
  type TraceReport = { scrollAttributions: ScrollAttribution[], inputEvents: Array<{ type: string }> }
  const owner = page.locator('.workspace-layout .n-layout-scroll-container').first()
  const available = await owner.evaluate((el) => el.scrollHeight - el.clientHeight)
  expect(available).toBeGreaterThan(5)

  // The pointer remains in the workspace while a real wheel input scrolls it.
  await owner.evaluate((el) => { el.scrollTop = 0 })
  await page.mouse.move(400, 190)
  await page.mouse.wheel(0, 350)
  await expect.poll(async () => page.evaluate(() => {
    const p = (window as unknown as {
      __dinkPositionsHoverTrace: { report(): TraceReport }
    }).__dinkPositionsHoverTrace
    return p.report().scrollAttributions.some(x => x.classification === 'RECENT_WHEEL' && x.delta > 0)
  })).toBe(true)

  // Explicit script call must be independently identifiable in the export.
  await owner.evaluate((el) => el.scrollTo({ top: 0, behavior: 'instant' }))
  await expect.poll(async () => page.evaluate(() => {
    const p = (window as unknown as {
      __dinkPositionsHoverTrace: { report(): TraceReport }
    }).__dinkPositionsHoverTrace
    return p.report().scrollAttributions.some(x => x.classification === 'RECENT_SCRIPT_CALL' && x.delta < 0)
  })).toBe(true)

  const report = await page.evaluate(() => (window as unknown as {
    __dinkPositionsHoverTrace: { report(): TraceReport }
  }).__dinkPositionsHoverTrace.report())
  expect(report.inputEvents.some(x => x.type === 'wheel')).toBe(true)
})
