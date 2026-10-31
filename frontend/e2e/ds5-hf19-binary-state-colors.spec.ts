import { test, expect } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

// DS5 HF21: the user's updated buttons.css is the visual specification.
// A state color is on the GLYPH + border; native action buttons retain their
// own surface background. The icon still describes the NEXT action.
const tokens = readFileSync(resolve(process.cwd(), 'src/styles/tokens.css'), 'utf8')
const buttons = readFileSync(resolve(process.cwd(), 'src/styles/buttons.css'), 'utf8')
const tables = readFileSync(resolve(process.cwd(), 'src/styles/tables.css'), 'utf8')

type ButtonStyle = {
  background: string
  color: string
  border: string
  width: number
  mask: string
}

for (const theme of ['light', 'dark'] as const) {
  test(`${theme}: custom outline state buttons follow CURRENT status, next-action icons and hover`, async ({ page }) => {
    await page.setContent(`<html><body>
      <button id="row-active" class="table-row-action table-row-action--state ds-icon-control ds-state-toggle"
        data-ds-state="active" data-ds-icon="deactivate" title="Deactivate" aria-label="Deactivate" type="button"></button>
      <button id="row-inactive" class="table-row-action table-row-action--state ds-icon-control ds-state-toggle"
        data-ds-state="inactive" data-ds-icon="activate" title="Activate" aria-label="Activate" type="button"></button>
      <button id="detail-active" class="secondary-button ds-state-toggle" data-ds-state="active"
        data-ds-icon="deactivate" type="button">Deactivate</button>
      <button id="detail-inactive" class="secondary-button ds-state-toggle" data-ds-state="inactive"
        data-ds-icon="activate" type="button">Activate</button>
      <button id="workflow" class="ds-icon-control" data-ds-icon="changestate"
        aria-label="Change States" type="button"></button>
    </body></html>`)
    await page.addStyleTag({ content: tokens })
    await page.addStyleTag({ content: buttons })
    await page.addStyleTag({ content: tables })
    await page.evaluate(next => document.documentElement.setAttribute('data-ds-theme', next), theme)

    const palette = await page.evaluate(() => {
      // Resolve custom properties through computed style rather than assuming
      // the token file is the same for Light and Dark modes.
      const resolveColor = (token: string, property: 'color' | 'backgroundColor' = 'color') => {
        const el = document.createElement('span')
        el.style[property] = `var(${token})`
        document.body.appendChild(el)
        const result = getComputedStyle(el)[property]
        el.remove()
        return result
      }
      return {
        green: resolveColor('--ds-success'),
        gray: resolveColor('--ds-border'),
        grayHoverBorder: resolveColor('--ds-border-strong'),
        surface: resolveColor('--ds-bg-surface', 'backgroundColor'),
        rowHover: resolveColor('--ds-primary-soft', 'backgroundColor'),
      }
    })

    const snapshot = async (id: string): Promise<ButtonStyle> => page.locator(`#${id}`).evaluate(el => {
      const style = getComputedStyle(el)
      const icon = getComputedStyle(el, '::before')
      return {
        background: style.backgroundColor,
        color: style.color,
        border: style.borderTopColor,
        width: el.getBoundingClientRect().width,
        mask: icon.maskImage || icon.webkitMaskImage,
      }
    })

    // Both icon-only row and text-button Detail show the same status color.
    // Detail has a 140ms transition; poll its final computed values.
    for (const [id, color] of [
      ['row-active', palette.green],
      ['detail-active', palette.green],
      ['row-inactive', palette.gray],
      ['detail-inactive', palette.gray],
    ] as const) {
      await expect.poll(async () => {
        const value = await snapshot(id)
        return { background: value.background, color: value.color, border: value.border }
      }).toEqual({ background: palette.surface, color, border: color })
    }

    const activeBefore = await snapshot('row-active')
    const inactiveBefore = await snapshot('row-inactive')
    const workflow = await snapshot('workflow')
    expect(activeBefore.mask).toContain('data:image/svg+xml')
    expect(inactiveBefore.mask).toContain('data:image/svg+xml')
    expect(new Set([activeBefore.mask, inactiveBefore.mask, workflow.mask]).size).toBe(3)
    expect(workflow.color).not.toBe(palette.green)

    const active = page.locator('#row-active')
    await page.keyboard.press('Tab')
    await expect(active).toBeFocused()
    expect(await active.evaluate(el => getComputedStyle(el).outlineStyle)).toBe('solid')

    await page.locator('#row-inactive').hover()
    await expect.poll(async () => {
      const value = await snapshot('row-inactive')
      return { color: value.color, border: value.border, background: value.background }
    }).toEqual({ color: palette.green, border: palette.grayHoverBorder, background: palette.rowHover })
    expect(Math.abs((await snapshot('row-inactive')).width - inactiveBefore.width)).toBeLessThan(0.5)

    // Simulate Vue props changing after a successful backend toggle.
    await page.locator('#workflow').hover()
    await active.evaluate(el => {
      el.setAttribute('data-ds-state', 'inactive')
      el.setAttribute('data-ds-icon', 'activate')
      el.setAttribute('aria-label', 'Activate')
    })
    await expect.poll(async () => {
      const value = await snapshot('row-active')
      return { background: value.background, color: value.color, border: value.border, mask: value.mask }
    }).toEqual({ background: palette.surface, color: palette.gray, border: palette.gray, mask: inactiveBefore.mask })
    expect(Math.abs((await snapshot('row-active')).width - activeBefore.width)).toBeLessThan(0.5)
    await expect(active).toHaveAttribute('aria-label', 'Activate')

    await active.evaluate(el => {
      el.setAttribute('data-ds-state', 'active')
      el.setAttribute('data-ds-icon', 'deactivate')
      el.setAttribute('aria-label', 'Deactivate')
    })
    await expect.poll(async () => {
      const value = await snapshot('row-active')
      return { color: value.color, border: value.border, mask: value.mask }
    }).toEqual({ color: palette.green, border: palette.green, mask: activeBefore.mask })
  })
}
