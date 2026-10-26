/* M11.3C: generic, payload-free browser Push signal. Never cache application data. */
self.addEventListener('push', (event) => {
  event.waitUntil(self.registration.showNotification('DinkERP', {
    body: 'You have a new notification. Sign in to view your Inbox.',
    tag: 'dinkerp-new-notification',
    icon: '/favicon.ico',
    data: { path: '/notifications' },
  }))
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
