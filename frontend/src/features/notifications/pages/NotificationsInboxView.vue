<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { NButton } from 'naive-ui'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { notificationDestination, type InboxEventGroup } from '@/services/notificationInbox'
import { inboxUiEnabled } from '@/features/notifications/featureFlags'

const { t } = useI18n()
const auth = useAuthStore()
const inbox = useNotificationsStore()
const unread = computed(() => inbox.filter === 'unread')
const displayItems = computed(() => inbox.items.map(item => ({ item, destination: notificationDestination(item) })))
const groupDraft = ref<InboxEventGroup>(inbox.eventGroup)
const fromDraft = ref(inbox.dateFrom ?? '')
const toDraft = ref(inbox.dateTo ?? '')
const invalidPeriod = computed(() => {
  if (!fromDraft.value && !toDraft.value) return false
  if (!fromDraft.value || !toDraft.value) return true
  if (fromDraft.value > toDraft.value) return true
  if (fromDraft.value < '1970-01-01' || toDraft.value > '2099-12-31') return true
  const start = Date.parse(`${fromDraft.value}T00:00:00Z`)
  const end = Date.parse(`${toDraft.value}T00:00:00Z`)
  return !Number.isFinite(start) || !Number.isFinite(end) || (end - start) / 86400000 > 365
})
const hasActiveFilters = computed(() => inbox.filter !== 'all' || inbox.eventGroup !== 'all' || inbox.dateFrom !== null)
const pendingFilters = computed(() => groupDraft.value !== inbox.eventGroup
  || (fromDraft.value || null) !== inbox.dateFrom || (toDraft.value || null) !== inbox.dateTo)
const sessionId = computed(() => auth.user?.id)
watch(sessionId, () => {
  groupDraft.value = 'all'; fromDraft.value = ''; toDraft.value = ''
  if (inboxUiEnabled) void inbox.loadPage('all', false, { eventGroup: 'all', from: null, to: null })
})
onMounted(() => { if (inboxUiEnabled) void inbox.loadPage(inbox.filter) })
function setFilter(filter: 'all' | 'unread'): void {
  if (invalidPeriod.value) return
  void inbox.loadPage(filter, false, {
    eventGroup: groupDraft.value, from: fromDraft.value || null, to: toDraft.value || null,
  })
}
function applyFilters(): void {
  if (invalidPeriod.value) return
  void inbox.loadPage(inbox.filter, false, {
    eventGroup: groupDraft.value, from: fromDraft.value || null, to: toDraft.value || null,
  })
}
function resetFilters(): void {
  groupDraft.value = 'all'; fromDraft.value = ''; toDraft.value = ''
  void inbox.loadPage('all', false, { eventGroup: 'all', from: null, to: null })
}
async function markRead(id: string): Promise<void> {
  try { await inbox.markRead(id) } catch { /* API error boundary displays details. */ }
}
async function markAll(): Promise<void> {
  try { await inbox.markAllRead() } catch { /* API error boundary displays details. */ }
}
</script>

