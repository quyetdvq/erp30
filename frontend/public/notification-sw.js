/* M11 Push v2: only encrypted, server-produced, allowlisted event facts reach the lock screen.
   Malformed/legacy payloads remain generic. Never render arbitrary HTML, PII, or external URLs. */
const fallback = {
  title: 'DinkERP',
  body: 'Bạn có thông báo mới. Mở Hộp thư để xem chi tiết.',
  tag: 'dinkerp-notification',
}
const labels = {
  product: 'Sản phẩm', product_group: 'Nhóm sản phẩm', product_uom: 'Đơn vị tính',
  price_list: 'Bảng giá', customer: 'Khách hàng', customer_contact: 'Liên hệ khách hàng',
  organization: 'Tổ chức', organization_contact: 'Liên hệ tổ chức',
  employee: 'Nhân viên', employee_contact: 'Liên hệ nhân viên',
  province: 'Tỉnh/Thành phố', ward: 'Phường/Xã', department: 'Phòng ban', position: 'Chức vụ',
}
function safeState(state) { return typeof state === 'string' && /^[A-Z][A-Z0-9_:-]{0,49}$/.test(state) }
function safeId(id) { return typeof id === 'string' && /^[1-9][0-9]{0,19}$/.test(id) }
function safeCount(count) { return Number.isInteger(count) && count >= 0 && count <= 999999 }

function renderSignal(data) {
  if (!data || data.v !== 1 || !safeId(data.id)) return fallback
  const id = data.id
  const tag = `dinkerp-${data.event}-${id}`
  if (data.event === 'import.completed' || data.event === 'import.completed_with_errors' || data.event === 'import.failed') {
    if (!safeCount(data.success) || !safeCount(data.errors)) return fallback
    const title = data.event === 'import.completed' ? 'Import Product hoàn tất'
      : data.event === 'import.failed' ? 'Import Product thất bại' : 'Import Product có lỗi'
    return { title, body: `Công việc #${id}: ${data.success} dòng thành công, ${data.errors} dòng lỗi.`, tag }
  }
  if (data.event === 'workflow.action_required' && typeof data.resource === 'string'
    && /^[a-z][a-z0-9_]{0,40}$/.test(data.resource) && safeState(data.state)) {
    // No arbitrary resource/status descriptions are allowed on the lock screen.
    const label = Object.hasOwn(labels, data.resource) ? labels[data.resource] : 'Đối tượng'
    return {
      title: 'Có quy trình cần xử lý',
      body: `${label} #${id} đang ở trạng thái ${data.state}. Mở Hộp thư để xem.`, tag,
    }
  }
  return fallback
}
// This worker owns only Push/notificationclick. Activate a newly deployed
// event renderer promptly; never wait for all ERP workspace tabs to close.
self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting())
})
self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim())
})
self.addEventListener('push', (event) => {
  event.waitUntil((async () => {
    let payload = null
    try {
      if (event.data) payload = event.data.json()
    } catch { /* Bad or legacy payload: do not display arbitrary text. */ }
    const signal = renderSignal(payload)
    await self.registration.showNotification(signal.title, {
      body: signal.body,
      tag: signal.tag,
      icon: '/favicon.ico',
      data: { path: '/notifications' },
    })
    // Notify already-open tabs to refresh the bell, without exposing a payload to the page.
    const tabs = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
    for (const tab of tabs) {
      try {
        if (new URL(tab.url).origin === self.location.origin) tab.postMessage({ type: 'DINKERP_NOTIFICATIONS_CHANGED' })
      } catch { /* Ignore foreign/invalid tabs. */ }
    }
  })())
})
self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
    const sameOrigin = windows.find((client) => {
      try { return new URL(client.url).origin === self.location.origin } catch { return false }
    })
    if (sameOrigin) {
      await sameOrigin.navigate('/notifications')
      return sameOrigin.focus()
    }
    return self.clients.openWindow('/notifications')
  })())
})
