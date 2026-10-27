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

const { t } = useI18n()
const auth = useAuthStore()
const resourceType = ref<MyWorkflowType>('all')
const items = ref<MyWorkflowItem[]>([])
const nextCursor = ref<string | null>(null)
const enabled = ref(true)
const busy = ref(false)
const error = ref(false)
const rows = computed(() => items.value.map(item => ({
  item, destination: workflowDestination(item.resource_type, item.resource_id),
})))
let requestSerial = 0
let activeController: AbortController | null = null
const userId = computed(() => String(auth.user?.id ?? ''))

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
watch(resourceType, () => { void load() })
watch(userId, () => {
  if (resourceType.value !== 'all') resourceType.value = 'all'
  else void load()
})
onMounted(() => { void load() })
onBeforeUnmount(() => { ++requestSerial; activeController?.abort() })
</script>

<template>
  <section class="my-work-actions" :aria-label="t('myWork.title')">
    <h1>{{ t('myWork.title') }}</h1>
    <p class="my-work-description">{{ t('myWork.description') }}</p>
    <label class="my-work-filter">
      <span>{{ t('myWork.filter') }}</span>
      <select data-testid="my-work-resource-filter" :value="resourceType" :disabled="busy" @change="selectType">
        <option v-for="type in myWorkflowTypes" :key="type" :value="type">
          {{ type === 'all' ? t('myWork.allTypes') : t(`myWork.resourceTypes.${type}`) }}
        </option>
      </select>
    </label>
    <NButton data-testid="my-work-refresh" secondary :disabled="busy" @click="load()">
      {{ t('myWork.refresh') }}
    </NButton>
    <p v-if="busy" role="status" aria-busy="true">{{ t('myWork.loading') }}</p>
    <p v-if="!enabled" role="status">{{ t('myWork.disabled') }}</p>
    <p v-if="error" role="alert">{{ t('myWork.error') }}</p>
    <NButton v-if="error" size="small" @click="load(items.length > 0 || nextCursor !== null)">
      {{ t('myWork.retry') }}
    </NButton>
    <p v-if="!busy && !error && enabled && !items.length">{{ t('myWork.empty') }}</p>
    <div class="my-work-list" role="list">
      <article v-for="row in rows" :key="`${row.item.resource_type}:${row.item.resource_id}`" role="listitem" class="my-work-row">
        <div>
          <strong>{{ t(`myWork.resourceTypes.${row.item.resource_type}`) }} #{{ row.item.resource_id }}</strong>
          <p>{{ row.item.current_state }}</p>
          <small>{{ t('myWork.selectedAt') }}: {{ new Date(row.item.selected_at).toLocaleString() }}</small>
          <div class="my-work-actions-label">{{ t('myWork.actions') }}:</div>
          <ul class="my-work-options">
            <li v-for="option in row.item.available_actions" :key="`${option.action_code}:${option.status}`">
              {{ option.action_code }} → {{ option.status }}
            </li>
          </ul>
        </div>
        <div class="my-work-action-link">
          <RouterLink v-if="row.destination" :to="row.destination.path">
            {{ t(row.destination.kind === 'detail' ? 'myWork.openRecord' : 'myWork.openList') }}
          </RouterLink>
          <span v-else>{{ t('myWork.unavailable') }}</span>
        </div>
      </article>
    </div>
    <NButton v-if="nextCursor !== null" data-testid="my-work-more" :disabled="busy" secondary @click="load(true)">
      {{ t('myWork.more') }}
    </NButton>
  </section>
</template>

<style scoped>
.my-work-actions { width: min(100%, 980px); margin: 0 auto; padding: 20px; }
.my-work-actions h1 { font-size: 1.5rem; margin: 0 0 8px; }
.my-work-description { opacity: .8; max-width: 800px; }
.my-work-filter { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 20px 0; }
.my-work-filter select { min-height: 34px; padding: 5px 8px; border-radius: 6px; border: 1px solid var(--n-border-color, #aaa5); background: var(--n-color, white); color: inherit; }
.my-work-list { display: grid; gap: 12px; margin: 16px 0; }
.my-work-row { display: flex; justify-content: space-between; gap: 18px; border: 1px solid var(--n-border-color, #aaa5); border-radius: 10px; padding: 16px; }
.my-work-row p { margin: 8px 0; }
.my-work-row small { opacity: .7; }
.my-work-actions-label { margin-top: 12px; font-size: .9rem; font-weight: 600; }
.my-work-options { display: flex; flex-wrap: wrap; gap: 8px; list-style: none; padding: 0; margin: 8px 0 0; }
.my-work-options li { border-radius: 6px; background: var(--n-color-modal, #80808018); padding: 4px 8px; font-size: .85rem; }
.my-work-action-link { flex-shrink: 0; align-self: center; }
@media (max-width: 650px) { .my-work-row { flex-direction: column; } .my-work-action-link { align-self: start; } }
</style>
