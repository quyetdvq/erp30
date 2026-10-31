<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { NButton } from 'naive-ui'

import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { workflowDestination } from '@/services/notificationInbox'
import {
  getMyWorkflowPage, myWorkflowTypes, type MyWorkflowItem, type MyWorkflowType,
} from '@/services/myWorkflow'

const { t, locale } = useI18n()
const auth = useAuthStore()
const resourceType = ref<MyWorkflowType>('all')
const searchText = ref('')
const stateFilter = ref('all')
const items = ref<MyWorkflowItem[]>([])
const nextCursor = ref<string | null>(null)
const enabled = ref(true)
const busy = ref(false)
const error = ref(false)
const userId = computed(() => String(auth.user?.id ?? ''))
const rows = computed(() => items.value.map(item => ({
  item, destination: workflowDestination(item.resource_type, item.resource_id),
})))
const states = computed(() => [...new Set(items.value.map(item => item.current_state))].sort())
const displayedRows = computed(() => {
  const query = searchText.value.trim().toLocaleLowerCase()
  return rows.value.filter(({ item }) => {
    if (stateFilter.value !== 'all' && item.current_state !== stateFilter.value) return false
    if (!query) return true
    return [item.resource_type, item.resource_id, item.record_name, item.record_code,
      item.current_state, ...item.available_actions.flatMap(option => [option.action_code, option.status]),
      t(`myWork.resourceTypes.${item.resource_type}`),
    ].some(value => String(value ?? '').toLocaleLowerCase().includes(query))
  })
})
const availableActionCount = computed(() => items.value.reduce(
  (sum, item) => sum + item.available_actions.length, 0,
))
const kindCount = computed(() => new Set(items.value.map(item => item.resource_type)).size)

function stateLabel(state: string): string {
  const key = state.toLowerCase()
  if (['draft', 'approved', 'active', 'inactive', 'closed', 'pending', 'processing',
    'completed', 'failed', 'locked', 'suspended', 'disabled'].includes(key)) {
    return t(`status.${key}`)
  }
  return state
}
function assignedTime(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
    dateStyle: 'short', timeStyle: 'short',
  }).format(date)
}
function recordTitle(item: MyWorkflowItem): string {
  return item.record_name?.trim() || `${t(`myWork.resourceTypes.${item.resource_type}`)} #${item.resource_id}`
}

let requestSerial = 0
let activeController: AbortController | null = null
async function load(append = false): Promise<void> {
  if (append && (nextCursor.value === null || busy.value)) return
  const sequence = ++requestSerial
  activeController?.abort()
  const controller = new AbortController()
  activeController = controller
  const actor = userId.value
  const filter = resourceType.value
  const cursor = append ? nextCursor.value : null
  if (!append) { items.value = []; nextCursor.value = null; enabled.value = true }
  busy.value = true
  error.value = false
  if (!actor) {
    enabled.value = false
    busy.value = false
    activeController = null
    return
  }
  try {
    const page = await getMyWorkflowPage(filter, cursor, controller.signal)
    if (sequence !== requestSerial || controller.signal.aborted || actor !== userId.value) return
    enabled.value = page.execution_enabled
    const seen = new Set(append ? items.value.map(item => `${item.resource_type}:${item.resource_id}`) : [])
    const fresh = page.items.filter(item => {
      const key = `${item.resource_type}:${item.resource_id}`
      if (seen.has(key)) return false
      seen.add(key)
      return true
    })
    items.value = append ? [...items.value, ...fresh] : fresh
    nextCursor.value = page.next_cursor
    if (stateFilter.value !== 'all' && !items.value.some(item => item.current_state === stateFilter.value)) {
      stateFilter.value = 'all'
    }
  } catch {
    if (sequence === requestSerial && !controller.signal.aborted && actor === userId.value) error.value = true
  } finally {
    if (sequence === requestSerial) {
      busy.value = false
      activeController = null
    }
  }
}
function selectType(event: Event): void {
  const value = (event.target as HTMLSelectElement).value
  if (myWorkflowTypes.some(type => type === value)) {
    resourceType.value = value as MyWorkflowType
  }
}
watch(resourceType, () => { searchText.value = ''; stateFilter.value = 'all'; void load() })
watch(userId, () => {
  searchText.value = ''; stateFilter.value = 'all'
  if (resourceType.value !== 'all') resourceType.value = 'all'
  else void load()
})
onMounted(() => { void load() })
onBeforeUnmount(() => { ++requestSerial; activeController?.abort() })
</script>

