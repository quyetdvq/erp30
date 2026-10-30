<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ListPagination } from '@/components/lists'
import { reportApiError } from '@/services/errorBoundary'
import { customerHistory, supplierHistory, type BusinessHistoryEvent } from '@/services/history'
import { useFormatters } from '@/composables/useFormatters'
import { historyActorLabel, historyChangeDisplay } from '@/features/history/historyPresentation'

// Exactly one parent may be supplied. An invalid combination never calls an API.
const props = defineProps<{ customerId?: string; supplierId?: string }>()
const { t } = useI18n()
const formatters = useFormatters()
const items = ref<BusinessHistoryEvent[]>([])
const page = ref(1)
const total = ref(0)
const pageSize = ref(25)
const busy = ref(false)
const error = ref(false)
let generation = 0

async function load(): Promise<void> {
  const token = ++generation
  error.value = false
  busy.value = true
  try {
    const parent = props.supplierId && !props.customerId ? 'supplier'
      : props.customerId && !props.supplierId ? 'customer' : null
    if (!parent) throw new Error('HISTORY_PARENT_REQUIRED')
    const result = parent === 'supplier'
      ? await supplierHistory(props.supplierId!, page.value, pageSize.value)
      : await customerHistory(props.customerId!, page.value, pageSize.value)
    if (token !== generation) return
    items.value = result.data
    total.value = result.meta.total
  } catch (cause) {
    if (token !== generation) return
    error.value = true
    items.value = []
    total.value = 0
    reportApiError(cause)
  } finally {
    if (token === generation) busy.value = false
  }
}
function setPage(next: number): void { page.value = next; void load() }
function setPageSize(next: number): void { pageSize.value = next; page.value = 1; void load() }
watch([() => props.customerId, () => props.supplierId], () => { page.value = 1; void load() }, { immediate: true })
onBeforeUnmount(() => { ++generation })

// Present each row once. A malformed event remains visible, but its untrusted
// diff is never rendered and cannot blank the complete Customer or Supplier modal.
const displayedItems = computed(() => items.value.map((event) => ({
  event, changeDisplay: historyChangeDisplay(event, t('history.redactedChange')),
})))
const hasRecords = computed(() => displayedItems.value.length > 0)
function actorLabel(event: BusinessHistoryEvent): string {
  return historyActorLabel(event, t('history.user'), t('history.system'))
}

</script>

<template>
  <section class="business-history" :aria-label="t('history.title')">
    <header class="business-history__header">
      <h3>{{ t('history.title') }}</h3>
      <button class="secondary-button" type="button" :disabled="busy" @click="void load()">
        {{ t('common.refresh') }}
      </button>
    </header>
    <p class="business-history__hint">{{ t('history.selectivePolicy') }}</p>
    <p v-if="busy" role="status">{{ t('history.loading') }}</p>
    <p v-if="error" role="alert" class="business-history__error">
      {{ t('history.loadError') }}
      <button class="secondary-button" type="button" @click="void load()">{{ t('list.retry') }}</button>
    </p>
    <p v-else-if="!busy && !hasRecords">{{ t('history.noEvents') }}</p>
    <ol v-if="hasRecords" class="business-history__items">
      <li v-for="item in displayedItems" :key="item.event.id" class="business-history__event">
        <strong>{{ t(`history.events.${item.event.event_type}`) }}</strong>
        <span>{{ actorLabel(item.event) }} · <time :datetime="item.event.created_at">{{ formatters.formatDateTime(item.event.created_at) }}</time> · #{{ item.event.id }}</span>
        <span v-if="item.event.status_before !== item.event.status_after && item.event.status_after">
          {{ item.event.status_before ?? '—' }} → {{ item.event.status_after }}
        </span>
        <p v-if="item.changeDisplay.invalid" role="alert" class="business-history__error" data-testid="history-invalid-changes">
          {{ t('history.invalidChangesFormat') }}
        </p>
        <ul v-if="item.changeDisplay.lines.length" class="business-history__changes">
          <li v-for="summary in item.changeDisplay.lines" :key="summary">{{ summary }}</li>
        </ul>
        <small v-if="item.event.checkpoint">{{ t('history.checkpoint') }}</small>
      </li>
    </ol>
    <ListPagination v-if="total > 0" :page="page" :page-size="pageSize" :total="total" :page-size-options="[10, 25, 50]"
      @update:page="setPage" @update:page-size="setPageSize" />
  </section>
</template>

<style scoped>
.business-history { display: grid; gap: 12px; min-width: 0; }
.business-history__header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.business-history__header h3 { margin: 0; font-size: 16px; }
.business-history__hint, .business-history__event span, .business-history__event small { color: #5c7181; font-size: 12px; }
.business-history__error { color: #b64444; }
.business-history__items { margin: 0; padding-left: 22px; display: grid; gap: 12px; min-width: 0; }
.business-history__event { display: grid; gap: 4px; min-width: 0; overflow-wrap: anywhere; border-bottom: 1px solid #e0e9ef; padding: 0 0 10px; }
.business-history__changes { padding-left: 18px; margin: 0; font-size: 12px; overflow-wrap: anywhere; }
</style>
