<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useBasePermission } from '@/composables/useBasePermission'
import { getNotificationOperationsOverview, type NotificationOperationsOverview } from '@/services/notificationOperations'
import NotificationOperationsDiagnosticsPanel from './NotificationOperationsDiagnosticsPanel.vue'

const { t } = useI18n()
const auth = useAuthStore()
const { hasBasePermission } = useBasePermission()
const allowed = computed(() => hasBasePermission('notification_operations', 'view'))
const userId = computed(() => String(auth.user?.id ?? ''))
const overview = ref<NotificationOperationsOverview | null>(null)
const loading = ref(false)
const error = ref(false)
let generation = 0
let controller: AbortController | null = null

const queueOrder = ['outbox', 'email', 'push'] as const
const statusOrder = {
  outbox: ['PENDING', 'LEASED', 'RETRY', 'PROCESSED', 'SUPPRESSED', 'DEAD'],
  email: ['PENDING', 'LEASED', 'RETRY', 'SENT', 'CANCELLED', 'DEAD'],
  push: ['PENDING', 'LEASED', 'RETRY', 'SENT', 'CANCELLED', 'DEAD'],
} as const
const switchLabels = {
  DINKERP_NOTIFICATIONS_DELIVERY_ENABLED: 'delivery',
  DINKERP_M11_TASKS_INAPP_ENABLED: 'inappTask',
  DINKERP_M11_TASKS_EMAIL_ENABLED: 'emailTask',
  DINKERP_M11_TASKS_PUSH_ENABLED: 'pushTask',
  DINKERP_M11_EMAIL_QUEUE_ENABLED: 'emailQueue',
  DINKERP_M11_EMAIL_SEND_ENABLED: 'emailSend',
  DINKERP_M11_PUSH_QUEUE_ENABLED: 'pushQueue',
  DINKERP_M11_PUSH_SEND_ENABLED: 'pushSend',
} as const
const visibleSwitches = computed(() => overview.value?.runtime_switches.filter(
  item => Object.prototype.hasOwnProperty.call(switchLabels, item.key),
) ?? [])
function switchLabel(key: string): string {
  return t(`notificationOperations.switches.${switchLabels[key as keyof typeof switchLabels]}`)
}
function warningCount(queue: NotificationOperationsOverview['queues']['outbox']): number {
  return (queue.status_counts.DEAD ?? 0) + (queue.status_counts.RETRY ?? 0)
    + queue.expired_leases + queue.overdue_ready_15m
}

async function load(): Promise<void> {
  const request = ++generation
  controller?.abort()
  const next = new AbortController()
  controller = next
  overview.value = null
  error.value = false
  if (!allowed.value || !userId.value) {
    loading.value = false
    return
  }
  const actor = userId.value
  const session = auth.getSessionGeneration()
  loading.value = true
  try {
    const result = await getNotificationOperationsOverview(next.signal)
    if (request === generation && !next.signal.aborted && actor === userId.value
      && session === auth.getSessionGeneration() && allowed.value) {
      overview.value = result
    }
  } catch {
    if (request === generation && !next.signal.aborted && actor === userId.value
      && session === auth.getSessionGeneration()) error.value = true
  } finally {
    if (request === generation) {
      loading.value = false
      controller = null
    }
  }
}
watch([userId, allowed], () => { void load() })
onMounted(() => { void load() })
onBeforeUnmount(() => { ++generation; controller?.abort() })
</script>

