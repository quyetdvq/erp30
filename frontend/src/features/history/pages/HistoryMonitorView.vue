<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ListPagination, ListTable, TableDetailModal, TableRowActions } from '@/components/lists'
import type { ListColumn } from '@/components/lists/listTypes'
import type { TableDetailField } from '@/components/lists/TableDetailModal.vue'
import { useFormatters } from '@/composables/useFormatters'
import { useBasePermission } from '@/composables/useBasePermission'
import { useAuthStore } from '@/stores/auth'
import { reportApiError } from '@/services/errorBoundary'
import { monitorBusinessHistory, type BusinessHistoryEvent } from '@/services/history'
import { historyActorLabel, historyChangeDisplay } from '@/features/history/historyPresentation'
// Canonical Customer detail, also used by /customers. No copied profile modal.
import CustomerDetailView from '@/features/customer/pages/CustomerDetailView.vue'
import SupplierDetailView from '@/features/supplier/pages/SupplierDetailView.vue'

const { t } = useI18n()
const formatters = useFormatters()
const { hasBasePermission } = useBasePermission()
const auth = useAuthStore()
const allowed = computed(() => hasBasePermission('business_history_monitor', 'view'))
const userId = computed(() => String(auth.user?.id ?? ''))
function utcDate(dayOffset = 0): string {
  const date = new Date()
  date.setUTCDate(date.getUTCDate() + dayOffset)
  return date.toISOString().slice(0, 10)
}
const from = ref(utcDate(-29))
const to = ref(utcDate())
const resourceType = ref('')
const eventType = ref('')
const actorId = ref('')
const resourceId = ref('')
const page = ref(1)
const pageSize = ref(25)
const rows = ref<BusinessHistoryEvent[]>([])
const total = ref(0)
const loading = ref(false)
const error = ref(false)
const invalidRange = ref(false)
const selected = ref<BusinessHistoryEvent | null>(null)
const selectedObject = ref<{ type: 'customer' | 'supplier'; id: string } | null>(null)
let generation = 0

const columns: readonly ListColumn<BusinessHistoryEvent>[] = [
  { key: 'created_at', labelKey: 'history.monitorTime', format: 'datetime' },
  { key: 'resource_type', labelKey: 'history.monitorObject' },
  { key: 'resource_code', labelKey: 'history.monitorIdentifier' },
  { key: 'event_type', labelKey: 'history.monitorEvent' },
  { key: 'actor_user_id', labelKey: 'history.monitorActorColumn' },
  { key: 'status_after', labelKey: 'history.monitorStatus' },
  { key: 'row_actions', labelKey: 'history.monitorDetails', align: 'end' },
  { key: 'object_actions', labelKey: 'history.monitorOpenObject', align: 'end' },
]
watch(resourceType, (nextType) => { if (!nextType) resourceId.value = '' })

const eventTypes = ['CREATE', 'UPDATE', 'STATE_CHANGE', 'SUBMIT', 'APPROVE', 'REJECT',
  'SENSITIVE_CHANGE', 'DELETE', 'RESTORE']

function datesValid(): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(from.value) || !/^\d{4}-\d{2}-\d{2}$/.test(to.value)) return false
  const first = Date.parse(from.value + 'T00:00:00Z')
  const last = Date.parse(to.value + 'T00:00:00Z')
  return Number.isFinite(first) && Number.isFinite(last) && last >= first
    && (last - first) / 86400000 < 90
    && new Date(first).toISOString().slice(0, 10) === from.value
    && new Date(last).toISOString().slice(0, 10) === to.value
}

async function load(): Promise<void> {
  const request = ++generation
  const session = auth.getSessionGeneration()
  const actor = userId.value
  rows.value = []
  total.value = 0
  selected.value = null
  selectedObject.value = null
  error.value = false
  invalidRange.value = !datesValid()
  if (!allowed.value || !actor || invalidRange.value) { loading.value = false; return }
  loading.value = true
  try {
    const result = await monitorBusinessHistory({
      from: from.value, to: to.value,
      ...(resourceType.value ? { resource_type: resourceType.value } : {}),
      ...(eventType.value ? { event_type: eventType.value } : {}),
      ...(actorId.value.trim() ? { actor_user_id: actorId.value.trim() } : {}),
      ...(resourceType.value && resourceId.value.trim()
        ? { resource_id: resourceId.value.trim() } : {}),
    }, page.value, pageSize.value)
    if (request !== generation || session !== auth.getSessionGeneration()
      || actor !== userId.value || !allowed.value) return
    rows.value = result.data
    total.value = result.meta.total
  } catch (cause) {
    if (request !== generation || session !== auth.getSessionGeneration()) return
    error.value = true
    reportApiError(cause)
  } finally {
    if (request === generation) loading.value = false
  }
}
function apply(): void { page.value = 1; void load() }
function reset(): void {
  from.value = utcDate(-29); to.value = utcDate()
  resourceType.value = ''; eventType.value = ''; actorId.value = ''; resourceId.value = ''
  apply()
}
function actorLabel(event: BusinessHistoryEvent): string {
  return historyActorLabel(event, t('history.user'), t('history.system'))
}
// Parse only the compact contract, but do not crash the entire modal when
// an outdated/malformed event slips through an older API deployment.
const selectedChanges = computed(() => selected.value
  ? historyChangeDisplay(selected.value, t('history.redactedChange'))
  : { lines: [] as readonly string[], invalid: false })
