/**
 * M11.3C: user-facing registration diagnostics. Never include the subscription
 * URL, its bearer token, the VAPID key, or raw exception/server messages.
 */
export type PushRegistrationStage = 'permission' | 'service-worker' | 'vapid' | 'browser-subscription' | 'server'
export type PushRegistrationIssue =
  | 'permissionDenied'
  | 'permissionDismissed'
  | 'serviceWorkerFailed'
  | 'vapidFailed'
  | 'browserSubscriptionFailed'
  | 'windowsProviderUnsupported'
  | 'serverConsentRequired'
  | 'serverDisabled'
  | 'serverUnauthenticated'
  | 'serverRejected'
  | 'serverUnavailable'
  | 'serverNetworkFailed'

export class UnsupportedWindowsPushProvider extends Error {
  constructor() {
    super('PUSH_PROVIDER_WNS_UNSUPPORTED')
    this.name = 'UnsupportedWindowsPushProvider'
  }
}

/** Windows Edge can issue WNS endpoints; the current zero-payload sender does not support them. */
export function isWindowsPushEndpoint(endpoint: string): boolean {
  try {
    const host = new URL(endpoint).hostname.toLowerCase()
    return host.endsWith('.notify.windows.com') && host !== '.notify.windows.com'
  } catch {
    return false
  }
}

export function classifyPushRegistrationFailure(
  stage: PushRegistrationStage,
  cause: unknown,
  permission?: NotificationPermission,
): PushRegistrationIssue {
  if (cause instanceof UnsupportedWindowsPushProvider) return 'windowsProviderUnsupported'
  if (stage === 'permission') {
    return permission === 'denied' ? 'permissionDenied' : 'permissionDismissed'
  }
  if (stage === 'service-worker') return 'serviceWorkerFailed'
  if (stage === 'vapid') return 'vapidFailed'
  if (stage === 'browser-subscription') {
    const name = (cause as { name?: unknown } | null)?.name
    if (name === 'NotAllowedError' || name === 'PermissionDeniedError') return 'permissionDenied'
    return 'browserSubscriptionFailed'
  }

  // The API's stable error code and HTTP status may be inspected, but its
  // message, raw exception and subscription URL must never appear in the UI.
  const response = (cause as { response?: {
    status?: unknown
    data?: { error?: { code?: unknown } }
  } } | null)?.response
  const status = typeof response?.status === 'number' ? response.status : 0
  const code = response?.data?.error?.code
  if (code === 'NOTIFICATION_PUSH_CONSENT_REQUIRED') return 'serverConsentRequired'
  if (code === 'NOTIFICATION_PUSH_DISABLED') return 'serverDisabled'
  if (status === 401 || status === 419) return 'serverUnauthenticated'
  if (status === 403 || status === 400 || status === 409 || status === 422) return 'serverRejected'
  return status >= 500 ? 'serverUnavailable' : 'serverNetworkFailed'
}
