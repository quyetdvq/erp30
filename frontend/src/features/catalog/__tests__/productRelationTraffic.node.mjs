import { strict as assert } from 'node:assert'
import { test } from 'node:test'
import { loadProductRelationBatches } from '../productRelationTraffic.ts'

function deferred() {
  let resolve
  let reject
  const promise = new Promise((success, fail) => { resolve = success; reject = fail })
  return { promise, resolve, reject }
}

test('does not call relation preview when a page has no PARENT/COMBO IDs', async () => {
  let calls = 0
  await loadProductRelationBatches([], async () => { calls++; return [] }, () => {}, () => true)
  assert.equal(calls, 0)
})

test('page_size=200 chunks at the frozen 50-ID limit with no duplicates', async () => {
  const ids = Array.from({ length: 200 }, (_, i) => String(i + 1))
  const requests = []
  const published = []
  await loadProductRelationBatches(ids, async (batch) => {
    requests.push(batch)
    return [...batch]
  }, (batch, rows) => published.push([batch, rows]), () => true)
  assert.deepEqual(requests.map((batch) => batch.length), [50, 50, 50, 50])
  assert.deepEqual(requests.flat(), ids)
  assert.deepEqual(published.flatMap(([, result]) => result), ids)
})

test('first preview batch publishes while a later batch is still loading', async () => {
  const pending = deferred()
  const events = []
  const ids = Array.from({ length: 51 }, (_, i) => String(i))
  const task = loadProductRelationBatches(ids, async (batch) => {
    if (batch[0] === '50') return pending.promise
    return ['first']
  }, (batch, result) => events.push([batch[0], result]), () => true)
  await Promise.resolve()
  await Promise.resolve()
  assert.deepEqual(events, [['0', ['first']]])
  pending.resolve(['second'])
  await task
  assert.deepEqual(events, [['0', ['first']], ['50', ['second']]])
})

test('old page/session batches never publish after being superseded', async () => {
  const pending = deferred()
  let current = true
  const published = []
  const task = loadProductRelationBatches(['1'], () => pending.promise,
    (ids) => published.push(ids), () => current)
  current = false
  pending.resolve([{ id: '1' }])
  await task
  assert.deepEqual(published, [])
})

test('failed batch rejects; later successful batches cannot revive a failed view', async () => {
  const pending = deferred()
  const published = []
  const ids = Array.from({ length: 51 }, (_, i) => String(i))
  const task = loadProductRelationBatches(ids, async (batch) => {
    if (batch[0] === '0') throw new Error('preview down')
    return pending.promise
  }, (batch) => published.push(batch), () => true)
  await assert.rejects(task, /preview down/)
  pending.resolve([{ id: '50' }])
  await Promise.resolve()
  assert.deepEqual(published, [])
})
