import { expect, test, type Page, type Request } from '@playwright/test'

/** Mocked HTTP transport: no database or persistent account mutations. */
type SessionUser = {
  id: number
  username: string
  name: string | null
  has_avatar: boolean
  avatar_url?: string | null
  email: string
  active: boolean
  status: string
  groups: string[]
  base_permissions: string[]
}

async function mockProfileSession(page: Page): Promise<{ user: SessionUser }> {
  const session: { user: SessionUser } = {
    user: {
      id: 7,
      username: 'legacy',
      name: null,
      has_avatar: false,
      avatar_url: null,
      email: 'legacy@example.test',
      active: true,
      status: 'ACTIVE',
      groups: ['orgadmin'],
      base_permissions: ['user.view'],
    },
  }
  await page.route('**/api/v1/auth/csrf', (route) =>
    route.fulfill({
      status: 200,
      headers: { 'X-CSRF-TOKEN': 'csrf-profile-avatar' },
      contentType: 'application/json',
      body: JSON.stringify({ data: { csrf_token: 'csrf-profile-avatar', header_name: 'X-CSRF-TOKEN' }, meta: {} }),
    }),
  )
  await page.route('**/api/v1/auth/me', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { user: session.user }, meta: {} }),
    }),
  )
  return session
}

/** Validate the intercepted multipart upload without conditional test assertions. */
function validateAvatarUploadRequest(request: Request): string {
  expect(request.headers()['content-type']).toContain('multipart/form-data')
  const payload = request.postDataBuffer()?.toString('latin1') ?? ''
  expect(payload).toContain('name="image"; filename="photo.png"')
  expect(payload).not.toContain('name="_method"')
  return payload
}

test.describe('User v1.2 private profile and avatar regression', () => {
  test('legacy NULL name can be updated without changing groups or permissions', async ({ page }) => {
    const session = await mockProfileSession(page)
    let submitted: Record<string, unknown> | null = null
    await page.route('**/api/v1/auth/profile', async (route) => {
      if (route.request().method() !== 'PATCH') return route.continue()
      submitted = route.request().postDataJSON() as Record<string, unknown>
      session.user.name = String(submitted.name)
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { user: session.user }, meta: {} }),
      })
    })

    await page.goto('/profile')
    await expect(page.locator('.header-user-label')).toHaveText('legacy')
    await page.getByLabel('Họ và tên').fill('  Lê Thị Mai  ')
    await page.getByRole('button', { name: 'Lưu thay đổi' }).click()

    await expect(page.getByText('Đã cập nhật profile.')).toBeVisible()
    await expect(page.locator('.header-user-label')).toHaveText('Lê Thị Mai')
    expect(submitted).toMatchObject({ name: 'Lê Thị Mai', username: 'legacy', email: 'legacy@example.test' })
    expect(submitted).not.toHaveProperty('group_ids')
    expect(submitted).not.toHaveProperty('base_permissions')
    expect(session.user.groups).toEqual(['orgadmin'])
    expect(session.user.base_permissions).toEqual(['user.view'])
  })

  test('owner uploads, displays static avatar, and deletes without supplying a storage filename', async ({ page }) => {
    const session = await mockProfileSession(page)
    // Use a CRC-valid 2x2 RGB PNG. Firefox rejects malformed IDAT chunk CRCs.
    const png = Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR42mNULVzNwMDAxMDAwMDAAAAOsAFF/HvYqAAAAABJRU5ErkJggg==',
      'base64',
    )
    // The mutation endpoint is authenticated; image bytes are served through the
    // validated, public static URL returned in avatar_url (not an API GET).
    const avatarUrl = '/uploads/2026/20261023avatar5689523.png'
    let uploaded = 0
    let staticImageReads = 0
    let legacyApiImageReads = 0
    let deleted = 0
    let multipartPayload = ''
    await page.route(`**${avatarUrl}`, async (route) => {
      staticImageReads++
      expect(route.request().method()).toBe('GET')
      await route.fulfill({ status: 200, contentType: 'image/png', body: png })
    })
    await page.route('**/api/v1/users/7/avatar', async (route) => {
      const method = route.request().method()
      if (method === 'POST') {
        uploaded++
        multipartPayload = validateAvatarUploadRequest(route.request())
        session.user.has_avatar = true
        session.user.avatar_url = avatarUrl
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ data: { user_id: '7', has_avatar: true, avatar_url: avatarUrl }, meta: {} }),
        })
      }
      if (method === 'GET') {
        legacyApiImageReads++
        return route.fulfill({ status: 405 })
      }
      if (method === 'DELETE') {
        deleted++
        session.user.has_avatar = false
        session.user.avatar_url = null
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ data: { user_id: '7', has_avatar: false, avatar_url: null }, meta: {} }),
        })
      }
      return route.fulfill({ status: 405 })
    })
    await page.route('**/api/v1/auth/logout', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { ok: true }, meta: {} }) }),
    )

    await page.goto('/profile')
    const manager = page.getByRole('region', { name: 'Ảnh đại diện' })
    await manager.locator('input[type="file"]').setInputFiles({
      name: 'photo.png',
      mimeType: 'image/png',
      buffer: png,
    })
    await manager.getByRole('button', { name: 'Tải ảnh lên' }).click()
    await expect(manager.getByRole('button', { name: 'Xóa ảnh' })).toBeVisible()
    const displayedAvatar = manager.locator('.user-avatar__image')
    await expect(displayedAvatar).toBeVisible()
    expect(await displayedAvatar.getAttribute('src')).toContain(avatarUrl)
    await expect.poll(() => staticImageReads).toBeGreaterThan(0)
    await expect.poll(() => displayedAvatar.evaluate((image: HTMLImageElement) =>
      image.complete && image.naturalWidth > 0,
    )).toBe(true)
    expect(legacyApiImageReads).toBe(0)
    expect(uploaded).toBe(1)
    expect(multipartPayload).toContain('name="image"')
    expect(multipartPayload).not.toContain('avatar_filename')

    await manager.getByRole('button', { name: 'Xóa ảnh' }).click()
    await expect(manager.getByRole('button', { name: 'Xóa ảnh' })).toHaveCount(0)
    await expect(manager.locator('.user-avatar__image')).toHaveCount(0)
    expect(deleted).toBe(1)
    expect(session.user.has_avatar).toBe(false)

    await page.locator('.user-button').click()
    await page.getByText(/Đăng xuất|Sign out|Logout/, { exact: true }).click()
    await expect(page).toHaveURL(/\/login$/)
    await expect(page.locator('.user-avatar__image')).toHaveCount(0)
  })
})