<template>
  <section class="m11-inbox" :aria-label="t('notificationsInbox.title')">
    <header class="m11-heading">
      <div>
        <h1>{{ t('notificationsInbox.title') }}</h1>
        <p>{{ t('notificationsInbox.description') }}</p>
      </div>
      <NButton v-if="inboxUiEnabled" secondary :disabled="inbox.unreadCount === 0" @click="markAll">
        {{ t('shell.markAllRead') }}
      </NButton>
    </header>
    <p v-if="!inboxUiEnabled" role="status">{{ t('notificationsInbox.disabled') }}</p>
    <template v-else>
      <nav class="m11-filters" :aria-label="t('notificationsInbox.filters')">
        <NButton :type="unread ? 'default' : 'primary'" :disabled="invalidPeriod || inbox.busy" @click="setFilter('all')">{{ t('notificationsInbox.all') }}</NButton>
        <NButton :type="unread ? 'primary' : 'default'" :disabled="invalidPeriod || inbox.busy" @click="setFilter('unread')">
          {{ t('notificationsInbox.unread') }} ({{ inbox.unreadCount }})
        </NButton>
      </nav>
      <div class="m11-advanced-filters" role="group" :aria-label="t('notificationsInbox.advancedFilters')">
        <label>
          <span>{{ t('notificationsInbox.eventGroup') }}</span>
          <select v-model="groupDraft" data-testid="inbox-event-group" :disabled="inbox.busy">
            <option value="all">{{ t('notificationsInbox.allGroups') }}</option>
            <option value="import">{{ t('notificationsInbox.importGroup') }}</option>
            <option value="workflow">{{ t('notificationsInbox.workflowGroup') }}</option>
          </select>
        </label>
        <label>
          <span>{{ t('notificationsInbox.fromUtc') }}</span>
          <input v-model="fromDraft" data-testid="inbox-from" type="date" min="1970-01-01" max="2099-12-31" :disabled="inbox.busy" />
        </label>
        <label>
          <span>{{ t('notificationsInbox.toUtc') }}</span>
          <input v-model="toDraft" data-testid="inbox-to" type="date" min="1970-01-01" max="2099-12-31" :disabled="inbox.busy" />
        </label>
        <NButton data-testid="inbox-apply" :disabled="inbox.busy || invalidPeriod" @click="applyFilters">
          {{ t('notificationsInbox.applyFilters') }}
        </NButton>
        <NButton data-testid="inbox-reset" secondary :disabled="inbox.busy" @click="resetFilters">
          {{ t('notificationsInbox.resetFilters') }}
        </NButton>
      </div>
      <p v-if="pendingFilters && !invalidPeriod" role="status">{{ t('notificationsInbox.pendingFilters') }}</p>
      <p v-if="invalidPeriod" role="alert">{{ t('notificationsInbox.dateRangeError') }}</p>
      <p v-if="inbox.busy" aria-busy="true">{{ t('notificationsInbox.loading') }}</p>
      <p v-if="inbox.error" role="alert">{{ t('notificationsInbox.requestError') }}</p>
      <p v-if="!inbox.busy && !inbox.error && inbox.items.length === 0">{{ t(hasActiveFilters ? 'notificationsInbox.noMatches' : 'notificationsInbox.empty') }}</p>
      <div class="m11-notices" role="list">
        <article v-for="row in displayItems" :key="row.item.id" class="m11-notice" role="listitem">
          <div>
            <strong>{{ t(row.item.title_key, row.item.params) }}</strong>
            <p>{{ t(row.item.body_key, row.item.params) }}</p>
            <time :datetime="row.item.created_at">{{ new Date(row.item.created_at).toLocaleString() }}</time>
            <small v-if="row.destination?.kind === 'list'" class="m11-target-context">#{{ row.item.target.id }}</small>
          </div>
          <div class="m11-notice-actions">
            <RouterLink v-if="row.destination" :to="row.destination.path">
              {{ t(row.destination.kind === 'detail' ? 'notificationsInbox.openTarget' : 'notificationsInbox.openList') }}
            </RouterLink>
            <span v-else>{{ t('notificationsInbox.targetUnavailable') }}</span>
            <NButton v-if="row.item.read_at === null" size="small" @click="markRead(row.item.id)">
              {{ t('notificationsInbox.markRead') }}
            </NButton>
            <span v-else>{{ t('notificationsInbox.read') }}</span>
          </div>
        </article>
      </div>
      <NButton v-if="inbox.nextCursor" :disabled="inbox.busy" @click="inbox.loadPage(inbox.filter, true)">
        {{ t('notificationsInbox.more') }}
      </NButton>
    </template>
  </section>
</template>

<style scoped>
.m11-inbox { width: min(100%, 980px); margin: 0 auto; padding: 20px; }
.m11-heading { display: flex; justify-content: space-between; align-items: start; gap: 18px; }
.m11-heading h1 { font-size: 1.5rem; margin: 0 0 6px; }
.m11-heading p { opacity: .75; }
.m11-filters, .m11-notice-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
.m11-filters { margin: 16px 0; }
.m11-advanced-filters { display: flex; flex-wrap: wrap; align-items: end; gap: 12px; margin: 14px 0; }
.m11-advanced-filters label { display: flex; flex-direction: column; gap: 5px; font-size: .85rem; }
.m11-advanced-filters select, .m11-advanced-filters input { min-height: 34px; padding: 5px 8px; border: 1px solid var(--n-border-color, #aaa5); border-radius: 6px; background: var(--n-color, white); color: inherit; }
.m11-notices { display: grid; gap: 10px; margin: 12px 0; }
.m11-notice { display: flex; gap: 18px; justify-content: space-between; padding: 16px; border: 1px solid var(--n-border-color, #aaa5); border-radius: 10px; }
.m11-notice p { margin: 8px 0; }
.m11-notice time { opacity: .7; font-size: .82rem; }
.m11-target-context { display: block; margin-top: 5px; opacity: .7; font-size: .82rem; }
@media(max-width:650px) { .m11-notice, .m11-heading { flex-direction: column; } }
</style>
