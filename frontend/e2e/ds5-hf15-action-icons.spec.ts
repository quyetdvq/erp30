import {test,expect} from '@playwright/test'
import {readFileSync} from 'node:fs'
import {resolve} from 'node:path'

const tokens=readFileSync(resolve(process.cwd(),'src/styles/tokens.css'),'utf8')
const buttons=readFileSync(resolve(process.cwd(),'src/styles/buttons.css'),'utf8')
const labels=['save','close','edit','changestate','activate','deactivate','refresh','add','apply','cancel','delete','view']

for(const theme of ['light','dark']){
 test(`${theme}: all action icons share one mask, stable dimensions and keyboard focus`,async({page})=>{
  await page.setContent(`<html><body><section id="action-fixture">
   ${labels.map(action=>`<button class="${action==='close'?'modal-card-button-close':action==='edit'||action==='delete'||action==='changestate'||action==='activate'||action==='deactivate'?'ds-icon-control':'primary-button'}" data-ds-icon="${action}" aria-label="${action}" type="button">${['close','edit','delete','changestate','activate','deactivate'].includes(action)?'':action}</button>`).join('')}
   <button class="n-button" data-ds-icon="apply"><span class="n-button__content">Apply</span></button>
  </section></body></html>`)
  await page.addStyleTag({content:tokens})
  await page.addStyleTag({content:buttons})
  await page.evaluate(t=>document.documentElement.setAttribute('data-ds-theme',t),theme)
  for(const key of labels){
   const button=page.locator(`button[data-ds-icon="${key}"]`).first()
   const before=await button.evaluate(el=>{
    const css=getComputedStyle(el,'::before')
    return {content:css.content,mask:css.maskImage||css.webkitMaskImage,opacity:css.opacity, width:css.width}
   })
   expect(before.content).not.toBe('none')
   expect(before.mask).toContain('data:image/svg+xml')
   expect(before.width).toBe(key==='close'?'15px':'18px')
   expect(await button.locator('svg,.n-icon').count()).toBe(0)
   const width=await button.evaluate(el=>el.getBoundingClientRect().width)
   await button.hover()
   expect(Math.abs((await button.evaluate(el=>el.getBoundingClientRect().width))-width)).toBeLessThan(.5)
  }
  const stateMasks = await page.evaluate(() => Object.fromEntries(['activate', 'deactivate', 'changestate'].map(key => {
   const button = document.querySelector(`button[data-ds-icon="${key}"]`)
   if (!button) throw new Error(`Missing ${key} fixture button`)
   const style = getComputedStyle(button, '::before')
   return [key, style.maskImage || style.webkitMaskImage]
  })))
  expect(new Set(Object.values(stateMasks)).size).toBe(3)
  const naive=page.locator('button.n-button')
  const mask=await naive.locator('.n-button__content').evaluate(el=>{
   const style=getComputedStyle(el,'::before');return style.maskImage||style.webkitMaskImage
  })
  expect(mask).toContain('data:image/svg+xml')
  await page.locator('button[data-ds-icon="save"]').focus()
  await expect(page.locator('button[data-ds-icon="save"]')).toBeFocused()
 })
}

// Unlike an exact-path assertion, loading the data URLs catches malformed
// custom SVG artwork. This covers all icons in the uploaded buttons.css,
// including percent-encoded and readable inline SVG variants.
const customMaskUris = [...buttons.matchAll(/^\[data-ds-icon="([a-z-]+)"\]\s*\{\s*--ds-action-mask:\s*url\("([^"]+)"\);\s*\}/gm)]
  .map(([, name, uri]) => ({ name, uri }))

test('all user-authored SVG action masks are valid images in Chromium', async ({ page }) => {
  expect(customMaskUris.length).toBeGreaterThanOrEqual(31)
  await page.setContent('<html><body></body></html>')
  const results = await page.evaluate(async entries => {
    return Promise.all(entries.map(async ({ name, uri }) => {
      const xml = decodeURIComponent(uri.slice('data:image/svg+xml,'.length))
      const doc = new DOMParser().parseFromString(xml, 'image/svg+xml')
      if (doc.getElementsByTagName('parsererror').length || doc.documentElement.localName !== 'svg') {
        throw new Error(`${name}: custom SVG XML failed to parse`)
      }
      const image = new Image()
      image.src = uri
      try {
        await image.decode()
      } catch {
        throw new Error(`${name}: SVG image failed to decode in Chromium`)
      }
      return { name, width: image.naturalWidth, height: image.naturalHeight }
    }))
  }, customMaskUris)
  for (const { name, width, height } of results) {
    expect(width, `${name}: missing SVG intrinsic width`).toBeGreaterThan(0)
    expect(height, `${name}: missing SVG intrinsic height`).toBeGreaterThan(0)
  }
})
