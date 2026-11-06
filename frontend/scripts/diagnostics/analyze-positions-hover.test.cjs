const { test } = require('node:test')
const assert = require('node:assert/strict')
const { analyze } = require('./analyze-positions-hover.cjs')
const base = () => ({ schema: 'dinkerp.positions.hover-live.v1', summary: { frames: 90 }, events: [], mutations: [], layoutShiftEvents: [] })
test('No geometry events classifies paint-only hover conservatively', () => {
  assert.deepEqual(analyze(base()).categories, ['NO_DOM_GEOMETRY_OR_SCROLL_SHIFT_OBSERVED'])
})
test('Changing inner scrollport size distinguishes scrollbar from table CSS', () => {
  const t = base()
  t.events.push({ at: 200, hover: 2, deltas: [{ kind: 'SCROLL_OR_SIZE', element: 'workspace', field: 'clientWidth', delta: 15 }] })
  assert.deepEqual(analyze(t).categories, ['SCROLLBAR_GUTTER_OR_SCROLLPORT_WIDTH_CHANGED'])
})
test('Changes in row geometry with fixed workspace point to table', () => {
  const t = base()
  t.events.push({ at: 220, hover: 1, deltas: [{ kind: 'ELEMENT_GEOMETRY', element: 'row1', field: 'height', delta: 2 }] })
  assert.deepEqual(analyze(t).categories, ['TABLE_INTERNAL_GEOMETRY_CHANGED'])
})
test('Zoom changes and sidebar resize are distinguished', () => {
  const t = base()
  t.events.push({ at: 230, hover: 1, deltas: [
    { kind: 'VIEWPORT_OR_SIDEBAR', field: 'visualScale', before: 1, after: 1.25 },
    { kind: 'VIEWPORT_OR_SIDEBAR', field: 'sidebarCssWidth', before: '288px', after: '296px' },
  ] })
  assert.deepEqual(analyze(t).categories, ['VIEWPORT_OR_ZOOM_CHANGED', 'SIDEBAR_WIDTH_CHANGED'])
})
