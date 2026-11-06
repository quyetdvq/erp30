/*
 * DinkERP Positions live layout tracer (opt-in only).
 * Enabled by /people/positions?positions_trace=1, via PeoplePositionsView.
 * No API calls, persistence, business mutations, screenshots or user data capture.
 * Samples the real browser's geometry on animation frames around row transitions.
 */
(() => {
  'use strict'

  const KEY = '__dinkPositionsHoverTrace'
  const PATH = '/people/positions'
  if (!location.pathname.endsWith(PATH)) return
  if (window[KEY] && typeof window[KEY].stop === 'function') window[KEY].stop()

  const STARTED = Date.now()
  const CAP = 1600
  const entries = []
  const changes = []
  const layoutShiftEvents = []
  const mutations = []
  const faults = []
  // HF08: The previous trace observed a 46.4px workspace scroll exactly when
  // the hovered row changed, but could not distinguish wheel, keyboard, focus
  // scroll, script scroll or browser restoration. Capture SOURCE evidence.
  // No input field values, row texts, credentials or API payloads are stored.
  const inputEvents = []
  const scrollCalls = []
  const scrollAttributions = []
  const methodRestores = []
  let lastWorkspaceTop = null
  const tracked = [
    ['shell', '.erp-shell'],
    ['shellBody', '.shell-body'],
    ['sidebar', '.shell-sider'],
    ['workspace', '.workspace-layout'],
    ['workspaceScroll', '.workspace-layout .n-layout-scroll-container'],
    ['content', '.workspace-content'],
    ['breadcrumb', '.app-breadcrumbs'],
    ['heading', '.page-heading'],
    ['panel', '.content-panel'],
    ['tableWrap', '.list-table-wrap'],
    ['table', '.list-table-wrap table.list-table'],
    ['row1', '.list-table tbody tr:nth-child(1)'],
    ['row2', '.list-table tbody tr:nth-child(2)'],
    ['row3', '.list-table tbody tr:nth-child(3)'],
    ['row4', '.list-table tbody tr:nth-child(4)'],
    ['row1FirstCell', '.list-table tbody tr:nth-child(1) td:first-child'],
    ['row2FirstCell', '.list-table tbody tr:nth-child(2) td:first-child'],
    ['row1Actions', '.list-table tbody tr:nth-child(1) td:last-child'],
    ['row2Actions', '.list-table tbody tr:nth-child(2) td:last-child'],
  ]
  const elements = Object.fromEntries(tracked.map(([name, selector]) => [name, selector]))
  let running = true
  let tracing = false
  let raf = 0
  let lastCaptured = null
  let captureUntil = 0
  let lastHover = 0
  let latestPointer = { x: null, y: null }
  let listenerBody = null
  let observer = null
  let readinessObserver = null
  let perfObserver = null
  let panel = null
  let status = null
  let detach = []
  let lastFrameTime = -1
  let pointerTarget = null
  let routeGuardInterval = null

  const round = n => Math.round(n * 100) / 100
  const small = r => r && [round(r.x), round(r.y), round(r.width), round(r.height)]
  function boundedPush(list, item) {
    list.push(item)
    if (list.length > 200) list.shift()
  }
  function nodeLabel(target) {
    if (!target || !target.tagName) return 'unknown'
    const cls = typeof target.className === 'string'
      ? target.className.trim().replace(/\s+/g, '.').slice(0, 70) : ''
    return target.tagName + (cls ? '.' + cls : '')
  }
  function recordInput(type, details) {
    boundedPush(inputEvents, { at: round(performance.now()), type, ...details })
  }
  function recordScrollCall(method, target) {
    // Instrumenting calls is opt-in; call the original browser method unchanged.
    // Do not capture method arguments: they may contain sensitive DOM objects.
    boundedPush(scrollCalls, { at: round(performance.now()), method,
      target: nodeLabel(target),
      stack: String(new Error().stack || '').split('\n').slice(2, 7).join('\n').slice(0, 600) })
  }
  function instrument(owner, method) {
    const original = owner?.[method]
    if (typeof original !== 'function') return
    const wrapper = function (...args) {
      recordScrollCall(method, this)
      return original.apply(this, args)
    }
    try {
      owner[method] = wrapper
      if (owner[method] !== wrapper) return
      methodRestores.push(() => { if (owner[method] === wrapper) owner[method] = original })
    } catch (_) { /* method is read-only; input and scroll events remain available */ }
  }
  function installScrollCallMonitoring() {
    for (const name of ['scroll', 'scrollTo', 'scrollBy']) {
      instrument(Element.prototype, name)
      instrument(window, name)
    }
    instrument(Element.prototype, 'scrollIntoView')
    instrument(HTMLElement.prototype, 'focus')
  }
  function recent(list, at, duration) {
    return list.filter(item => item.at <= at && at - item.at <= duration).slice(-12)
  }
  function scrollAttribution(target) {
    if (target !== node('workspaceScroll')) return
    const now = round(performance.now())
    const to = scrollState('workspaceScroll')?.top
    if (typeof to !== 'number') return
    if (lastWorkspaceTop === null) { lastWorkspaceTop = to; return }
    const from = lastWorkspaceTop
    lastWorkspaceTop = to
    if (Math.abs(to - from) < 0.35) return
    const matchingInputs = recent(inputEvents, now, 1500)
    const matchingCalls = recent(scrollCalls, now, 1500)
    // A wheel/focus/call is a temporal lead, not automatically proof of cause.
    boundedPush(scrollAttributions, {
      at: now, from, to, delta: round(to - from),
      pointer: { ...latestPointer }, hover: lastHover,
      possibleInputs: matchingInputs,
      possibleScriptCalls: matchingCalls,
      // Prefer the most recent causal candidate; an older scrollTo during
      // page setup must not override a wheel event right before this scroll.
      classification: (() => {
        const candidates = [
          ...matchingCalls.map(item => ({ at: item.at, type: 'RECENT_SCRIPT_CALL' })),
          ...matchingInputs.filter(item => ['wheel', 'keyboard', 'touch', 'focusin'].includes(item.type))
            .map(item => ({ at: item.at, type: item.type === 'wheel' ? 'RECENT_WHEEL' :
              item.type === 'focusin' ? 'RECENT_FOCUS_CHANGE' : 'RECENT_NAVIGATION_INPUT' })),
        ]
        candidates.sort((a, b) => b.at - a.at)
        return candidates[0]?.type || 'NO_INPUT_OR_SCROLL_API_CALL_RECORDED'
      })(),
    })
  }
  function node(name) { return document.querySelector(elements[name]) }
  function geometry(name) {
    const el = node(name)
    return el ? small(el.getBoundingClientRect()) : null
  }
  function readRowStyle(index) {
    const row = node(index === 1 ? 'row1' : 'row2')
    const cell = row?.querySelector('td')
    if (!row || !cell) return null
    const css = getComputedStyle(cell)
    const rowCss = getComputedStyle(row)
    return {
      hovered: row.matches(':hover'),
      focusWithin: row.matches(':focus-within'),
      background: css.backgroundColor,
      border: css.borderWidth,
      padding: css.padding,
      font: `${css.fontWeight} ${css.fontSize} ${css.lineHeight}`,
      transform: css.transform,
      transition: css.transitionDuration,
      rowDisplay: rowCss.display,
      rowHeight: round(row.getBoundingClientRect().height),
    }
  }
  function scrollState(selector) {
    const el = selector === 'document' ? document.documentElement : node(selector)
    if (!el) return null
    const css = getComputedStyle(el)
    return {
      left: round(el.scrollLeft), top: round(el.scrollTop),
      clientWidth: el.clientWidth, clientHeight: el.clientHeight,
      scrollWidth: el.scrollWidth, scrollHeight: el.scrollHeight,
      overflowX: css.overflowX, overflowY: css.overflowY,
      gutter: css.scrollbarGutter,
    }
  }
  function styleEvidence(name) {
    const el = node(name)
    if (!el) return null
    const css = getComputedStyle(el)
    return {
      className: typeof el.className === 'string' ? el.className.slice(0, 150) : '',
      inlineStyle: el.getAttribute('style')?.slice(0, 300) || '',
      display: css.display, position: css.position,
      width: css.width, height: css.height,
      minWidth: css.minWidth, maxWidth: css.maxWidth,
      margin: css.margin, padding: css.padding,
      overflowX: css.overflowX, overflowY: css.overflowY,
      transform: css.transform, zoom: css.zoom,
      transitionProperty: css.transitionProperty,
      scrollbarGutter: css.scrollbarGutter,
    }
  }
  function frame(reason) {
    const allRects = Object.fromEntries(tracked.map(([name]) => [name, geometry(name)]))
    const scroll = {
      document: scrollState('document'),
      workspace: scrollState('workspaceScroll'),
      content: scrollState('content'),
      tableWrap: scrollState('tableWrap'),
    }
    const viewport = {
      innerWidth: innerWidth, innerHeight: innerHeight,
      visualWidth: window.visualViewport?.width ?? null,
      visualHeight: window.visualViewport?.height ?? null,
      visualScale: window.visualViewport?.scale ?? null,
      clientWidth: document.documentElement.clientWidth,
      dpr: window.devicePixelRatio,
      scrollX: window.scrollX, scrollY: window.scrollY,
      sidebarCssWidth: getComputedStyle(document.querySelector('.erp-shell') || document.body)
        .getPropertyValue('--sidebar-width').trim(),
    }
    return {
      at: round(performance.now()), reason, row: lastHover, pointer: { ...latestPointer }, pointerTarget,
      viewport, rects: allRects, scroll, rowStyles: [readRowStyle(1), readRowStyle(2)],
      activeTag: document.activeElement?.tagName || null,
    }
  }
  function diff(before, after) {
    if (!before) return []
    const out = []
    for (const [name, newer] of Object.entries(after.rects)) {
      const older = before.rects[name]
      if (!older || !newer) continue
      for (let i = 0; i < 4; i++) {
        const delta = round(newer[i] - older[i])
        if (Math.abs(delta) >= 0.35) {
          out.push({ kind: 'ELEMENT_GEOMETRY', element: name, field: ['x', 'y', 'width', 'height'][i], delta })
        }
      }
    }
    for (const key of ['innerWidth', 'innerHeight', 'clientWidth', 'visualWidth', 'visualScale', 'dpr', 'scrollX', 'scrollY', 'sidebarCssWidth']) {
      if (before.viewport[key] !== after.viewport[key]) {
        out.push({ kind: 'VIEWPORT_OR_SIDEBAR', field: key, before: before.viewport[key], after: after.viewport[key] })
      }
    }
    for (const [name, now] of Object.entries(after.scroll)) {
      const prev = before.scroll[name]
      if (!now || !prev) continue
      for (const key of ['left', 'top', 'clientWidth', 'clientHeight', 'scrollWidth', 'scrollHeight']) {
        if (Math.abs(now[key] - prev[key]) >= 0.35) {
          out.push({ kind: 'SCROLL_OR_SIZE', element: name, field: key, delta: round(now[key] - prev[key]) })
        }
      }
    }
    return out
  }
  function sample(reason) {
    if (!running || !tracing) return
    try {
      const now = frame(reason)
      const delta = diff(lastCaptured, now)
      if (delta.length) {
        const changedNames = [...new Set(delta.filter(d => d.kind === 'ELEMENT_GEOMETRY').map(d => d.element))]
        const upstream = ['shell', 'sidebar', 'workspace', 'workspaceScroll', 'content', 'panel']
        const targetNames = [...new Set([...upstream, ...changedNames])]
        changes.push({ at: now.at, reason, hover: now.row, deltas: delta,
          computedEvidence: Object.fromEntries(targetNames.map(name => [name, styleEvidence(name)])) })
        if (changes.length > CAP) changes.shift()
      }
      entries.push(now)
      if (entries.length > CAP) entries.shift()
      lastCaptured = now
      if (entries.length % 12 === 0) updateStatus()
    } catch (error) {
      faults.push(String(error?.message || error))
    }
  }
  function loop(now) {
    raf = 0
    if (!running || !tracing || now > captureUntil) return
    if (now !== lastFrameTime) {
      lastFrameTime = now
      sample('animation-frame')
    }
    raf = requestAnimationFrame(loop)
  }
  function burst(reason) {
    sample(reason)
    captureUntil = performance.now() + 600
    if (!raf) raf = requestAnimationFrame(loop)
  }
  function startIfReady() {
    if (!running || tracing) return
    const tbody = document.querySelector('.list-table-wrap table.list-table tbody')
    if (!tbody || tbody.rows.length < 2) return
    tracing = true
    listenerBody = tbody
    lastWorkspaceTop = scrollState('workspaceScroll')?.top ?? null
    installScrollCallMonitoring()
    if (readinessObserver) { readinessObserver.disconnect(); readinessObserver = null }
    const onPointerMove = event => {
      latestPointer = { x: round(event.clientX), y: round(event.clientY) }
    }
    const onPointerOver = event => {
      const row = event.target.closest?.('tr')
      const index = row && tbody.contains(row) ? [...tbody.rows].indexOf(row) + 1 : 0
      if (!index || (index !== 1 && index !== 2) || index === lastHover) return
      pointerTarget = event.target.tagName +
        (typeof event.target.className === 'string' ? '.' + event.target.className.slice(0, 80) : '')
      lastHover = index
      burst('pointer-over-row-' + index)
    }
    const onPointerLeave = () => {
      lastHover = 0
      burst('pointer-left-table')
    }
    const onScroll = event => {
      scrollAttribution(event.target)
      burst('scroll-' + (event.target?.className?.toString().slice(0, 45) || 'root'))
    }
    const onWheel = event => recordInput('wheel', {
      deltaX: round(event.deltaX), deltaY: round(event.deltaY),
      deltaMode: event.deltaMode, ctrlKey: event.ctrlKey,
      target: nodeLabel(event.target),
    })
    const onKeyDown = event => {
      if (!['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' ', 'Tab'].includes(event.key)) return
      recordInput('keyboard', { key: event.key, target: nodeLabel(event.target) })
    }
    const onTouchMove = event => recordInput('touch', { target: nodeLabel(event.target) })
    const onFocusIn = event => recordInput('focusin', { target: nodeLabel(event.target) })
    const onPointerDown = event => recordInput('pointerdown', {
      pointerType: event.pointerType, target: nodeLabel(event.target),
    })
    const onResize = () => burst('viewport-resize')
    document.addEventListener('pointermove', onPointerMove, { passive: true })
    document.addEventListener('wheel', onWheel, { passive: true, capture: true })
    document.addEventListener('keydown', onKeyDown, { capture: true })
    document.addEventListener('touchmove', onTouchMove, { passive: true, capture: true })
    document.addEventListener('focusin', onFocusIn, { capture: true })
    document.addEventListener('pointerdown', onPointerDown, { passive: true, capture: true })
    tbody.addEventListener('pointerover', onPointerOver, { passive: true })
    tbody.addEventListener('pointerleave', onPointerLeave, { passive: true })
    window.addEventListener('scroll', onScroll, { passive: true, capture: true })
    window.addEventListener('resize', onResize, { passive: true })
    detach = [
      () => document.removeEventListener('pointermove', onPointerMove),
      () => document.removeEventListener('wheel', onWheel, true),
      () => document.removeEventListener('keydown', onKeyDown, true),
      () => document.removeEventListener('touchmove', onTouchMove, true),
      () => document.removeEventListener('focusin', onFocusIn, true),
      () => document.removeEventListener('pointerdown', onPointerDown, true),
      () => tbody.removeEventListener('pointerover', onPointerOver),
      () => tbody.removeEventListener('pointerleave', onPointerLeave),
      () => window.removeEventListener('scroll', onScroll, true),
      () => window.removeEventListener('resize', onResize),
    ]
    const mutationTargets = [document.querySelector('.erp-shell'), document.querySelector('.workspace-content')]
    observer = new MutationObserver(records => {
      for (const m of records) {
        mutations.push({ at: round(performance.now()), type: m.type,
          target: m.target.tagName || m.target.nodeName,
          attribute: m.attributeName || null,
          added: m.addedNodes.length, removed: m.removedNodes.length })
      }
      if (mutations.length > CAP) mutations.splice(0, mutations.length - CAP)
      // DOM changes can cause a shift even without an actual pointer event.
      captureUntil = performance.now() + 600
      if (!raf) raf = requestAnimationFrame(loop)
    })
    for (const el of mutationTargets.filter(Boolean)) {
      observer.observe(el, { subtree: el.classList.contains('workspace-content'), childList: true, attributes: true,
        attributeFilter: ['class', 'style', 'aria-busy'] })
    }
    if (typeof PerformanceObserver !== 'undefined') {
      try {
        perfObserver = new PerformanceObserver(list => {
          for (const item of list.getEntries()) {
            layoutShiftEvents.push({ at: round(item.startTime), value: round(item.value),
              hadRecentInput: item.hadRecentInput,
              sources: (item.sources || []).map(s => ({ previous: small(s.previousRect), current: small(s.currentRect) })).slice(0, 8) })
          }
        })
        perfObserver.observe({ type: 'layout-shift', buffered: false })
      } catch (_) { /* unsupported browser; geometry frames remain authoritative */ }
    }
    sample('ready-baseline')
    updateStatus()
  }
  function report() {
    const first = entries[0]
    const last = entries[entries.length - 1]
    const categories = {}
    for (const change of changes) {
      for (const delta of change.deltas) categories[delta.kind] = (categories[delta.kind] || 0) + 1
    }
    return {
      schema: 'dinkerp.positions.hover-live.v1',
      route: PATH,
      startedAt: new Date(STARTED).toISOString(),
      exportedAt: new Date().toISOString(),
      status: tracing ? 'recording' : 'waiting_for_two_rows',
      summary: { frames: entries.length, eventBatches: changes.length,
        mutationCount: mutations.length, layoutShiftEntries: layoutShiftEvents.length,
        categoryCounts: categories, firstRow: first?.row ?? null, lastRow: last?.row ?? null,
        scrollAttributions: scrollAttributions.length },
      errors: faults.slice(-40), events: changes, mutations, layoutShiftEvents,
      inputEvents, scrollCalls, scrollAttributions, frames: entries,
    }
  }
  function download() {
    const payload = JSON.stringify(report(), null, 2)
    const url = URL.createObjectURL(new Blob([payload], { type: 'application/json' }))
    const link = document.createElement('a')
    link.href = url
    link.download = 'DinkERP_Positions_Hover_Trace_' + new Date().toISOString().replace(/[:.]/g, '-') + '.json'
    document.body.append(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(url), 15000)
  }
  function updateStatus() {
    if (!status) return
    status.textContent = tracing
      ? 'Positions trace: ' + entries.length + ' frames / ' + scrollAttributions.length + ' scrolls / ' + changes.length + ' shifts'
      : 'Positions trace: waiting for two data rows...'
  }
  function stop() {
    running = false
    if (raf) cancelAnimationFrame(raf)
    detach.forEach(f => f())
    methodRestores.reverse().forEach(restore => {
      try { restore() } catch (_) { /* browser may replace a method meanwhile */ }
    })
    observer?.disconnect()
    readinessObserver?.disconnect()
    perfObserver?.disconnect()
    if (routeGuardInterval !== null) clearInterval(routeGuardInterval)
    panel?.remove()
    if (window[KEY]?.stop === stop) delete window[KEY]
  }
  function drawControls() {
    panel = document.createElement('div')
    panel.id = 'dink-positions-trace-controls'
    panel.style.cssText = [
      'position:fixed', 'bottom:14px', 'right:14px', 'z-index:2147483000',
      'display:flex', 'align-items:center', 'gap:8px', 'padding:8px 10px',
      'font:12px/1.3 system-ui,sans-serif', 'color:#fff', 'background:#173553',
      'border:1px solid #9dc1e7', 'border-radius:8px', 'box-shadow:0 4px 18px #0003'
    ].join(';')
    status = document.createElement('span')
    status.setAttribute('aria-live', 'polite')
    status.textContent = 'Positions trace: initializing...'
    const exportButton = document.createElement('button')
    exportButton.type = 'button'
    exportButton.textContent = 'Export JSON'
    exportButton.setAttribute('aria-label', 'Download Positions hover geometry diagnostics')
    exportButton.style.cssText = 'padding:7px;border:1px solid white;background:#fff;color:#173553;border-radius:5px;cursor:pointer'
    exportButton.addEventListener('click', download)
    const stopButton = document.createElement('button')
    stopButton.type = 'button'
    stopButton.textContent = 'Stop'
    stopButton.style.cssText = 'padding:7px;border:1px solid #ddd;background:transparent;color:#fff;border-radius:5px;cursor:pointer'
    stopButton.addEventListener('click', stop)
    panel.append(status, exportButton, stopButton)
    document.body.append(panel)
  }

  window[KEY] = { report, download, stop }
  drawControls()
  startIfReady()
  if (!tracing) {
    readinessObserver = new MutationObserver(startIfReady)
    readinessObserver.observe(document.body, { subtree: true, childList: true })
  }
  routeGuardInterval = setInterval(() => {
    if (!location.pathname.endsWith(PATH)) stop()
  }, 1500)
  updateStatus()
  console.info('[DinkERP Positions Trace] Enabled by URL parameter. No business data recorded. Use Export JSON on the page.')
})()
