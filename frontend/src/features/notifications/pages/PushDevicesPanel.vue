<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  classifyPushRegistrationFailure, isWindowsPushEndpoint, UnsupportedWindowsPushProvider,
  type PushRegistrationStage,
} from '@/services/notificationPushDiagnostics'
import {
  decodeVapidPublicKey, getPushConfig, getPushDevices,
  registerPushSubscription, revokePushDevice,
  type PushConfig, type PushDevice,
} from '@/services/notificationPush'

const props = defineProps<{ consented: boolean }>()
const { t } = useI18n()
const auth = useAuthStore()
const available = ref<PushConfig | null>(null)
const devices = ref<PushDevice[]>([])
const busy = ref(false)
const error = ref('')
const notice = ref('')
const currentId = ref<string | null>(null)
const supported = computed(() => typeof window !== 'undefined' && window.isSecureContext
  && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window)
const canSubscribe = computed(() => supported.value && available.value?.available && props.consented)

async function refresh(): Promise<void> {
  const generation = auth.getSessionGeneration()
  try {
    const [config, list] = await Promise.all([getPushConfig(), getPushDevices()])
    if (generation !== auth.getSessionGeneration()) return
    available.value = config
    devices.value = list
  } catch {
    if (generation === auth.getSessionGeneration()) error.value = t('pushDevices.loadError')
  }
}

async function subscribe(): Promise<void> {
  if (!canSubscribe.value || busy.value) return
  // Permission MUST originate from this explicit user gesture, not from page load.
  busy.value = true
  error.value = ''
  notice.value = ''
  const generation = auth.getSessionGeneration()
  let stage: PushRegistrationStage = 'permission'
  try {
    const permission = await Notification.requestPermission()
    if (permission !== 'granted') throw new Error('PUSH_PERMISSION_DENIED')
    stage = 'service-worker'
    const worker = await navigator.serviceWorker.register('/notification-sw.js', { scope: '/' })
    const ready = await navigator.serviceWorker.ready
    if (worker.scope !== ready.scope) throw new Error('PUSH_SERVICE_WORKER_SCOPE_MISMATCH')
    stage = 'vapid'
    const key = available.value?.public_key
    if (!key) throw new Error('NO_VAPID_KEY')
    const keyBytes = decodeVapidPublicKey(key)
    stage = 'browser-subscription'
    let existing = await ready.pushManager.getSubscription()
    // VAPID key rotation invalidates an old browser subscription. Explicitly renew
    // on this user gesture instead of silently re-registering the obsolete endpoint.
    if (existing) {
      const oldKey = existing.options.applicationServerKey
      const bytes = oldKey ? new Uint8Array(oldKey) : null
      if (!bytes || bytes.length !== keyBytes.length
        || bytes.some((value, index) => value !== keyBytes[index])) {
        await existing.unsubscribe()
        existing = null
      }
    }
    const subscription = existing ?? await ready.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: keyBytes as BufferSource,
    })
    // WNS/Edge subscriptions cannot be delivered by our zero-payload Web Push
    // sender. Do not store bearer endpoints we cannot deliver to; use Chrome/Firefox
    // for this E2E gate instead. Never show the actual endpoint or its token.
    if (isWindowsPushEndpoint(subscription.endpoint)) throw new UnsupportedWindowsPushProvider()
    // A session change must not bind an endpoint to another signed-in User.
    if (generation !== auth.getSessionGeneration()) return
    stage = 'server'
    const device = await registerPushSubscription(subscription)
    if (generation !== auth.getSessionGeneration()) return
    currentId.value = device.id
    await refresh()
    notice.value = t('pushDevices.saved')
  } catch (cause) {
    if (generation === auth.getSessionGeneration()) {
      const issue = classifyPushRegistrationFailure(stage, cause, Notification.permission)
      error.value = t(`pushDevices.diagnostics.${issue}`)
    }
  } finally {
    busy.value = false
  }
}

async function remove(device: PushDevice): Promise<void> {
  if (busy.value) return
  busy.value = true
  error.value = ''
  notice.value = ''
  const generation = auth.getSessionGeneration()
  try {
    await revokePushDevice(device.id)
    if (generation !== auth.getSessionGeneration()) return
    if (device.id === currentId.value && supported.value) {
      const registration = await navigator.serviceWorker.getRegistration('/notification-sw.js')
      await (await registration?.pushManager.getSubscription())?.unsubscribe()
      currentId.value = null
    }
    await refresh()
    notice.value = t('pushDevices.removed')
  } catch {
    if (generation === auth.getSessionGeneration()) error.value = t('pushDevices.removeError')
  } finally {
    busy.value = false
  }
}

onMounted(() => { void refresh() })
</script>

<template>
  <section class="push-devices">
    <h2>{{ t('pushDevices.title') }}</h2>
    <p>{{ t('pushDevices.description') }}</p>
    <p v-if="!supported">{{ t('pushDevices.unsupported') }}</p>
    <p v-else-if="!available?.available">{{ t('pushDevices.disabled') }}</p>
    <p v-else-if="!consented">{{ t('pushDevices.optInFirst') }}</p>
    <button type="button" :disabled="!canSubscribe || busy" @click="subscribe">
      {{ t('pushDevices.subscribe') }}
    </button>
    <p v-if="error" role="alert">{{ error }}</p>
    <p v-if="notice" role="status">{{ notice }}</p>
    <ul>
      <li v-for="device in devices" :key="device.id">
        <span>{{ t('pushDevices.device') }} #{{ device.id }} ({{ device.created_at }})</span>
        <button type="button" :disabled="busy" @click="remove(device)">
          {{ t('pushDevices.remove') }}
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.push-devices { border-top: 1px solid #8297a944; padding: 20px 0; display: grid; gap: 12px; }
.push-devices h2 { font-size: 1.1rem; margin: 0; }
.push-devices p { margin: 0; }
.push-devices ul { display: grid; gap: 8px; list-style: none; padding: 0; }
.push-devices li { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; }
.push-devices button { padding: 6px 12px; }
</style>
