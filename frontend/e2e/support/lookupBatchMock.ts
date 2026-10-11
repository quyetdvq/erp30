import type { Page, Route } from '@playwright/test'

export type ApiCall = {
  method: string
  path: string
  query: URLSearchParams
  body: unknown
}

export const simple = {
  id: '30',
  product_code: 'E2E-SIMPLE',
  sku: 'SIMPLE-30',
  product_name: 'E2E Simple',
  full_name: 'E2E Simple',
  product_type: 'STANDARD',
  role: 'SIMPLE',
  base_uom_id: '20',
  status: 'ACTIVE',
  selectable: true,
}
export const child = {
  ...simple,
  id: '33',
  product_code: 'E2E-CHILD',
  sku: 'CHILD-33',
  product_name: 'E2E Child',
  full_name: 'E2E Child',
  role: 'CHILD',
  parent_product_id: '31',
}
export const parent = {
  ...simple,
  id: '31',
  product_code: 'E2E-PARENT',
  sku: 'PARENT-31',
  product_name: 'E2E Parent',
  full_name: 'E2E Parent',
  role: 'PARENT',
  base_uom_id: null,
  selectable: false,
}
export const combo = {
  ...simple,
  id: '32',
  product_code: 'E2E-COMBO',
  sku: 'COMBO-32',
  product_name: 'E2E Combo',
  full_name: 'E2E Combo',
  product_type: 'COMBO',
  role: 'COMBO',
}
export const priceList = {
  id: '70',
  code: 'E2E-RETAIL',
  name: 'E2E Retail',
  organization_id: '1',
  effective_from: '2026-10-01T00:00:00Z',
  effective_to: null,
  priority: 10,
  status: 'ACTIVE',
  description: null,
}

const organization = {
  id: '1',
  code: 'E2E-GRP',
  name: 'E2E Group',
  type: 'GROUP',
  status: 'ACTIVE',
}
const uoms = [
  { id: '20', code: 'PCS', name: 'Piece', status: 'ACTIVE' },
  { id: '21', code: 'BOX', name: 'Box', status: 'ACTIVE' },
]

/**
 * Deterministic browser contract fixture ONLY. It does not simulate or prove PBAC.
 * See e2e/live for real backend authorization negative tests.
 */