function objectLabel(event: BusinessHistoryEvent): string {
  return [event.resource_code, event.resource_name].filter(Boolean).join(' — ')
    || `${event.resource_type} #${event.resource_id}`
}
function canOpenObject(event: BusinessHistoryEvent): boolean {
  // SYSTEM monitor authority never grants Customer/Supplier master VIEW.
  // The canonical detail modal rechecks persisted row PBAC on every opening.
  return allowed.value && (event.resource_type === 'customer' || event.resource_type === 'supplier')
    && Boolean(event.resource_code) && hasBasePermission(event.resource_type, 'view')
    && /^[1-9][0-9]*$/.test(event.resource_id)
}
function openObject(event: BusinessHistoryEvent): void {
  if (!canOpenObject(event)) return
  selected.value = null
  selectedObject.value = { type: event.resource_type as 'customer' | 'supplier', id: event.resource_id }
}

// Only present the already-authorized, server-redacted event received in the
// monitor SQL page. Never fetch an unscoped event ID to fill this dialog.
const detailFields = computed<readonly TableDetailField[]>(() => {
  const event = selected.value
  if (!event || !allowed.value) return []
  const { lines, invalid } = selectedChanges.value
  return [
    { key: 'event', label: t('history.monitorEvent'), value: t(`history.events.${event.event_type}`) },
    { key: 'object', label: t('history.monitorObject'), value: `${event.resource_type} #${event.resource_id} · ${objectLabel(event)}` },
    { key: 'actor', label: t('history.monitorActorColumn'), value: actorLabel(event) },
    { key: 'time', label: t('history.monitorTime'), value: formatters.formatDateTime(event.created_at) },
    { key: 'status', label: t('history.monitorStatus'), value: `${event.status_before ?? '—'} → ${event.status_after ?? '—'}` },
    ...(event.action_code
      ? [{ key: 'action', label: t('history.monitorAction'), value: event.action_code }] : []),
    ...(event.request_id
      ? [{ key: 'request', label: t('history.monitorRequest'), value: event.request_id }] : []),
    ...(invalid ? [{ key: 'invalid-changes', label: t('history.monitorChanges'),
      value: t('history.invalidChangesFormat') }] : []),
    ...lines.map((line, index) => ({
      key: `change-${index}`, label: index === 0 ? t('history.monitorChanges') : '', value: line,
    })),
    ...(event.checkpoint
      ? [{ key: 'checkpoint', label: t('history.checkpoint'), value: t('history.monitorCheckpointRecorded') }] : []),
  ]
})
watch([allowed, userId], () => { page.value = 1; void load() }, { immediate: true })
onBeforeUnmount(() => { ++generation })
</script>

