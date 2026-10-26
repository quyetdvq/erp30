import assert from 'node:assert/strict'
import { test } from 'node:test'
import {
  isWindowsPushEndpoint, UnsupportedWindowsPushProvider, classifyPushRegistrationFailure,
} from '../src/services/notificationPushDiagnostics.ts'

test('Windows WNS is detected only by anchored hostname and never by token or path', () => {
  assert.equal(isWindowsPushEndpoint('https://wns2-ln2p.notify.windows.com/w/?token=SECRET'), true)
  assert.equal(isWindowsPushEndpoint('https://fcm.googleapis.com/fcm/send/abc'), false)
  assert.equal(isWindowsPushEndpoint('https://wns2.notify.windows.com.evil.test/w/?token=abc'), false)
  assert.equal(isWindowsPushEndpoint('garbage'), false)
})

test('registration errors identify the safe stage without showing raw exceptions', () => {
  assert.equal(classifyPushRegistrationFailure('permission', Error('do not print'), 'denied'), 'permissionDenied')
  assert.equal(classifyPushRegistrationFailure('permission', Error('do not print'), 'default'), 'permissionDismissed')
  assert.equal(classifyPushRegistrationFailure('service-worker', Error('secret URL')), 'serviceWorkerFailed')
  assert.equal(classifyPushRegistrationFailure('vapid', Error('private key')), 'vapidFailed')
  assert.equal(classifyPushRegistrationFailure('browser-subscription', Error('token')), 'browserSubscriptionFailed')
  assert.equal(classifyPushRegistrationFailure('browser-subscription', { name: 'NotAllowedError' }), 'permissionDenied')
  assert.equal(classifyPushRegistrationFailure('browser-subscription', new UnsupportedWindowsPushProvider()), 'windowsProviderUnsupported')
})

test('API errors are bounded to safe consent, availability, and HTTP classes', () => {
  assert.equal(classifyPushRegistrationFailure('server', { response: { status: 403, data: {
    error: { code: 'NOTIFICATION_PUSH_CONSENT_REQUIRED', message: 'secret' },
  } } }), 'serverConsentRequired')
  assert.equal(classifyPushRegistrationFailure('server', { response: { status: 403, data: {
    error: { code: 'NOTIFICATION_PUSH_DISABLED' },
  } } }), 'serverDisabled')
  assert.equal(classifyPushRegistrationFailure('server', { response: { status: 401 } }), 'serverUnauthenticated')
  assert.equal(classifyPushRegistrationFailure('server', { response: { status: 422 } }), 'serverRejected')
  assert.equal(classifyPushRegistrationFailure('server', { response: { status: 500 } }), 'serverUnavailable')
  assert.equal(classifyPushRegistrationFailure('server', Error('unsafe server url')), 'serverNetworkFailed')
})
