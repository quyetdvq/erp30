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
      registration: {
        async showNotification(title, options) { notifications.push({ title, options }) },
      },
      clients: {
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
