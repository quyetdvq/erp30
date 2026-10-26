import { chromium, expect, test as base, type BrowserContext } from '@playwright/test'
import { mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

// Chrome denies Notification permission in Incognito / off-the-record profiles.
// Playwright's default { context, page } are off-the-record. Test the genuine
// browser permission with a fresh REGULAR profile, never the operator's profile.
// New-headless Chromium closely matches normal Chrome. The temporary profile
// is closed and deleted even if an assertion or browser startup fails.
const test = base.extend<{ context: BrowserContext }>({
  context: async ({ baseURL }, use) => {
    const profileDir = await mkdtemp(join(tmpdir(), 'dinkerp-m11-push-'))
    try {
      const context = await chromium.launchPersistentContext(profileDir, {
        channel: 'chromium',
        headless: process.env.M11_PUSH_BROWSER_HEADED !== '1',
        baseURL,
        serviceWorkers: 'allow',
        permissions: ['notifications'],
      })
      try {
        await use(context)
      } finally {
        await context.close()
      }
    } finally {
      await rm(profileDir, { recursive: true, force: true, maxRetries: 5, retryDelay: 300 })
    }
  },
})

// A real Chromium Service Worker + a synthetic CDP Push event. The assertion
// observes a RESOLVED native showNotification() call from the shipped worker.
// Headless Windows/Chromium can omit notifications from getNotifications() even
// when showNotification() resolves; that OS notification list is extra evidence,
// not the sole signal that the Push handler ran.
test('real Chromium Service Worker handles a content-free synthetic Push', async ({ page, context, baseURL }, testInfo) => {
  const origin = new URL(baseURL!).origin
  const workerUrl = `${origin}/notification-sw.js`
  const expected = {
    title: 'DinkERP',
    body: 'You have a new notification. Sign in to view your Inbox.',
    path: '/notifications',
  }
  await context.grantPermissions(['notifications'], { origin })
  const cdp = await context.newCDPSession(page)
  const registrations = new Map<string, string>()
  const workerErrors: string[] = []
  cdp.on('ServiceWorker.workerRegistrationUpdated', (event: {
    registrations: Array<{ registrationId: string; scopeURL: string; isDeleted: boolean }>
  }) => {
    for (const item of event.registrations) {
      if (item.isDeleted) registrations.delete(item.scopeURL)
      else registrations.set(item.scopeURL, item.registrationId)
    }
  })
  cdp.on('ServiceWorker.workerErrorReported', (event: {
    errorMessage: { errorMessage: string }
  }) => workerErrors.push(event.errorMessage.errorMessage))

  try {
    await cdp.send('ServiceWorker.enable')
    // Local Vite preview only: no DEV API calls and no live browser permissions.
    await page.goto('/login')
    const scope = await page.evaluate(async () => {
      const registration = await navigator.serviceWorker.register('/notification-sw.js', { scope: '/' })
      await navigator.serviceWorker.ready
      return registration.scope
    })
    expect(scope).toBe(`${origin}/`)
    // Check native APIs, not a JS override: browser policy must remain visible.
    const permission = await page.evaluate(async () => ({
      origin: location.origin,
      secure: isSecureContext,
      notification: Notification.permission,
      permissionsApi: (await navigator.permissions.query({ name: 'notifications' })).state,
    }))
    expect(permission.origin).toBe(origin)
    expect(permission.secure, 'Loopback must be a secure context').toBe(true)
    expect(permission.notification,
      `Chromium denied notifications for ${permission.origin} (Permissions API: ${permission.permissionsApi}). `
      + 'Check normal-profile/Windows/enterprise policy; do not stub Notification.permission.').toBe('granted')
    expect(permission.permissionsApi).toBe('granted')
    await expect.poll(() => page.evaluate(async () => {
      const registration = await navigator.serviceWorker.getRegistration('/notification-sw.js')
      return registration?.active?.state
    })).toBe('activated')

    // Playwright can evaluate in a REAL ServiceWorkerGlobalScope. Capture the
    // exact call made by the shipped handler, while still invoking the native
    // Notification API (never mock showNotification or change the shipped JS).
    const worker = context.serviceWorkers().find((item) => item.url() === workerUrl)
      ?? await context.waitForEvent('serviceworker', { predicate: (item) => item.url() === workerUrl })
    await worker.evaluate(() => {
      type Probe = {
        calls: Array<{ title: string; body: string; path: string | null }>
        errors: string[]
      }
      const sw = globalThis as unknown as {
        registration: ServiceWorkerRegistration
        __m11PushProbe?: Probe
      }
      const registration = sw.registration
      const nativeShow = registration.showNotification.bind(registration)
      const probe: Probe = { calls: [], errors: [] }
      sw.__m11PushProbe = probe
      registration.showNotification = async (title, options) => {
        try {
          await nativeShow(title, options)
          probe.calls.push({
            title,
            body: options?.body ?? '',
            path: (options?.data as { path?: string } | undefined)?.path ?? null,
          })
        } catch (error) {
          probe.errors.push(String(error))
          throw error
        }
      }
    })

    await expect.poll(() => registrations.get(scope)).toBeTruthy()
    // The CDP command returns before background delivery is finished, and it
    // does NOT report errors occurring inside the Service Worker.
    await cdp.send('ServiceWorker.deliverPushMessage', {
      origin, registrationId: registrations.get(scope)!, data: '',
    })
    try {
      await expect.poll(async () => worker.evaluate(() => {
        const sw = globalThis as unknown as {
          __m11PushProbe?: {
            calls: Array<{ title: string; body: string; path: string | null }>
            errors: string[]
          }
        }
        if (sw.__m11PushProbe?.errors.length) {
          throw new Error(`native showNotification failed: ${sw.__m11PushProbe.errors.join('; ')}`)
        }
        return sw.__m11PushProbe?.calls ?? []
      }), { timeout: 15_000, message: 'CDP Push must reach the real Worker and resolve native showNotification()' })
        .toEqual([expected])
    } catch (error) {
      throw new Error(`Synthetic Push did not complete; Worker errors: ${workerErrors.join(' | ') || 'none reported'}. ${String(error)}`)
    }

    // Optional secondary evidence. Some headless Chromium/OS combinations do
    // not retain native notifications in getNotifications(); this is NOT an OS
    // display assertion and a human device check remains required.
    const displayed = await page.evaluate(async () => {
      const registration = await navigator.serviceWorker.getRegistration('/notification-sw.js')
      return (await registration?.getNotifications() ?? []).map((n) => ({
        title: n.title, body: n.body, path: n.data?.path,
      }))
    })
    if (displayed.length > 0) expect(displayed).toEqual([expected])
    else testInfo.annotations.push({ type: 'headless-notification-list',
      description: 'getNotifications() returned []; verified native showNotification() resolved in real worker. OS display requires human DEV-device acceptance.' })
  } finally {
    // Clean up even when a real browser API or CDP assertion failed.
    await page.evaluate(async () => {
      const registration = await navigator.serviceWorker.getRegistration('/notification-sw.js')
      for (const note of await registration?.getNotifications() ?? []) note.close()
      await registration?.unregister()
    }).catch(() => {})
    await cdp.detach().catch(() => {})
  }
})
