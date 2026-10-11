import process from 'node:process'
import {
  expect,
  test,
  type APIRequestContext,
  type Browser,
  type BrowserContext,
  type Page,
} from '@playwright/test'

type Persona = 'ADMIN' | 'RESTRICTED' | 'PRICE_ONLY'
const REQUIRED = [
  'DINKERP_E2E_ADMIN_EMAIL',
  'DINKERP_E2E_ADMIN_PASSWORD',
  'DINKERP_E2E_RESTRICTED_EMAIL',
  'DINKERP_E2E_RESTRICTED_PASSWORD',
  'DINKERP_E2E_PRICE_ONLY_EMAIL',
  'DINKERP_E2E_PRICE_ONLY_PASSWORD',
  'DINKERP_E2E_ALLOWED_PRODUCT_ID',
  'DINKERP_E2E_DENIED_PRODUCT_ID',
  'DINKERP_E2E_PARENT_PRODUCT_ID',
  'DINKERP_E2E_ALLOWED_UOM_ID',
  'DINKERP_E2E_DENIED_UOM_ID',
  'DINKERP_E2E_ALLOWED_ORG_ID',
  'DINKERP_E2E_DENIED_ORG_ID',
  'DINKERP_E2E_ALLOWED_PRICE_LIST_ID',
  'DINKERP_E2E_DENIED_PRICE_LIST_ID',
] as const

/** No silent skip. Missing fixture/account is a failed setup, not a green security test. */
function fixture(name: (typeof REQUIRED)[number]): string {
  const value = process.env[name]?.trim()
  if (!value) throw new Error(`Missing required staging fixture variable: ${name}`)
  return value
}
function baseApi(): string {
  const origin = process.env.DINKERP_E2E_API_ORIGIN
  if (!origin) throw new Error('DINKERP_E2E_API_ORIGIN is required.')
  return origin.replace(/\/$/, '')
}
function id(name: (typeof REQUIRED)[number]): string {
  const value = fixture(name)
  if (!/^[1-9]\d*$/.test(value)) throw new Error(`${name} must be a positive BIGINT ID`)
  return value
}

type Session = { context: BrowserContext; request: APIRequestContext; csrf: string }
function updateCsrf(session: Session, responseHeaders: Record<string, string>): void {
  const next = responseHeaders['x-csrf-token']
  if (next) session.csrf = next
}
async function signIn(browser: Browser, persona: Persona): Promise<Session> {
  const context = await browser.newContext()
  try {
    const page: Page = await context.newPage()
    const apiOrigin = new URL(baseApi()).origin
    // Fail closed: accidentally built production API_BASE_URL must never receive credentials.
    await page.route('**/api/v1/**', (route) => {
      if (new URL(route.request().url()).origin !== apiOrigin) return route.abort('blockedbyclient')
      return route.continue()
    })
    await page.goto('/login')
    await page
      .locator('#login-email-input')
      .fill(fixture(`DINKERP_E2E_${persona}_EMAIL` as (typeof REQUIRED)[number]))
    await page
      .locator('#login-password-input')
      .fill(fixture(`DINKERP_E2E_${persona}_PASSWORD` as (typeof REQUIRED)[number]))
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login(?:\?|$)/)
    const request = context.request
    const who = await request.get(`${baseApi()}/api/v1/auth/me`)
    expect(who.status(), `Login failed for ${persona}`).toBe(200)
    const csrfResponse = await request.get(`${baseApi()}/api/v1/auth/csrf`)
    expect(csrfResponse.status()).toBe(200)
    const csrfBody = (await csrfResponse.json()) as { data?: { csrf_token?: string } }
    const csrf = csrfResponse.headers()['x-csrf-token'] ?? csrfBody.data?.csrf_token
    expect(csrf, `Missing CSRF token for ${persona}`).toBeTruthy()
    return { context, request, csrf: csrf! }
  } catch (error) {
    await context.close()
    throw error
  }
}
function apiUrl(path: string): string {
  return `${baseApi()}/api/v1${path}`
}
async function read(session: Session, path: string): Promise<{ status: number; data: unknown }> {
  const response = await session.request.get(apiUrl(path))
  updateCsrf(session, response.headers())
  const body = (await response.json().catch(() => null)) as { data?: unknown } | null
  return { status: response.status(), data: body?.data }
}
async function resolve(
  session: Session,
  path: string,
  body: Record<string, string[] | number>,
): Promise<{ status: number; data: unknown }> {
  const response = await session.request.post(apiUrl(path), {
    data: body,
    headers: { 'X-CSRF-TOKEN': session.csrf, Accept: 'application/json' },
  })
  updateCsrf(session, response.headers())
  const result = (await response.json().catch(() => null)) as { data?: unknown } | null
  return { status: response.status(), data: result?.data }
}
function ids(rows: unknown): string[] {
  expect(Array.isArray(rows)).toBe(true)
  return (rows as Array<{ id: string }>).map((row) => String(row.id))
}
async function withSessions(
  browser: Browser,
  personas: Persona[],
  assertSessions: (sessions: Partial<Record<Persona, Session>>) => Promise<void>,
): Promise<void> {
  const sessions: Partial<Record<Persona, Session>> = {}
  try {
    for (const persona of personas) sessions[persona] = await signIn(browser, persona)
    await assertSessions(sessions)
  } finally {
    for (const session of Object.values(sessions)) await session.context.close()
  }
}

