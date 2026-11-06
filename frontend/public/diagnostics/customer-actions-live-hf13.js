/*
 * DinkERP CSS-A02-HF13 Customers-only diagnostics.
 * Opt-in: /customers?customers_trace=1. No API calls, data writes, screenshots,
 * customer text, cell contents, credentials or local persistence.
 * Captures permission-snapshot phases, action presence, cell geometry and
 * scroll/input correlation. The app itself remains read-only to the tracer.
 */
(() => {
  'use strict'
  if (location.pathname !== '/customers') return
  const KEY = '__dinkCustomerActionsTrace'
  if (window[KEY]?.stop) window[KEY].stop()
  const started = Date.now()
  const frameCap = 2200
  const events = []
  const frames = []
  const inputs = []
  const marks = []
  const mutations = []
  const scrollCalls = []
  const cleanups = []
  const originalMethods = []
  const round = n => Math.round(n * 100) / 100
  const bounds = el => {
    if (!el) return null
    const r = el.getBoundingClientRect()
    return [round(r.x), round(r.y), round(r.width), round(r.height)]
  }
  const limit = (array, record, cap) => {
    array.push(record)
    if (array.length > cap) array.splice(0, array.length - cap)
  }
  const label = el => {
    if (!(el instanceof Element)) return 'unknown'
    const tag = el.tagName.toLowerCase()
    const cls = typeof el.className === 'string' ? el.className.trim().replace(/\s+/g, '.').slice(0, 72) : ''
    return tag + (cls ? '.' + cls : '')
  }
  let panel
  let summaryEl
  let raf = 0
  let alive = true
  let observedTbody = null
  let mutationObserver = null
  let last = null
  let pointer = { x: null, y: null }
  let lastDraw = 0
  let changes = 0
  const listen = (target, type, handler, options) => {
    target.addEventListener(type, handler, options)
    cleanups.push(() => target.removeEventListener(type, handler, options))
  }
  const elements = () => ({
    shell: document.querySelector('.workspace-layout'),
    owner: document.querySelector('.workspace-layout .n-layout-scroll-container'),
    content: document.querySelector('.workspace-content'),
    panel: document.querySelector('.customer-list-panel'),
    wrap: document.querySelector('.customer-list-panel .list-table-wrap--sticky-actions'),
    table: document.querySelector('.customer-list-panel table.list-table'),
    tbody: document.querySelector('.customer-list-panel table.list-table tbody'),
  })
  function frame(reason) {
    const els = elements()
    const rows = Array.from(els.tbody?.rows || []).slice(0, 8)
    const target = pointer.x == null ? null : document.elementFromPoint(pointer.x, pointer.y)
    const under = target?.closest('tbody tr')
    const hoveredIndex = under && els.tbody?.contains(under) ? Array.from(els.tbody.rows).indexOf(under) + 1 : 0
    const actionRows = rows.map(row => {
      const cell = row.lastElementChild
      const actions = cell?.querySelector('.table-row-actions')
      const buttons = actions ? Array.from(actions.querySelectorAll('button')) : []
      const s = cell ? getComputedStyle(cell) : null
      return {
        row: bounds(row), cell: bounds(cell), actionBox: bounds(actions),
        buttons: buttons.map(b => ({ icon: b.getAttribute('data-ds-icon') || '', rect: bounds(b), disabled: b.disabled })),
        hover: row.matches(':hover'), focused: row.matches(':focus-within'),
        stickyPosition: s?.position || null, background: s?.backgroundColor || null,
      }
    })
    const scroll = (el) => el ? {
      x: round(el.scrollLeft), y: round(el.scrollTop),
      clientWidth: el.clientWidth, clientHeight: el.clientHeight,
      scrollWidth: el.scrollWidth, scrollHeight: el.scrollHeight,
    } : null
    const permission = els.panel?.getAttribute('data-customer-permission-snapshot') || 'not-available'
    return {
      at: round(performance.now()), reason, pointer: { ...pointer }, hoveredIndex, permission,
      page: { w: innerWidth, h: innerHeight, dpr: devicePixelRatio,
        scale: window.visualViewport?.scale ?? 1 },
      rects: { shell: bounds(els.shell), owner: bounds(els.owner), content: bounds(els.content),
        panel: bounds(els.panel), wrap: bounds(els.wrap), table: bounds(els.table) },
      scrolls: { owner: scroll(els.owner), wrap: scroll(els.wrap), root: scroll(document.documentElement) },
      rows: actionRows, count: els.tbody?.rows.length || 0,
    }
  }
  function significant(previous, current) {
    if (!previous) return ['baseline']
    const out = []
    if (previous.permission !== current.permission) out.push('PERMISSION_SNAPSHOT_CHANGED')
    if (previous.count !== current.count) out.push('ROW_COUNT_CHANGED')
    if (previous.hoveredIndex !== current.hoveredIndex) out.push('POINTER_ROW_CHANGED')
    for (const [key, name] of [['owner','WORKSPACE_SCROLL_CHANGED'], ['wrap','TABLE_SCROLL_CHANGED']]) {
      const a = previous.scrolls[key], b = current.scrolls[key]
      if (a && b && (Math.abs(a.x - b.x) > .34 || Math.abs(a.y - b.y) > .34)) out.push(name)
    }
    for (const [key, name] of [['shell','SHELL_GEOMETRY'], ['panel','PANEL_GEOMETRY'], ['table','TABLE_GEOMETRY']]) {
      const a = previous.rects[key], b = current.rects[key]
      if (a && b && a.some((v, i) => Math.abs(v - b[i]) > .34)) out.push(name)
    }
    const max = Math.max(previous.rows.length, current.rows.length)
    for (let i = 0; i < max; i++) {
      const a = previous.rows[i], b = current.rows[i]
      if (!a || !b) { out.push('ROW_ELEMENT_CHANGED'); continue }
      if (a.buttons.length !== b.buttons.length ||
          a.buttons.some((v, j) => v.icon !== b.buttons[j]?.icon)) out.push('ROW_ACTIONS_CHANGED_' + (i + 1))
      for (const k of ['row','cell','actionBox']) {
        if (a[k] && b[k] && a[k].some((v, j) => Math.abs(v - b[k][j]) > .34)) {
          out.push('ROW_' + (i + 1) + '_' + k.toUpperCase() + '_GEOMETRY')
        }
      }
    }
    return [...new Set(out)]
  }
  function sample(reason) {
    if (!alive || !document.querySelector('.customer-list-panel')) return
    const current = frame(reason)
    const kinds = significant(last, current)
    if (kinds.length) {
      changes += 1
      limit(events, { at: current.at, kinds, before: last, after: current }, 500)
    }
    limit(frames, current, frameCap)
    last = current
    if (summaryEl && (changes < 10 || changes % 4 === 0)) {
      summaryEl.textContent = 'Customer trace · ' + frames.length + ' frames · ' + changes + ' events · ' + current.permission
    }
  }
  function watchDOM() {
    const tbody = elements().tbody
    if (tbody === observedTbody) return
    mutationObserver?.disconnect()
    observedTbody = tbody
    if (!tbody) return
    mutationObserver = new MutationObserver(records => {
      limit(mutations, { at: round(performance.now()),
        count: records.length,
        adds: records.reduce((a, r) => a + r.addedNodes.length, 0),
        removes: records.reduce((a, r) => a + r.removedNodes.length, 0) }, 400)
      sample('tbody-mutation')
    })
    mutationObserver.observe(tbody, { subtree: true, childList: true, attributes: true,
      attributeFilter: ['class','style','disabled','aria-busy'] })
  }
  function loop(t) {
    if (!alive) return
    if (location.pathname !== '/customers') { stop(); return }
    if (t - lastDraw >= 33) {
      lastDraw = t
      watchDOM()
      sample('animation-frame')
    }
    raf = requestAnimationFrame(loop)
  }
  function installScrollCalls() {
    const instrument = (obj, name) => {
      const original = obj?.[name]
      if (typeof original !== 'function') return
      const wrapped = function (...args) {
        limit(scrollCalls, { at: round(performance.now()), method: name,
          target: label(this), stack: String(new Error().stack || '').split('\n').slice(2, 6).join('\n').slice(0, 500) }, 300)
        return original.apply(this, args)
      }
      try {
        obj[name] = wrapped
        if (obj[name] === wrapped) originalMethods.push(() => { if (obj[name] === wrapped) obj[name] = original })
      } catch (_) { /* immutable browser API */ }
    }
    for (const method of ['scroll','scrollTo','scrollBy']) {
      instrument(Element.prototype, method)
      instrument(window, method)
    }
    instrument(Element.prototype, 'scrollIntoView')
    instrument(HTMLElement.prototype, 'focus')
  }
  function input(kind, extra) { limit(inputs, { at: round(performance.now()), kind, ...extra }, 400) }
  listen(document, 'pointermove', e => { pointer = { x: round(e.clientX), y: round(e.clientY) } }, { passive: true, capture: true })
  listen(document, 'wheel', e => input('wheel', { dx: round(e.deltaX), dy: round(e.deltaY), ctrl: e.ctrlKey }), { passive: true, capture: true })
  listen(document, 'keydown', e => {
    if (['ArrowUp','ArrowDown','PageUp','PageDown','Home','End','Tab',' '].includes(e.key)) input('key', { key: e.key })
  }, true)
  listen(document, 'focusin', e => input('focus', { target: label(e.target) }), true)
  listen(document, 'pointerdown', e => input('pointerdown', { target: label(e.target) }), { passive: true, capture: true })
  listen(window, 'scroll', e => { input('scroll', { target: label(e.target) }); sample('scroll') }, { passive: true, capture: true })
  listen(window, 'resize', () => sample('resize'), { passive: true })
  installScrollCalls()
  function mark() {
    limit(marks, { at: round(performance.now()), snapshot: frame('USER_MARKED_GLITCH') }, 50)
    sample('user-mark')
    summaryEl.textContent = 'Đã đánh dấu lần nhảy #' + marks.length + ' — tiếp tục hoặc Xuất JSON'
  }
  function report() {
    const categories = {}
    for (const ev of events) for (const kind of ev.kinds) categories[kind] = (categories[kind] || 0) + 1
    return {
      schema: 'dinkerp.customers.actions.hf13.v1', route: location.pathname,
      startedAt: new Date(started).toISOString(), exportedAt: new Date().toISOString(),
      summary: { frames: frames.length, eventCount: events.length, marks: marks.length, categories },
      events, frames, inputs, mutations, marks, scrollCalls,
    }
  }
  function download() {
    const url = URL.createObjectURL(new Blob([JSON.stringify(report(), null, 2)], { type: 'application/json' }))
    const link = document.createElement('a')
    link.href = url
    link.download = 'DinkERP_Customers_Action_Trace_' + new Date().toISOString().replace(/[:.]/g, '-') + '.json'
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(url), 15000)
  }
  function stop() {
    if (!alive) return
    alive = false
    cancelAnimationFrame(raf)
    mutationObserver?.disconnect()
    cleanups.forEach(fn => fn())
    originalMethods.reverse().forEach(fn => { try { fn() } catch (_) {} })
    panel?.remove()
    if (window[KEY]?.stop === stop) delete window[KEY]
  }
  panel = document.createElement('div')
  panel.id = 'dink-customer-live-trace-controls'
  panel.style.cssText = 'position:fixed;right:14px;bottom:14px;z-index:2147483000;display:flex;align-items:center;gap:8px;max-width:min(94vw,520px);padding:9px 12px;border-radius:7px;border:1px solid #a2c6eb;background:#173553;color:#fff;font:12px/1.4 system-ui,sans-serif;box-shadow:0 4px 18px #0003'
  summaryEl = document.createElement('span')
  summaryEl.style.cssText = 'min-width:0;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis'
  summaryEl.textContent = 'Customer trace: đang khởi tạo...'
  const makeButton = (name, handler) => {
    const button = document.createElement('button')
    button.type = 'button'
    button.textContent = name
    button.style.cssText = 'flex:0 0 auto;padding:6px;border:1px solid #fff;border-radius:4px;background:#fff;color:#173553;cursor:pointer'
    button.addEventListener('click', handler)
    return button
  }
  panel.append(summaryEl, makeButton('Đánh dấu nhảy', mark), makeButton('Xuất JSON', download), makeButton('Tắt', stop))
  document.body.appendChild(panel)
  window[KEY] = { report, mark, download, stop }
  sample('init')
  raf = requestAnimationFrame(loop)
  console.info('[DinkERP Customer Trace] Enabled only by customers_trace=1. No customer data or API payloads are collected.')
})()
