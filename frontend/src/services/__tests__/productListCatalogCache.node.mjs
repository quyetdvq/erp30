import { strict as assert } from 'node:assert'
import { test } from 'node:test'
import {
  PRODUCT_LIST_CATALOG_TTL_MS,
  productListCatalogScope,
  invalidateProductListCatalog,
  loadProductListCatalog,
} from '../productListCatalogCache.ts'

function deferred() {
  let resolve
  let reject
  const promise = new Promise((done, fail) => {
    resolve = done
    reject = fail
  })
  return { promise, resolve, reject }
}

// The production cache is imported directly, without a simulated implementation.
test('missing session scope bypasses cache to avoid unauthenticated reuse', async () => {
  invalidateProductListCatalog()
  let reads = 0
  const read = async () => [++reads]
  assert.deepEqual(await loadProductListCatalog('groups', null, read), [1])
  assert.deepEqual(await loadProductListCatalog('groups', null, read), [2])
})

test('in-flight request is coalesced, including before it resolves', async () => {
  invalidateProductListCatalog()
  const pending = deferred()
  let reads = 0
  const loader = () => { reads++; return pending.promise }
  const first = loadProductListCatalog('groups', 'actor1:session1', loader)
  const second = loadProductListCatalog('groups', 'actor1:session1', loader)
  assert.equal(first, second)
  await Promise.resolve()
  assert.equal(reads, 1)
  pending.resolve([{ id: '5' }])
  assert.deepEqual(await first, [{ id: '5' }])
})

test('reentering the Product list within TTL reuses labels without another GET', async () => {
  invalidateProductListCatalog()
  let reads = 0
  const loader = async () => [{ code: `G${++reads}` }]
  assert.deepEqual(await loadProductListCatalog('groups', 'actor1:session1', loader), [{ code: 'G1' }])
  assert.deepEqual(await loadProductListCatalog('groups', 'actor1:session1', loader), [{ code: 'G1' }])
  assert.equal(reads, 1)
})

test('success expires after the bounded TTL', async () => {
  invalidateProductListCatalog()
  const realNow = Date.now
  let now = 1000
  Date.now = () => now
  try {
    let reads = 0
    const loader = async () => [++reads]
    assert.deepEqual(await loadProductListCatalog('uoms', 'actor1', loader), [1])
    now += PRODUCT_LIST_CATALOG_TTL_MS - 1
    assert.deepEqual(await loadProductListCatalog('uoms', 'actor1', loader), [1])
    now++
    assert.deepEqual(await loadProductListCatalog('uoms', 'actor1', loader), [2])
  } finally {
    Date.now = realNow
  }
})

test('changing user/session/permission scope never returns previous labels', async () => {
  invalidateProductListCatalog()
  let reads = 0
  const loader = async () => [++reads]
  await loadProductListCatalog('groups', 'user1:session1:permissionsA', loader)
  assert.deepEqual(await loadProductListCatalog('groups', 'user1:session1:permissionsB', loader), [2])
  assert.deepEqual(await loadProductListCatalog('groups', 'user2:session2:permissionsB', loader), [3])
})

test('failed fetch is never cached; retry reloads successful catalog', async () => {
  invalidateProductListCatalog()
  let reads = 0
  const loader = async () => {
    if (++reads === 1) throw new Error('offline')
    return [42]
  }
  await assert.rejects(loadProductListCatalog('uoms', 'u1', loader), /offline/)
  assert.deepEqual(await loadProductListCatalog('uoms', 'u1', loader), [42])
  assert.equal(reads, 2)
})

test('successful UOM mutation invalidation preserves independent group cache', async () => {
  invalidateProductListCatalog()
  let groups = 0
  let uoms = 0
  const groupLoader = async () => [++groups]
  const uomLoader = async () => [++uoms]
  await loadProductListCatalog('groups', 'same-session', groupLoader)
  await loadProductListCatalog('uoms', 'same-session', uomLoader)
  invalidateProductListCatalog('uoms')
  assert.deepEqual(await loadProductListCatalog('groups', 'same-session', groupLoader), [1])
  assert.deepEqual(await loadProductListCatalog('uoms', 'same-session', uomLoader), [2])
})

test('invalidation while old request is pending cannot evict newer result', async () => {
  invalidateProductListCatalog()
  const pending = deferred()
  const first = loadProductListCatalog('groups', 'same-session', () => pending.promise)
  invalidateProductListCatalog('groups')
  const second = loadProductListCatalog('groups', 'same-session', async () => ['new'])
  assert.deepEqual(await second, ['new'])
  pending.resolve(['old'])
  assert.deepEqual(await first, ['old'])
  assert.deepEqual(await loadProductListCatalog('groups', 'same-session', async () => ['wrong']), ['new'])
})

test('older rejected request cannot evict a newer scope', async () => {
  invalidateProductListCatalog()
  const pending = deferred()
  const first = loadProductListCatalog('groups', 'user1', () => pending.promise)
  assert.deepEqual(await loadProductListCatalog('groups', 'user2', async () => ['user2']), ['user2'])
  pending.reject(new Error('old session rejected'))
  await assert.rejects(first, /old session/)
  assert.deepEqual(await loadProductListCatalog('groups', 'user2', async () => ['wrong']), ['user2'])
})

test('scope is stable for permission order but changes for identity, locale or snapshot', () => {
  const input = {
    userId: 1, sessionGeneration: 6, locale: 'vi',
    permissionState: 'ready', permissions: ['product_uom.view', 'product_group.view'],
  }
  const before = [...input.permissions]
  const first = productListCatalogScope(input)
  assert.equal(first, productListCatalogScope({ ...input, permissions: [...before].reverse() }))
  assert.deepEqual(input.permissions, before)
  assert.notEqual(first, productListCatalogScope({ ...input, userId: 2 }))
  assert.notEqual(first, productListCatalogScope({ ...input, sessionGeneration: 7 }))
  assert.notEqual(first, productListCatalogScope({ ...input, locale: 'en' }))
  assert.notEqual(first, productListCatalogScope({ ...input, permissions: ['product_uom.view'] }))
  assert.equal(productListCatalogScope({ ...input, permissionState: 'stale' }), null)
  assert.equal(productListCatalogScope({ ...input, userId: null }), null)
})