test.beforeAll(() => {
  for (const name of REQUIRED) fixture(name)
  const domains = [
    [
      'DINKERP_E2E_ALLOWED_PRODUCT_ID',
      'DINKERP_E2E_DENIED_PRODUCT_ID',
      'DINKERP_E2E_PARENT_PRODUCT_ID',
    ],
    ['DINKERP_E2E_ALLOWED_UOM_ID', 'DINKERP_E2E_DENIED_UOM_ID'],
    ['DINKERP_E2E_ALLOWED_ORG_ID', 'DINKERP_E2E_DENIED_ORG_ID'],
    ['DINKERP_E2E_ALLOWED_PRICE_LIST_ID', 'DINKERP_E2E_DENIED_PRICE_LIST_ID'],
  ] as const
  for (const group of domains) {
    const values = group.map((key) => id(key))
    expect(new Set(values).size).toBe(values.length)
  }
})

test.describe('LIVE staging PBAC — read-only Lookup/Batch', () => {
  test('Authenticated Vue screens load Product and Price List pages through the real staging backend', async ({
    browser,
  }) => {
    const session = await signIn(browser, 'RESTRICTED')
    try {
      const page = session.context.pages()[0]!
      const productReply = page.waitForResponse(
        (response) =>
          new URL(response.url()).origin === new URL(baseApi()).origin &&
          new URL(response.url()).pathname === '/api/v1/products' &&
          response.request().method() === 'GET',
      )
      await page.goto('/products')
      expect((await productReply).status()).toBe(200)
      await expect(page.getByRole('heading', { name: 'Sản phẩm' })).toBeVisible()

      const priceReply = page.waitForResponse(
        (response) =>
          new URL(response.url()).origin === new URL(baseApi()).origin &&
          new URL(response.url()).pathname === '/api/v1/price-lists' &&
          response.request().method() === 'GET',
      )
      await page.goto('/products/price-lists')
      expect((await priceReply).status()).toBe(200)
      await expect(page.getByRole('heading', { name: 'Chính sách giá' })).toBeVisible()
    } finally {
      await session.context.close()
    }
  })

  test('Product State deny survives direct GET, options/resolve and details/resolve', async ({
    browser,
  }) => {
    const allowed = id('DINKERP_E2E_ALLOWED_PRODUCT_ID')
    const denied = id('DINKERP_E2E_DENIED_PRODUCT_ID')
    await withSessions(
      browser,
      ['ADMIN', 'RESTRICTED'],
      async ({ ADMIN: admin, RESTRICTED: viewer }) => {
        if (!admin || !viewer) throw new Error('Missing staging sessions.')
        const adminAllowed = await read(admin, `/products/${allowed}`)
        const adminDenied = await read(admin, `/products/${denied}`)
        expect(adminAllowed.status).toBe(200)
        expect(adminDenied.status).toBe(200)
        expect((adminAllowed.data as { status: string }).status).toBe('ACTIVE')
        expect((adminDenied.data as { status: string }).status).toBe('INACTIVE')
        expect((await read(viewer, `/products/${allowed}`)).status).toBe(200)
        expect((await read(viewer, `/products/${denied}`)).status).toBe(404)
        for (const path of ['/products/options/resolve', '/products/details/resolve']) {
          const result = await resolve(viewer, path, { product_ids: [allowed, denied] })
          expect(result.status).toBe(200)
          expect(ids(result.data)).toEqual([allowed])
        }
        const uom = await resolve(viewer, '/products/sales-uom-options/resolve', {
          product_ids: [allowed, denied],
        })
        expect(uom.status).toBe(200)
        expect((uom.data as Array<{ product_id: string }>).map((row) => row.product_id)).toEqual([
          allowed,
        ])
      },
    )
  })

  test('Relation preview counts only products visible under PBAC, not denied children', async ({
    browser,
  }) => {
    const owner = id('DINKERP_E2E_PARENT_PRODUCT_ID')
    const denied = id('DINKERP_E2E_DENIED_PRODUCT_ID')
    await withSessions(
      browser,
      ['ADMIN', 'RESTRICTED'],
      async ({ ADMIN: admin, RESTRICTED: viewer }) => {
        if (!admin || !viewer) throw new Error('Missing staging sessions.')
        expect((await read(viewer, `/products/${owner}`)).status).toBe(200)
        const deniedProduct = await read(admin, `/products/${denied}`)
        expect(deniedProduct.status).toBe(200)
        expect(
          String((deniedProduct.data as { parent_product_id: string }).parent_product_id),
        ).toBe(owner)
        const payload = { product_ids: [owner], limit_per_product: 5 }
        const adminResult = await resolve(admin, '/products/relations-preview', payload)
        const restrictedResult = await resolve(viewer, '/products/relations-preview', payload)
        expect(adminResult.status).toBe(200)
        expect(restrictedResult.status).toBe(200)
        const adminRow = (adminResult.data as Array<{ product_id: string; total: number }>).find(
          (row) => row.product_id === owner,
        )
        const viewerRow = (
          restrictedResult.data as Array<{
            product_id: string
            total: number
            products: Array<{ id: string }>
          }>
        ).find((row) => row.product_id === owner)
        expect(adminRow).toBeDefined()
        expect(viewerRow).toBeDefined()
        expect(viewerRow!.total).toBeLessThan(adminRow!.total)
        expect(viewerRow!.products.map((row) => row.id)).not.toContain(denied)
      },
    )
  })

  test('Organization Scope isolates labels and Price List targets independently of Product view', async ({
    browser,
  }) => {
    const visibleOrg = id('DINKERP_E2E_ALLOWED_ORG_ID')
    const deniedOrg = id('DINKERP_E2E_DENIED_ORG_ID')
    const visiblePriceList = id('DINKERP_E2E_ALLOWED_PRICE_LIST_ID')
    const deniedPriceList = id('DINKERP_E2E_DENIED_PRICE_LIST_ID')
    await withSessions(
      browser,
      ['ADMIN', 'RESTRICTED', 'PRICE_ONLY'],
      async ({ ADMIN: admin, RESTRICTED: viewer, PRICE_ONLY: priceOnly }) => {
        if (!admin || !viewer || !priceOnly) throw new Error('Missing staging sessions.')
        for (const path of [`/organizations/${deniedOrg}`, `/price-lists/${deniedPriceList}`]) {
          expect((await read(admin, path)).status).toBe(200)
          expect((await read(viewer, path)).status).toBe(404)
        }
        expect((await read(viewer, `/organizations/${visibleOrg}`)).status).toBe(200)
        expect((await read(viewer, `/price-lists/${visiblePriceList}`)).status).toBe(200)
        expect((await read(viewer, `/price-lists/${deniedPriceList}/items`)).status).toBe(404)
        const labels = await resolve(viewer, '/organizations/labels/resolve', {
          organization_ids: [visibleOrg, deniedOrg],
        })
        expect(labels.status).toBe(200)
        expect(ids(labels.data)).toEqual([visibleOrg])
        // Price List VIEW must not implicitly grant Product VIEW.
        expect((await read(priceOnly, `/price-lists/${visiblePriceList}`)).status).toBe(200)
        const options = await resolve(priceOnly, '/products/options/resolve', {
          product_ids: [id('DINKERP_E2E_ALLOWED_PRODUCT_ID')],
        })
        expect(options.status).toBe(200)
        expect(ids(options.data)).toEqual([])
        const search = await read(
          priceOnly,
          '/products/options?purpose=price_list_item&page=1&page_size=20',
        )
        expect([403, 404]).toContain(search.status)
      },
    )
  })

  test('UOM label resolve does not expose UOM outside view permission', async ({ browser }) => {
    const allowed = id('DINKERP_E2E_ALLOWED_UOM_ID')
    const denied = id('DINKERP_E2E_DENIED_UOM_ID')
    await withSessions(
      browser,
      ['ADMIN', 'RESTRICTED'],
      async ({ ADMIN: admin, RESTRICTED: viewer }) => {
        if (!admin || !viewer) throw new Error('Missing staging sessions.')
        expect((await read(admin, `/product-uoms/${denied}`)).status).toBe(200)
        expect((await read(viewer, `/product-uoms/${allowed}`)).status).toBe(200)
        expect((await read(viewer, `/product-uoms/${denied}`)).status).toBe(404)
        const labels = await resolve(viewer, '/product-uoms/labels/resolve', {
          uom_ids: [allowed, denied],
        })
        expect(labels.status).toBe(200)
        expect(ids(labels.data)).toEqual([allowed])
      },
    )
  })
})
