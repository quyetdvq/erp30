import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import vm from 'node:vm'

// Execute the actual shipped Service Worker file with restricted stubs.
// These tests never create a Push subscription, contact a provider, or fetch ERP APIs.
const source = readFileSync(new URL('../public/notification-sw.js', import.meta.url), 'utf8')

function workerHarness(clients = []) {
  const handlers = new Map()
  const notifications = []
  const opens = []
  const context = {
    URL,
    Promise,
    self: {
      location: { origin: 'https://erp2.monmin.net' },
      async skipWaiting() {},
      registration: {
        async showNotification(title, options) { notifications.push({ title, options }) },
      },
      clients: {
        async claim() {},
        async matchAll() { return clients },
        async openWindow(url) { opens.push(url) },
      },
      addEventListener(name, handler) { handlers.set(name, handler) },
    },
  }
  vm.runInNewContext(source, context, { filename: 'notification-sw.js', timeout: 1000 })
  async function event(name, extra = {}) {
    let promise
    const e = { waitUntil(value) { promise = Promise.resolve(value) }, ...extra }
    handlers.get(name)?.(e)
    assert.ok(promise, `expected ${name} handler to call waitUntil`)
    await promise
  }
  return { event, notifications, opens }
}

test('push shows a generic, content-free notification pointing only to ERP Inbox', async () => {
  const h = workerHarness()
  await h.event('push', { data: { text: () => 'private-record-123' } })
  assert.equal(h.notifications.length, 1)
  const { title, options } = h.notifications[0]
  assert.equal(title, 'DinkERP')
  assert.equal(options.data.path, '/notifications')
  assert.ok(!JSON.stringify({ title, options }).includes('private-record-123'))
  assert.ok(!JSON.stringify({ title, options }).includes('http://'))
})

test('click navigates/focuses an existing same-origin ERP tab (ignores foreign tabs)', async () => {
  const calls = []
  const clients = [
    { url: 'https://unrelated.example/path', navigate: async () => { throw new Error('cross origin') } },
    { url: 'https://erp2.monmin.net/orders', navigate: async (path) => { calls.push(['navigate', path]) },
      focus: async () => { calls.push(['focus']) } },
  ]
  const h = workerHarness(clients)
  let closed = false
  await h.event('notificationclick', { notification: { close() { closed = true } } })
  assert.ok(closed)
  assert.deepEqual(calls, [['navigate', '/notifications'], ['focus']])
  assert.deepEqual(h.opens, [])
})

test('click opens ERP Inbox when no existing same-origin tab exists', async () => {
  const h = workerHarness([{ url: 'https://unrelated.example/path' }])
  await h.event('notificationclick', { notification: { close() {} } })
  assert.deepEqual(h.opens, ['/notifications'])
})

test('encrypted import facts show a meaningful summary without exposing arbitrary text', async () => {
  const h = workerHarness()
  await h.event('push', { data: { json: () => ({
    v: 1, event: 'import.completed_with_errors', id: '87', success: 42, errors: 3,
    private: 'secret-customer-email@example.com',
  }) } })
  assert.equal(h.notifications.length, 1)
  assert.match(h.notifications[0].title, /Import Product có lỗi/)
  assert.match(h.notifications[0].options.body, /#87.*42.*3/)
  assert.ok(!JSON.stringify(h.notifications[0]).includes('secret-customer-email'))
  assert.equal(h.notifications[0].options.data.path, '/notifications')
})

test('workflow fact identifies resource and state but rejects spoofed resource text', async () => {
  const h = workerHarness()
  await h.event('push', { data: { json: () => ({
    v: 1, event: 'workflow.action_required', id: '29', resource: 'product', state: 'ACTIVE',
  }) } })
  assert.match(h.notifications[0].options.body, /Sản phẩm #29.*ACTIVE/)
  await h.event('push', { data: { json: () => ({
    v: 1, event: 'workflow.action_required', id: '29', resource: '<script>', state: 'ACTIVE',
  }) } })
  assert.equal(h.notifications[1].title, 'DinkERP')
})


test('custom Workflow approval states remain topic-specific and do not fall back to generic', async () => {
  const h = workerHarness()
  await h.event('push', { data: { json: () => ({
    v: 1, event: 'workflow.action_required', id: '19', resource: 'customer_contact', state: 'WAITING_APPROVAL',
  }) } })
  assert.equal(h.notifications.length, 1)
  assert.equal(h.notifications[0].title, 'Có quy trình cần xử lý')
  assert.match(h.notifications[0].options.body, /Liên hệ khách hàng #19.*WAITING_APPROVAL/)
})

test('unknown safe Workflow resource produces a neutral label rather than arbitrary text', async () => {
  const h = workerHarness()
  await h.event('push', { data: { json: () => ({
    v: 1, event: 'workflow.action_required', id: '23', resource: 'future_source', state: 'REVIEW',
  }) } })
  assert.equal(h.notifications[0].title, 'Có quy trình cần xử lý')
  assert.match(h.notifications[0].options.body, /Đối tượng #23.*REVIEW/)
  assert.ok(!h.notifications[0].options.body.includes('future_source'))
})


test('new Service Worker activates without requiring ERP tabs to close', async () => {
  const h = workerHarness()
  await h.event('install')
  await h.event('activate')
})
