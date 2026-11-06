import { expect, test, type Page } from '@playwright/test'

/** Private user fixture. No backend write is made by these tests. */
type ProfileUser = {
  id: number
  username: string
  name: string | null
  email: string
  active: boolean
  status: string
  has_avatar: boolean
  avatar_url: null
  groups: string[]
  base_permissions: string[]
}

async function mockSession(page: Page): Promise<{ user: ProfileUser; saved: Array<Record<string, unknown>> }> {
  const session: { user: ProfileUser; saved: Array<Record<string, unknown>> } = {
    user: {
      id: 7, username: 'minhanh', name: 'Nguyễn Thị Minh Anh', email: 'minhanh@dinktech.vn',
      active: true, status: 'ACTIVE', has_avatar: false, avatar_url: null,
      groups: ['orgadmin', 'procurement'], base_permissions: ['user.view'],
    },
    saved: [],
  }
  await page.route('**/api/v1/auth/csrf', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    headers: { 'X-CSRF-TOKEN': 'profile-layout-fixture' },
    body: JSON.stringify({ data: { csrf_token: 'profile-layout-fixture', header_name: 'X-CSRF-TOKEN' }, meta: {} }),
  }))
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({
    status: 200, contentType: 'application/json', body: JSON.stringify({ data: { user: session.user }, meta: {} }),
  }))
  await page.route('**/api/v1/auth/profile', async (route) => {
    if (route.request().method() !== 'PATCH') return route.continue()
    const submitted = route.request().postDataJSON() as Record<string, unknown>
    session.saved.push(submitted)
    session.user.name = String(submitted.name ?? session.user.name)
    session.user.username = String(submitted.username)
    session.user.email = String(submitted.email)
    await route.fulfill({
      status: 200, contentType: 'application/json', body: JSON.stringify({ data: { user: session.user }, meta: {} }),
    })
  })
  return session
}

test('desktop Profile has two aligned panels and preserves account save, groups and avatar', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  const session = await mockSession(page)
  await page.goto('/profile')
  await expect(page.getByRole('heading', { name: 'Hồ sơ cá nhân' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Thông tin tài khoản' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Bảo mật đăng nhập' })).toBeVisible()
  await expect(page.getByText('orgadmin', { exact: true })).toBeVisible()

  const summary = (await page.locator('.profile-summary').boundingBox())!
  const form = (await page.locator('.form-panel').boundingBox())!
  expect(Math.abs(summary.y - form.y)).toBeLessThan(3)
  expect(form.x).toBeGreaterThan(summary.x + summary.width)
  const username = (await page.locator('#profile-username').boundingBox())!
  const name = (await page.locator('#profile-fullname').boundingBox())!
  expect(Math.abs(username.y - name.y)).toBeLessThan(4)
  expect(username.height).toBe(36)

  await page.getByLabel('Họ và tên').fill('Lê Thị Mai')
  await expect(page.getByText('Chưa lưu thay đổi')).toBeVisible()
  await page.getByRole('button', { name: 'Lưu thay đổi' }).click()
  await expect(page.getByText('Đã cập nhật profile.')).toBeVisible()
  await expect(page.locator('.profile-identity__name')).toHaveText('Lê Thị Mai')
  expect(session.saved).toHaveLength(1)
  expect(session.saved[0]).toMatchObject({ username: 'minhanh', name: 'Lê Thị Mai', email: 'minhanh@dinktech.vn' })
  expect(session.saved[0]).not.toHaveProperty('group_ids')
  expect(session.user.groups).toEqual(['orgadmin', 'procurement'])
})

test('mobile Profile stacks cards, keeps controls usable and exposes avatar file input', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 })
  await mockSession(page)
  await page.goto('/profile')
  await expect(page.getByRole('heading', { name: 'Hồ sơ cá nhân' })).toBeVisible()
  const summary = (await page.locator('.profile-summary').boundingBox())!
  const form = (await page.locator('.form-panel').boundingBox())!
  expect(form.y).toBeGreaterThan(summary.y)
  const username = (await page.locator('#profile-username').boundingBox())!
  const name = (await page.locator('#profile-fullname').boundingBox())!
  expect(name.y).toBeGreaterThan(username.y)
  expect(username.height).toBe(36)
  const file = page.getByRole('region', { name: 'Ảnh đại diện' }).locator('input[type="file"]')
  await expect(file).toBeVisible()
  await file.setInputFiles({
    name: 'avatar.png', mimeType: 'image/png',
    buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR42mNULVzNwMDAxMDAwMDAAAAOsAFF/HvYqAAAAABJRU5ErkJggg==', 'base64'),
  })
  await expect(page.locator('.avatar-manager__local')).toBeVisible()
  expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(1)
})