export async function mockLookupBatch(
  page: Page,
  permissions: string[] = [
    'product.view',
    'product.edit',
    'product.create',
    'product_uom.view',
    'price_list.view',
    'price_list.edit',
    'price_list.create',
    'organization.view',
  ],
  config: { legacyItem?: boolean } = {},
) {
  const calls: ApiCall[] = []
  const unexpected: string[] = []
  let itemRows =
    config.legacyItem === false
      ? []
      : [{ id: '71', price_list_id: '70', product_id: '30', uom_id: '99', price: '125000.00' }]
  let submittedItems: unknown = null
  await page.route('**/api/v1/**', async (route: Route) => {
    const request = route.request()
    const url = new URL(request.url())
    const path = url.pathname.replace(/\/$/, '')
    const method = request.method()
    const body = request.postDataJSON() as unknown
    // CORS preflight is a transport detail, not a second business request.
    const headers = {
      'Access-Control-Allow-Origin': request.headers()['origin'] ?? 'http://localhost:5173',
      'Access-Control-Allow-Credentials': 'true',
      'Access-Control-Allow-Methods': 'GET,POST,PUT,PATCH,OPTIONS',
      'Access-Control-Allow-Headers':
        'Accept,Accept-Language,Content-Type,X-Request-ID,X-CSRF-TOKEN',
    }
    if (method === 'OPTIONS') {
      await route.fulfill({ status: 204, headers })
      return
    }
    calls.push({ method, path, query: url.searchParams, body })
    const respond = async (data: unknown, meta?: Record<string, number>, status = 200) =>
      route.fulfill({
        status,
        headers,
        contentType: 'application/json',
        body: JSON.stringify({ data, ...(meta ? { meta } : {}) }),
      })
    const list = async (rows: unknown[], pageSize = 200) =>
      respond(rows, {
        page: Number(url.searchParams.get('page') || 1),
        page_size: Number(url.searchParams.get('page_size') || pageSize),
        total: rows.length,
      })
    if (path === '/api/v1/auth/csrf' && method === 'GET') {
      await route.fulfill({
        status: 200,
        headers: { ...headers, 'X-CSRF-TOKEN': 'e2e-csrf' },
        contentType: 'application/json',
        body: JSON.stringify({ data: { csrf_token: 'e2e-csrf', header_name: 'X-CSRF-TOKEN' } }),
      })
    } else if (path === '/api/v1/auth/me' && method === 'GET') {
      await respond({
        user: {
          id: 7,
          username: 'e2e-viewer',
          email: 'e2e@example.test',
          active: true,
          status: 'ACTIVE',
          groups: [],
          base_permissions: permissions,
        },
      })
    } else if (path === '/api/v1/products' && method === 'GET') {
      await list([parent, combo, simple], 15)
    } else if (path === '/api/v1/product-groups' && method === 'GET') {
      await list([{ id: '10', code: 'E2E-GROUP', name: 'Test group', status: 'ACTIVE' }])
    } else if (path === '/api/v1/product-uoms' && method === 'GET') {
      await list(uoms)
    } else if (path === '/api/v1/products/relations-preview' && method === 'POST') {
      await respond([
        { product_id: '31', total: 3, products: [simple, child] },
        { product_id: '32', total: 3, products: [simple, child] },
      ])
    } else if (path === '/api/v1/products/30/uom-conversions' && method === 'GET') {
      await respond([
        {
          id: '90',
          product_id: '30',
          uom_id: '21',
          conversion_factor: '12.0000',
          usage_type: 'SALES',
          status: 'ACTIVE',
        },
      ])
    } else if (path === '/api/v1/products/32/combo-items' && method === 'GET') {
      await respond([
        { id: '81', combo_product_id: '32', component_product_id: '30', quantity: '1.000000' },
        { id: '82', combo_product_id: '32', component_product_id: '33', quantity: '2.000000' },
      ])
    } else if (path === '/api/v1/products/details/resolve' && method === 'POST') {
      const ids = (body as { product_ids: string[] }).product_ids
      await respond([simple, child, parent, combo].filter((row) => ids.includes(row.id)))
    } else if (path === '/api/v1/products/options' && method === 'GET') {
      const q = (url.searchParams.get('q') ?? '').toLowerCase()
      const purpose = url.searchParams.get('purpose')
      const candidates = purpose === 'parent_product' ? [parent, simple] : [simple, child, combo]
      const filtered = candidates.filter((row) =>
        `${row.product_code} ${row.full_name}`.toLowerCase().includes(q),
      )
      const requestedPage = Number(url.searchParams.get('page') || 1)
      const size = Number(url.searchParams.get('page_size') || 20)
      await respond(filtered.slice((requestedPage - 1) * size, requestedPage * size), {
        page: requestedPage,
        page_size: size,
        total: filtered.length,
      })
    } else if (path === '/api/v1/products/options/resolve' && method === 'POST') {
      const ids = (body as { product_ids: string[] }).product_ids
      await respond([simple, child, combo].filter((row) => ids.includes(row.id)))
    } else if (path === '/api/v1/products/sales-uom-options/resolve' && method === 'POST') {
      const ids = (body as { product_ids: string[] }).product_ids
      await respond(
        ids.map((id) => ({
          product_id: id,
          options:
            id === '32'
              ? [{ id: '20', code: 'PCS' }]
              : [
                  { id: '20', code: 'PCS' },
                  { id: '21', code: 'BOX' },
                ],
        })),
      )
    } else if (path === '/api/v1/product-uoms/labels/resolve' && method === 'POST') {
      const ids = (body as { uom_ids: string[] }).uom_ids
      await respond(ids.includes('99') ? [{ id: '99', code: 'OLD-UOM', status: 'INACTIVE' }] : [])
    } else if (path === '/api/v1/organizations/labels/resolve' && method === 'POST') {
      await respond([organization])
    } else if (path === '/api/v1/organizations' && method === 'GET') {
      await list([organization])
    } else if (path === '/api/v1/price-lists' && method === 'GET') {
      await list([priceList], 15)
    } else if (path === '/api/v1/price-lists/70/items' && method === 'GET') {
      await respond(itemRows)
    } else if (path === '/api/v1/price-lists/70/items' && method === 'PUT') {
      submittedItems = body
      const incoming = Array.isArray(body)
        ? body
        : (body as { items: Array<{ product_id: string; uom_id: string; price: string }> }).items
      itemRows = incoming.map((item, index) => ({
        id: String(71 + index),
        price_list_id: '70',
        product_id: item.product_id,
        uom_id: item.uom_id,
        price: item.price,
      }))
      await respond([])
    } else {
      // The shell may load unrelated settings; never hit a live backend.
      // Reject unrecognized Product/Pricing routes so test cannot silently pass.
      if (/^\/api\/v1\/(products|product-uoms|price-lists|organizations)(?:\/|$)/.test(path)) {
        unexpected.push(`${method} ${path}`)
        await respond({ error: 'UNMOCKED_PRODUCT_PRICING_API' }, undefined, 501)
      } else {
        await respond([])
      }
    }
  })
  return {
    calls,
    unexpected,
    get submittedItems() {
      return submittedItems
    },
  }
}
