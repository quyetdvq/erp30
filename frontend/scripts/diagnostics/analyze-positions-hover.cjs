#!/usr/bin/env node
/**
 * Offline local analyzer for the opt-in Positions geometry trace.
 * Run: node scripts/diagnostics/analyze-positions-hover.cjs path/to/trace.json
 * Does not use the network or modify any input file.
 */
const fs = require('node:fs')

function analyze(trace) {
  if (trace?.schema !== 'dinkerp.positions.hover-live.v1') throw Error('Unexpected trace schema')
  const counts = trace.summary || {}
  const events = trace.events || []
  const deltas = events.flatMap(e => (e.deltas || []).map(d => ({ ...d, at: e.at, hover: e.hover })))
  const has = (kind, name, field) => deltas.some(d => d.kind === kind && (!name || d.element === name) && (!field || d.field === field))
  const viewportChanges = deltas.filter(d => d.kind === 'VIEWPORT_OR_SIDEBAR')
  const scrollChanges = deltas.filter(d => d.kind === 'SCROLL_OR_SIZE')
  const tableChanges = deltas.filter(d => d.kind === 'ELEMENT_GEOMETRY' && /^(table|tableWrap|row[1-4])$/.test(d.element))
  const workspaceChanges = deltas.filter(d => d.kind === 'ELEMENT_GEOMETRY' && ['workspace', 'content', 'panel', 'heading', 'breadcrumb'].includes(d.element))
  const categories = []
  if (!counts.frames || counts.frames < 10) {
    categories.push('INSUFFICIENT_SAMPLES')
  } else if (!deltas.length) {
    categories.push('NO_DOM_GEOMETRY_OR_SCROLL_SHIFT_OBSERVED')
  } else {
    if (viewportChanges.some(d => ['innerWidth', 'clientWidth', 'visualWidth', 'visualScale', 'dpr'].includes(d.field))) categories.push('VIEWPORT_OR_ZOOM_CHANGED')
    if (viewportChanges.some(d => d.field === 'sidebarCssWidth') || has('ELEMENT_GEOMETRY', 'sidebar', 'width')) categories.push('SIDEBAR_WIDTH_CHANGED')
    if (has('SCROLL_OR_SIZE', 'workspace', 'clientWidth') || has('SCROLL_OR_SIZE', 'document', 'clientWidth')) categories.push('SCROLLBAR_GUTTER_OR_SCROLLPORT_WIDTH_CHANGED')
    if (scrollChanges.some(d => d.field === 'left' || d.field === 'top') || viewportChanges.some(d => ['scrollX', 'scrollY'].includes(d.field))) categories.push('SCROLL_POSITION_CHANGED')
    if (workspaceChanges.length && !categories.length) categories.push('WORKSPACE_OR_ANCESTOR_GEOMETRY_CHANGED')
    if (tableChanges.length && !workspaceChanges.length) categories.push('TABLE_INTERNAL_GEOMETRY_CHANGED')
    if (!categories.length) categories.push('OTHER_GEOMETRY_OR_SCROLL_CHANGE')
  }
  return {
    status: trace.status,
    capturedAt: trace.exportedAt,
    frames: counts.frames || 0,
    eventBatches: events.length,
    mutations: trace.mutations?.length || 0,
    browserLayoutShiftEvents: trace.layoutShiftEvents?.length || 0,
    scrollAttributions: (trace.scrollAttributions || []).map(item => ({
      at: item.at, from: item.from, to: item.to, delta: item.delta,
      classification: item.classification,
      recentInputTypes: (item.possibleInputs || []).map(input => input.type),
      recentScriptMethods: (item.possibleScriptCalls || []).map(call => call.method),
    })),
    categories,
    firstEvents: events.slice(0, 8).map(e => ({ at: e.at, hover: e.hover, deltas: e.deltas })),
    note: categories.includes('NO_DOM_GEOMETRY_OR_SCROLL_SHIFT_OBSERVED')
      ? 'This recording only supports paint-only hover or an issue outside DOM geometry; it cannot rule out unrecorded changes outside the hover intervals.'
      : 'Use the full events/computedEvidence entries to identify the exact CSS property or runtime state changing at the recorded timestamp.',
  }
}
if (require.main === module) {
  const file = process.argv[2]
  if (!file) {
    console.error('Usage: node scripts/diagnostics/analyze-positions-hover.cjs <trace.json>')
    process.exitCode = 1
  } else {
    try {
      const trace = JSON.parse(fs.readFileSync(file, 'utf8'))
      console.log(JSON.stringify(analyze(trace), null, 2))
    } catch (e) {
      console.error('Trace analysis failed:', e.message)
      process.exitCode = 1
    }
  }
}
module.exports = { analyze }