<template>
  <section class="m11-operations" :aria-label="t('notificationOperations.title')">
    <header class="m11-operations-header">
      <div>
        <h1>{{ t('notificationOperations.title') }}</h1>
        <p>{{ t('notificationOperations.description') }}</p>
      </div>
      <button data-testid="ops-refresh" type="button" :disabled="loading || !allowed" @click="load">
        {{ t('notificationOperations.refresh') }}
      </button>
    </header>
    <p v-if="!allowed" role="alert">{{ t('notificationOperations.notAuthorized') }}</p>
    <p v-else-if="loading" role="status" aria-busy="true">{{ t('notificationOperations.loading') }}</p>
    <template v-else-if="error">
      <p role="alert">{{ t('notificationOperations.loadError') }}</p>
      <button type="button" @click="load">{{ t('notificationOperations.retry') }}</button>
    </template>
    <template v-else-if="overview">
      <p class="m11-operations-asof">
        {{ t('notificationOperations.asOf') }}: <time :datetime="overview.as_of_utc">{{ overview.as_of_utc }}</time>
      </p>
      <div class="m11-operations-queues">
        <section v-for="key in queueOrder" :key="key" class="m11-operations-card" :data-testid="`ops-queue-${key}`">
          <h2>{{ t(`notificationOperations.queues.${key}`) }}</h2>
          <p v-if="!overview.queues[key].available" role="status">{{ t('notificationOperations.unavailable') }}</p>
          <template v-else>
            <p v-if="warningCount(overview.queues[key]) > 0" class="m11-operations-warning" role="status">
              {{ t('notificationOperations.attention') }}
            </p>
            <dl class="m11-operations-counts">
              <div v-for="status in statusOrder[key]" :key="status">
                <dt>{{ t(`notificationOperations.statuses.${status}`) }}</dt>
                <dd>{{ overview.queues[key].status_counts[status] ?? 0 }}</dd>
              </div>
              <div>
                <dt>{{ t('notificationOperations.overdue') }}</dt>
                <dd>{{ overview.queues[key].overdue_ready_15m }}</dd>
              </div>
              <div>
                <dt>{{ t('notificationOperations.expired') }}</dt>
                <dd>{{ overview.queues[key].expired_leases }}</dd>
              </div>
            </dl>
          </template>
        </section>
      </div>
      <NotificationOperationsDiagnosticsPanel />
      <div class="m11-operations-bottom">
        <section class="m11-operations-card" data-testid="ops-cron">
          <h2>{{ t('notificationOperations.cron') }}</h2>
          <strong>{{ t('notificationOperations.unknown') }}</strong>
          <p>{{ t('notificationOperations.cronUnknown') }}</p>
          <p>{{ t('notificationOperations.cronManual') }}</p>
        </section>
        <section class="m11-operations-card" data-testid="ops-runtime">
          <h2>{{ t('notificationOperations.runtime') }}</h2>
          <p>{{ t('notificationOperations.runtimeNote') }}</p>
          <dl class="m11-operations-counts">
            <div v-for="flag in visibleSwitches" :key="flag.key">
              <dt>{{ switchLabel(flag.key) }}</dt>
              <dd>{{ t(flag.enabled ? 'notificationOperations.enabled' : 'notificationOperations.disabled') }}</dd>
            </div>
          </dl>
        </section>
      </div>
      <p class="m11-operations-footer">{{ t('notificationOperations.deliveryDisclaimer') }}</p>
    </template>
  </section>
</template>

<style scoped>
.m11-operations { width: min(100%, 1200px); margin: 0 auto; padding: 24px; }
.m11-operations-header { display: flex; justify-content: space-between; align-items: start; gap: 20px; }
.m11-operations h1 { font-size: 1.5rem; margin: 0 0 8px; }
.m11-operations h2 { font-size: 1.1rem; margin: 0 0 12px; }
.m11-operations p { line-height: 1.5; }
.m11-operations-header p, .m11-operations-asof, .m11-operations-footer { opacity: .82; }
.m11-operations button { padding: 9px 14px; border-radius: 7px; border: 1px solid currentColor; background: transparent; color: inherit; cursor: pointer; }
.m11-operations button:disabled { opacity: .5; cursor: default; }
.m11-operations-queues { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 14px; margin: 20px 0 14px; }
.m11-operations-bottom { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 14px; }
.m11-operations-card { border: 1px solid var(--n-border-color,#a0a0a040); border-radius: 10px; padding: 18px; min-width: 0; }
.m11-operations-counts { display: grid; grid-template-columns: 1fr; gap: 6px; }
.m11-operations-counts > div { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--n-divider-color,#a0a0a029); padding-bottom: 5px; }
.m11-operations-counts dt { overflow-wrap: anywhere; }
.m11-operations-counts dd { font-weight: 650; font-variant-numeric: tabular-nums; white-space: nowrap; margin: 0; }
.m11-operations-warning { font-weight: 600; }
.m11-operations-footer { margin-top: 18px; }
@media(max-width: 820px) { .m11-operations-queues { grid-template-columns: 1fr; } .m11-operations-bottom { grid-template-columns: 1fr; } }
@media(max-width: 540px) { .m11-operations { padding: 12px; } .m11-operations-header { flex-direction: column; } }
</style>
