<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { getNotificationOperationsDiagnostics, type NotificationOperationsDiagnostics,
  type NotificationOperationsDiagnosticsQueue, type OperationsErrorCategory } from '@/services/notificationOperations'

const { t } = useI18n()
const auth = useAuthStore()
const snapshot = ref<NotificationOperationsDiagnostics | null>(null)
const loading = ref(true)
const error = ref(false)
let requestNumber = 0
let controller: AbortController | null = null
const queueNames = ['outbox', 'email', 'push'] as const
const counters = [
  'ready', 'retry_ready', 'retry_delayed', 'dead', 'expired_leases',
  'overdue_ready_15m', 'overdue_ready_60m', 'oldest_ready_minutes',
] as const satisfies readonly (keyof NotificationOperationsDiagnosticsQueue)[]
const categories = ['TRANSPORT', 'LEASE_EXHAUSTED', 'POLICY', 'IDENTITY', 'OTHER', 'UNSPECIFIED'] as const satisfies readonly OperationsErrorCategory[]

async function load(): Promise<void> {
  const nextNumber = ++requestNumber
  controller?.abort()
  const next = new AbortController()
  controller = next
  const id = String(auth.user?.id ?? '')
  const session = auth.getSessionGeneration()
  snapshot.value = null
  error.value = false
  loading.value = true
  try {
    const data = await getNotificationOperationsDiagnostics(next.signal)
    if (nextNumber === requestNumber && !next.signal.aborted && id === String(auth.user?.id ?? '')
      && session === auth.getSessionGeneration()) snapshot.value = data
  } catch {
    if (nextNumber === requestNumber && !next.signal.aborted && id === String(auth.user?.id ?? '')
      && session === auth.getSessionGeneration()) error.value = true
  } finally {
    if (nextNumber === requestNumber) { loading.value = false; controller = null }
  }
}
onMounted(() => { void load() })
onBeforeUnmount(() => { ++requestNumber; controller?.abort() })
</script>

<template>
  <section class="m11-diagnostics" data-testid="ops-diagnostics" :aria-label="t('notificationOperations.diagnostics.title')">
    <h2>{{ t('notificationOperations.diagnostics.title') }}</h2>
    <p>{{ t('notificationOperations.diagnostics.description') }}</p>
    <p v-if="loading" role="status" aria-busy="true">{{ t('notificationOperations.loading') }}</p>
    <p v-else-if="error" role="status" data-testid="ops-diagnostics-error">{{ t('notificationOperations.diagnostics.loadError') }}</p>
    <template v-else-if="snapshot">
      <p class="m11-diagnostics-asof">{{ t('notificationOperations.asOf') }}:
        <time :datetime="snapshot.as_of_utc">{{ snapshot.as_of_utc }}</time>
      </p>
      <div class="m11-diagnostics-grid">
        <section v-for="key in queueNames" :key="key" class="m11-diagnostics-card" :data-testid="`ops-diagnostic-${key}`">
          <h3>{{ t(`notificationOperations.queues.${key}`) }}</h3>
          <p v-if="!snapshot.queues[key].available" role="status">{{ t('notificationOperations.unavailable') }}</p>
          <template v-else>
            <p v-if="snapshot.queues[key].findings.length === 0" class="m11-diagnostics-disclaimer">
              {{ t('notificationOperations.diagnostics.noSignals') }}
            </p>
            <ul v-else class="m11-diagnostics-findings">
              <li v-for="finding in snapshot.queues[key].findings" :key="finding">
                {{ t(`notificationOperations.diagnostics.findings.${finding}`) }}
              </li>
            </ul>
            <dl class="m11-diagnostics-counters">
              <div v-for="counter in counters" :key="counter">
                <dt>{{ t(`notificationOperations.diagnostics.counters.${counter}`) }}</dt>
                <dd>{{ snapshot.queues[key][counter] ?? t('notificationOperations.diagnostics.none') }}</dd>
              </div>
            </dl>
            <details>
              <summary>{{ t('notificationOperations.diagnostics.categoriesTitle') }}</summary>
              <p>{{ t('notificationOperations.diagnostics.categoriesNote') }}</p>
              <dl class="m11-diagnostics-counters">
                <div v-for="category in categories" :key="category">
                  <dt>{{ t(`notificationOperations.diagnostics.categories.${category}`) }}</dt>
                  <dd>{{ snapshot.queues[key].error_categories[category] ?? 0 }}</dd>
                </div>
              </dl>
            </details>
          </template>
        </section>
      </div>
      <p class="m11-diagnostics-disclaimer">{{ t('notificationOperations.diagnostics.cronDisclaimer') }}</p>
    </template>
  </section>
</template>

<style scoped>
.m11-diagnostics { margin: 20px 0 14px; }
.m11-diagnostics h2 { font-size: 1.1rem; }
.m11-diagnostics h3 { font-size: 1rem; margin: 0 0 12px; }
.m11-diagnostics p { line-height: 1.5; }
.m11-diagnostics-asof,.m11-diagnostics-disclaimer { opacity: .8; }
.m11-diagnostics-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 14px; margin-top: 12px; }
.m11-diagnostics-card { border: 1px solid var(--n-border-color,var(--ds-border-subtle)); border-radius: 10px; padding: 18px; min-width: 0; }
.m11-diagnostics-findings { padding-left: 20px; font-weight: 600; }
.m11-diagnostics-counters { display: grid; gap: 6px; }
.m11-diagnostics-counters > div { display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--n-divider-color,var(--ds-border-subtle)); }
.m11-diagnostics-counters dt { overflow-wrap: anywhere; }
.m11-diagnostics-counters dd { font-variant-numeric: tabular-nums; font-weight: 650; margin: 0; }
.m11-diagnostics summary { cursor: pointer; margin-top: 16px; }
@media(max-width:820px) { .m11-diagnostics-grid { grid-template-columns: 1fr; } }
</style>