<template>
  <section class="history-monitor-page" :aria-label="t('history.monitorTitle')">
    <div class="page-heading">
      <div>
        <h1>{{ t('history.monitorTitle') }}</h1>
        <p>{{ t('history.monitorDescription') }}</p>
      </div>
    </div>
    <p v-if="!allowed" role="alert">{{ t('history.monitorRestricted') }}</p>
    <template v-else>
      <p class="history-monitor-note">{{ t('history.monitorScope') }}</p>
      <p class="history-monitor-note">{{ t('history.monitorDates') }}</p>
      <div class="ds-filter-container">
      <form class="history-monitor-filters ds-filter-form" role="search" @submit.prevent="apply">
        <div class="ds-filter-fields">
        <label>{{ t('history.monitorFrom') }}
          <input v-model="from" type="date" required /></label>
        <label>{{ t('history.monitorTo') }}
          <input v-model="to" type="date" required /></label>
        <label>{{ t('history.monitorResource') }}
          <select v-model="resourceType">
            <option value="">{{ t('list.all') }}</option>
            <option value="customer">Customer</option>
            <option value="supplier">Supplier</option>
          </select></label>
        <label>{{ t('history.monitorEvent') }}
          <select v-model="eventType">
            <option value="">{{ t('list.all') }}</option>
            <option v-for="type in eventTypes" :key="type" :value="type">{{ t(`history.events.${type}`) }}</option>
          </select></label>
        <label>{{ t('history.monitorActor') }}
          <input v-model="actorId" inputmode="numeric" pattern="[1-9][0-9]*" /></label>
        <label>{{ t('history.monitorResourceId') }}
          <input v-model="resourceId" inputmode="numeric" pattern="[1-9][0-9]*" :disabled="!resourceType" /></label>
        </div>
        <div class="history-monitor-controls ds-filter-actions">
          <button data-ds-icon="apply" type="submit" class="primary-button" :disabled="loading">{{ t('history.monitorApply') }}</button>
          <button data-ds-icon="reset" type="button" class="secondary-button" :disabled="loading" @click="reset">{{ t('history.monitorReset') }}</button>
        </div>
      </form>
      </div>
      <p v-if="invalidRange" role="alert" class="history-monitor-error">{{ t('history.monitorInvalidRange') }}</p>
      <p v-else-if="error" role="alert" class="history-monitor-error">{{ t('history.loadError') }}</p>
      <p v-if="loading" role="status">{{ t('history.loading') }}</p>
      <section class="content-panel history-monitor-results">
        <ListTable :rows="rows" :columns="columns" :busy="loading">
          <template #cell-resource_type="{ row }">{{ row.resource_type }} #{{ row.resource_id }}</template>
          <template #cell-resource_code="{ row }">
            <span v-if="row.resource_code || row.resource_name" class="history-monitor-object-identity">
              <strong v-if="row.resource_code">{{ row.resource_code }}</strong>
              <small v-if="row.resource_name">{{ row.resource_name }}</small>
            </span>
            <span v-else>—</span>
          </template>
          <template #cell-event_type="{ row }">{{ t(`history.events.${row.event_type}`) }}</template>
          <template #cell-actor_user_id="{ row }">{{ actorLabel(row) }}</template>
          <template #cell-status_after="{ row }">{{ row.status_before ?? '—' }} → {{ row.status_after ?? '—' }}</template>
          <template #cell-row_actions="{ row }">
            <TableRowActions authorization-mode="effective" :view-scope-verified="true"
              view-permission="business_history_monitor.view" :show-view="true"
              :view-label="t('history.monitorDetails')" :busy="loading"
              @view="selected = row" />
          </template>
          <template #cell-object_actions="{ row }">
            <!-- SYSTEM monitor only supplies an advisory VIEW action;
                 canonical Customer/Supplier detail still enforces row PBAC. -->
            <TableRowActions v-if="canOpenObject(row)" authorization-mode="legacy-base-only"
              :view-permission="`${row.resource_type}.view`" :show-view="true" :busy="loading"
              :view-label="t('history.monitorOpenObject')" @view="openObject(row)" />
          </template>
        </ListTable>
        <p v-if="!loading && !error && total === 0">{{ t('history.monitorNoResults') }}</p>
        <ListPagination :page="page" :page-size="pageSize" :total="total" :page-size-options="[10, 25, 50]"
          :disabled="loading" @update:page="(value) => { page = value; void load() }"
          @update:page-size="(value) => { pageSize = value; page = 1; void load() }" />
      </section>
      <!-- All detail dialogs now provide their own Naive portal. -->
      <TableDetailModal :open="allowed && selected !== null"
          :title="`${t('history.monitorDetails')} #${selected?.id ?? ''}`"
          :fields="detailFields" @close="selected = null" />
      <!-- Exact Customer modal used by /customers; source fetch is PBAC guarded. -->
        <CustomerDetailView v-if="allowed && selectedObject?.type === 'customer'"
          :key="`customer-${selectedObject.id}`" :customer-id="selectedObject.id"
          @close="selectedObject = null" @saved="void load()" />
        <SupplierDetailView v-if="allowed && selectedObject?.type === 'supplier'"
          :key="`supplier-${selectedObject.id}`" :supplier-id="selectedObject.id"
          @close="selectedObject = null" @saved="void load()" />
    </template>
  </section>
</template>

<style scoped>
.history-monitor-page { display: grid; gap: 12px; }
.history-monitor-note { margin: 0; color: var(--ds-text-secondary); font-size: 12px; }
.history-monitor-filters { padding: 16px; background: var(--ds-bg-surface); border: 1px solid var(--ds-border-subtle); border-radius: var(--ds-radius-sm); }
.history-monitor-filters label { display: grid; gap: 5px; color: var(--ds-text-secondary); font-size: 12px; }
.history-monitor-filters input, .history-monitor-filters select { min-height: var(--ds-button-height-md); min-width: 0; width: 100%; border: 1px solid var(--ds-border); border-radius: var(--ds-radius-sm); padding: 5px 8px; background: var(--ds-bg-surface); color: var(--ds-text-primary); font-size: var(--ds-font-sm); }
.history-monitor-controls { align-items: center; }
.history-monitor-error { color: var(--ds-danger); font-size: 13px; }
.history-monitor-results { display: grid; gap: 14px; padding: 16px; }
.history-monitor-object-identity { display: grid; gap: 3px; min-width: 0; }
.history-monitor-object-identity strong { font-weight: 600; overflow-wrap: anywhere; }
.history-monitor-object-identity small { color: var(--ds-text-secondary); overflow-wrap: anywhere; }
</style>