<template>
  <section class="my-work-actions" :aria-label="t('myWork.title')">
    <header class="my-work-heading">
      <h1>{{ t('myWork.title') }}</h1>
      <p class="my-work-description">{{ t('myWork.description') }}</p>
    </header>

    <div class="ds-filter-container">
    <div class="my-work-toolbar ds-filter-form">
      <div class="ds-filter-fields">
      <label class="my-work-control">
        <span>{{ t('myWork.filter') }}</span>
        <select data-testid="my-work-resource-filter" :value="resourceType" :disabled="busy" @change="selectType">
          <option v-for="type in myWorkflowTypes" :key="type" :value="type">
            {{ type === 'all' ? t('myWork.allTypes') : t(`myWork.resourceTypes.${type}`) }}
          </option>
        </select>
      </label>
      <label class="my-work-control my-work-control--search ds-filter-field--wide">
        <span>{{ t('myWork.searchLoaded') }}</span>
        <input v-model="searchText" data-testid="my-work-search" type="search"
          :placeholder="t('myWork.searchPlaceholder')" />
      </label>
      <label class="my-work-control">
        <span>{{ t('myWork.stateFilter') }}</span>
        <select v-model="stateFilter" data-testid="my-work-state-filter">
          <option value="all">{{ t('myWork.allStates') }}</option>
          <option v-for="state in states" :key="state" :value="state">{{ stateLabel(state) }}</option>
        </select>
      </label>
      </div>
      <div class="ds-filter-actions">
      <NButton data-testid="my-work-refresh" secondary :disabled="busy" @click="load()" data-ds-icon="refresh">{{ t('myWork.refresh') }}
      </NButton>
      </div>
    </div>
    </div>

    <p v-if="busy && !items.length" role="status" aria-busy="true">{{ t('myWork.loading') }}</p>
    <p v-if="!enabled" role="status">{{ t('myWork.disabled') }}</p>
    <p v-if="error" role="alert">{{ t('myWork.error') }}</p>
    <NButton v-if="error" size="small" @click="load(items.length > 0 || nextCursor !== null)" data-ds-icon="retry">{{ t('myWork.retry') }}
    </NButton>

    <template v-if="enabled && (items.length || nextCursor !== null)">
      <div class="my-work-summary" role="status">
        <span><strong>{{ displayedRows.length }}</strong> {{ t('myWork.visibleOfLoaded') }} <strong>{{ items.length }}</strong></span>
        <span>{{ t('myWork.availableCount') }}: <strong>{{ availableActionCount }}</strong></span>
        <span>{{ t('myWork.typeCount') }}: <strong>{{ kindCount }}</strong></span>
      </div>
      <div v-if="displayedRows.length" class="my-work-table-scroll">
        <table class="my-work-table">
          <thead>
            <tr>
              <th scope="col">{{ t('myWork.record') }}</th>
              <th scope="col">{{ t('myWork.currentStateLabel') }}</th>
              <th scope="col">{{ t('myWork.actions') }}</th>
              <th scope="col">{{ t('myWork.selectedAt') }}</th>
              <th scope="col">{{ t('myWork.open') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in displayedRows" :key="`${row.item.resource_type}:${row.item.resource_id}`"
              data-testid="my-work-row">
              <td :data-label="t('myWork.record')">
                <div class="my-work-record">
                  <span class="my-work-type">{{ t(`myWork.resourceTypes.${row.item.resource_type}`) }}</span>
                  <strong class="my-work-name">{{ recordTitle(row.item) }}</strong>
                  <div class="my-work-identity">
                    <span v-if="row.item.record_code" class="my-work-code">{{ row.item.record_code }}</span>
                    <span>#{{ row.item.resource_id }}</span>
                  </div>
                </div>
              </td>
              <td :data-label="t('myWork.currentStateLabel')">
                <span class="my-work-state" :class="{ 'my-work-state--active': row.item.current_state === 'ACTIVE' }">
                  {{ stateLabel(row.item.current_state) }}
                </span>
              </td>
              <td :data-label="t('myWork.actions')">
                <ul class="my-work-options">
                  <li v-for="option in row.item.available_actions" :key="`${option.action_code}:${option.status}`">
                    <span>{{ stateLabel(row.item.current_state) }} → <strong>{{ stateLabel(option.status) }}</strong></span>
                    <code>{{ option.action_code }}</code>
                  </li>
                </ul>
              </td>
              <td class="my-work-time" :data-label="t('myWork.selectedAt')">
                <time :datetime="row.item.selected_at">{{ assignedTime(row.item.selected_at) }}</time>
              </td>
              <td :data-label="t('myWork.open')">
                <RouterLink v-if="row.destination" class="my-work-link" :to="row.destination.path">
                  {{ t(row.destination.kind === 'detail' ? 'myWork.openRecord' : 'myWork.openList') }}
                </RouterLink>
                <span v-else class="my-work-muted">{{ t('myWork.unavailable') }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="my-work-empty-filter" role="status">{{ t('myWork.noFilterMatches') }}</p>
      <p class="my-work-scope-note">{{ t('myWork.localFilterNote') }}</p>
    </template>
    <p v-else-if="!busy && !error && enabled">{{ t('myWork.empty') }}</p>
    <div v-if="nextCursor !== null" class="my-work-more">
      <NButton data-testid="my-work-more" :disabled="busy" secondary @click="load(true)" data-ds-icon="next">{{ t('myWork.more') }}
      </NButton>
    </div>
  </section>
</template>

<style scoped>
.my-work-actions { width: min(100%, 1240px); margin: 0 auto; padding: 20px; color: var(--ds-text-primary); }
.my-work-heading h1 { font-size: 1.65rem; margin: 0 0 8px; }
.my-work-description { max-width: 900px; margin: 0; line-height: 1.6; color: var(--ds-text-secondary); }
.my-work-toolbar { margin: 24px 0 16px; }
.my-work-control { font-size: 12px; font-weight: 600; color: var(--ds-text-secondary); }
.my-work-control :is(select, input) { width: 100%; min-height: 38px; padding: 8px 10px; border: 1px solid var(--ds-border); border-radius: 6px; background: var(--ds-bg-surface); color: var(--ds-text-primary); font: inherit; font-size: 13px; }
.my-work-summary { display: flex; flex-wrap: wrap; gap: 8px 20px; padding: 12px 16px; border: 1px solid var(--ds-border-subtle); border-radius: 7px 7px 0 0; background: var(--ds-bg-subtle); color: var(--ds-text-secondary); font-size: 13px; }
.my-work-summary strong { color: var(--ds-text-primary); }
.my-work-table-scroll { width: 100%; max-width: 100%; overflow-x: auto; border: 1px solid var(--ds-border-subtle); border-top: 0; border-radius: 0 0 7px 7px; background: var(--ds-bg-surface); }
.my-work-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: start; }
.my-work-table th { padding: 12px; text-align: start; white-space: nowrap; color: var(--ds-text-secondary); background: var(--ds-bg-subtle); border-bottom: 1px solid var(--ds-border-subtle); }
.my-work-table td { padding: 14px 12px; border-top: 1px solid var(--ds-border-subtle); vertical-align: middle; }
.my-work-table tbody tr:hover { background: var(--ds-bg-hover); }
.my-work-record { display: grid; justify-items: start; gap: 5px; min-width: 180px; max-width: 340px; }
.my-work-type { padding: 3px 7px; border-radius: 4px; font-size: 11px; background: var(--ds-info-soft); color: var(--ds-info); }
.my-work-name { color: var(--ds-text-primary); overflow-wrap: anywhere; font-size: 14px; }
.my-work-identity { display: flex; flex-wrap: wrap; gap: 6px 10px; font-size: 12px; color: var(--ds-text-muted); }
.my-work-code { font-weight: 600; color: var(--ds-text-secondary); }
.my-work-state { display: inline-flex; padding: 5px 9px; border-radius: 20px; border: 1px solid var(--ds-border-subtle); background: var(--ds-bg-subtle); font-size: 12px; font-weight: 600; white-space: nowrap; }
.my-work-state--active { background: var(--ds-success-soft); color: var(--ds-success); border-color: var(--ds-success); }
.my-work-options { display: grid; gap: 7px; list-style: none; margin: 0; padding: 0; min-width: 150px; }
.my-work-options li { display: grid; gap: 3px; width: fit-content; max-width: 100%; padding: 6px 9px; border-radius: 5px; background: var(--ds-bg-subtle); color: var(--ds-text-primary); font-size: 12px; }
.my-work-options code { color: var(--ds-text-muted); font-size: 11px; overflow-wrap: anywhere; }
.my-work-time { white-space: nowrap; color: var(--ds-text-secondary); }
.my-work-link { color: var(--ds-primary); font-weight: 600; white-space: nowrap; }
.my-work-muted { color: var(--ds-text-muted); }
.my-work-scope-note { font-size: 12px; line-height: 1.5; color: var(--ds-text-muted); }
.my-work-empty-filter { padding: 24px; text-align: center; border: 1px solid var(--ds-border-subtle); border-top: 0; margin: 0; }
.my-work-more { display: flex; justify-content: center; padding: 12px; }
@media (max-width: 760px) {
  .my-work-actions { padding: 12px; }
  .my-work-table-scroll { overflow-x: visible; border: 0; background: transparent; }
  .my-work-table, .my-work-table tbody { display: block; }
  .my-work-table thead { display: none; }
  .my-work-table tbody tr { display: grid; gap: 10px; margin: 10px 0; padding: 14px; border: 1px solid var(--ds-border-subtle); border-radius: 8px; background: var(--ds-bg-surface); }
  .my-work-table td { display: grid; grid-template-columns: 100px minmax(0, 1fr); align-items: start; gap: 8px; padding: 0; border: 0; }
  .my-work-table td::before { content: attr(data-label); color: var(--ds-text-secondary); font-size: 12px; font-weight: 600; }
  .my-work-record { min-width: 0; }
  .my-work-time { white-space: normal; }
}
</style>
