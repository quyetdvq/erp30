/**
 * DinkERP Positions hover diagnostic: paste the entire file into DevTools
 * Console while signed in on /people/positions. No network or persistent writes.
 * Move the pointer between first and second data rows, then run:
 *   copy(window.__dinkPositionsHoverProbe.report())
 * To remove listeners, run:
 *   window.__dinkPositionsHoverProbe.stop()
 */
(() => {
  const existing = window.__dinkPositionsHoverProbe
  if (existing && typeof existing.stop === 'function') existing.stop()
  const table = document.querySelector('.list-table-wrap table.list-table')
  const tbody = table?.tBodies?.[0]
  if (!table || !tbody || tbody.rows.length < 2) {
    console.warn('[DinkERP Positions hover] Need at least two rendered rows in /people/positions')
    return
  }
  const wrapper = table.closest('.list-table-wrap')
  const scrollOwner = document.querySelector('.workspace-layout .n-layout-scroll-container')
  const observed = Array.from(tbody.rows).slice(0, 2)
  const entries = []
  const changes = []
  const pending = new Set()
  let active = true
  let previousRow = -1
  const names = ['Table wrapper', 'Table', 'Row 1', 'Row 2',
    'Workspace scroll owner', 'Workspace content', 'Breadcrumb', 'Heading', 'Panel']

  function metrics(element) {
    if (!element) return null
    const rect = element.getBoundingClientRect()
    const style = getComputedStyle(element)
    return {
      rect: [rect.x, rect.y, rect.width, rect.height].map((v) => Math.round(v * 100) / 100),
      background: style.backgroundColor,
      padding: style.padding,
      border: style.borderWidth,
      font: `${style.fontWeight} ${style.fontSize}/${style.lineHeight}`,
      hover: element.matches(':hover'),
      focusWithin: element.matches(':focus-within'),
    }
  }

  function snapshot(kind, row) {
    if (!active) return
    const elements = [wrapper, table, ...observed, scrollOwner,
      document.querySelector('.workspace-content'),
      document.querySelector('.app-breadcrumbs'),
      document.querySelector('.page-heading'),
      document.querySelector('.content-panel')]
    const values = Object.fromEntries(elements.map((el, i) => [names[i], metrics(el)]))
    for (let i = 0; i < observed.length; i++) {
      observed[i].querySelectorAll('td').forEach((el, j) => {
        values[`Row ${i + 1} Cell ${j + 1}`] = metrics(el)
      })
      observed[i].querySelectorAll('button').forEach((el, j) => {
        values[`Row ${i + 1} Button ${j + 1}`] = metrics(el)
      })
    }
    const baseline = entries[0]?.values
    const shifts = []
    if (baseline) {
      for (const [name, current] of Object.entries(values)) {
        const before = baseline[name]
        if (!current || !before) continue
        const fields = ['x', 'y', 'width', 'height']
        for (let i = 0; i < 4; i++) {
          const delta = Math.round((current.rect[i] - before.rect[i]) * 100) / 100
          if (Math.abs(delta) > 0.25) shifts.push({ element: name, field: fields[i], delta })
        }
      }
    }
    entries.push({
      elapsedMs: Math.round(performance.now()),
      kind,
      activeRow: row,
      windowScroll: [window.scrollX, window.scrollY],
      wrapperScroll: [wrapper.scrollLeft, wrapper.scrollTop],
      viewport: [window.innerWidth, window.innerHeight],
      scrollGeometry: {
        documentClientWidth: document.documentElement.clientWidth,
        documentScrollWidth: document.documentElement.scrollWidth,
        visualViewportWidth: window.visualViewport?.width ?? null,
        workspaceGutter: scrollOwner ? getComputedStyle(scrollOwner).scrollbarGutter : null,
        workspaceOverflowY: scrollOwner ? getComputedStyle(scrollOwner).overflowY : null,
        workspaceClientWidth: scrollOwner?.clientWidth ?? null,
        workspaceScrollWidth: scrollOwner?.scrollWidth ?? null,
        workspaceScrollHeight: scrollOwner?.scrollHeight ?? null,
        workspaceClientHeight: scrollOwner?.clientHeight ?? null,
        workspaceScrollTop: scrollOwner?.scrollTop ?? null,
      },
      focused: document.activeElement ? {
        tag: document.activeElement.tagName,
        className: document.activeElement.className?.toString().slice(0, 100),
        ariaLabel: document.activeElement.getAttribute?.('aria-label'),
      } : null,
      shifts,
      values,
    })
    if (entries.length > 220) entries.shift()
  }
  function schedule(fn, ms) {
    const id = window.setTimeout(() => {
      pending.delete(id)
      if (active) fn()
    }, ms)
    pending.add(id)
  }
  function onPointerOver(event) {
    const row = event.target.closest?.('tbody tr')
    if (!row || !tbody.contains(row)) return
    const index = observed.indexOf(row)
    if (index === -1 || previousRow === index) return
    previousRow = index
    snapshot('pointerover', index + 1)
    for (const delay of [40, 150, 320]) {
      schedule(() => snapshot(`pointerover +${delay}ms`, index + 1), delay)
    }
  }
  function onPointerLeave() {
    previousRow = -1
    snapshot('pointerleave', 0)
  }
  const observer = new MutationObserver((mutations) => {
    if (!active) return
    for (const mutation of mutations) {
      changes.push({
        kind: mutation.type,
        target: mutation.target.nodeName,
        attribute: mutation.attributeName,
        childListAdded: mutation.addedNodes.length,
        childListRemoved: mutation.removedNodes.length,
        ms: Math.round(performance.now()),
      })
    }
    if (changes.length > 100) changes.splice(0, changes.length - 100)
  })
  observer.observe(tbody, { subtree: true, childList: true, attributes: true,
    attributeFilter: ['class', 'style', 'aria-busy'] })
  tbody.addEventListener('pointerover', onPointerOver)
  tbody.addEventListener('pointerleave', onPointerLeave)
  snapshot('baseline', 0)
  window.__dinkPositionsHoverProbe = {
    report() {
      const allShifts = entries.flatMap((entry) => entry.shifts.map((shift) => ({
        event: entry.kind,
        activeRow: entry.activeRow,
        ...shift,
      })))
      const output = {
        route: location.pathname,
        capturedAt: new Date().toISOString(),
        sampleCount: entries.length,
        mutationCount: changes.length,
        layoutShifts: allShifts,
        mutations: changes,
        samples: entries,
      }
      console.log('[DinkERP Positions hover] Samples:', entries.length,
        'Geometry differences:', allShifts.length, 'DOM mutations:', changes.length)
      return JSON.stringify(output, null, 2)
    },
    stop() {
      active = false
      tbody.removeEventListener('pointerover', onPointerOver)
      tbody.removeEventListener('pointerleave', onPointerLeave)
      observer.disconnect()
      for (const id of pending) clearTimeout(id)
      pending.clear()
      delete window.__dinkPositionsHoverProbe
      console.log('[DinkERP Positions hover] stopped')
    },
  }
  console.log('[DinkERP Positions hover] Active. Move the mouse between rows 1 and 2. Then run copy(window.__dinkPositionsHoverProbe.report()).')
})()
