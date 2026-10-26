<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { NButton } from 'naive-ui'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { notificationTarget } from '@/services/notificationInbox'
import { inboxUiEnabled } from '@/features/notifications/featureFlags'

const { t } = useI18n()
const auth = useAuthStore()
const inbox = useNotificationsStore()
const unread = computed(() => inbox.filter === 'unread')
const sessionId = computed(() => auth.user?.id)
watch(sessionId, () => { if (inboxUiEnabled) void inbox.loadPage('all') })
onMounted(() => { if (inboxUiEnabled) void inbox.loadPage('all') })
function setFilter(filter: 'all' | 'unread'): void { void inbox.loadPage(filter) }
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
        <NButton :type="unread ? 'default' : 'primary'" @click="setFilter('all')">{{ t('notificationsInbox.all') }}</NButton>
        <NButton :type="unread ? 'primary' : 'default'" @click="setFilter('unread')">
          {{ t('notificationsInbox.unread') }} ({{ inbox.unreadCount }})
        </NButton>
      </nav>
      <p v-if="inbox.busy" aria-busy="true">{{ t('notificationsInbox.loading') }}</p>
      <p v-if="inbox.error" role="alert">{{ t('notificationsInbox.requestError') }}</p>
      <p v-if="!inbox.busy && !inbox.error && inbox.items.length === 0">{{ t('notificationsInbox.empty') }}</p>
      <div class="m11-notices" role="list">
        <article v-for="item in inbox.items" :key="item.id" class="m11-notice" role="listitem">
          <div>
            <strong>{{ t(item.title_key, item.params) }}</strong>
            <p>{{ t(item.body_key, item.params) }}</p>
            <time :datetime="item.created_at">{{ new Date(item.created_at).toLocaleString() }}</time>
          </div>
          <div class="m11-notice-actions">
            <RouterLink v-if="notificationTarget(item)" :to="notificationTarget(item) || '/notifications'">
              {{ t('notificationsInbox.openTarget') }}
            </RouterLink>
            <NButton v-if="item.read_at === null" size="small" @click="markRead(item.id)">
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
.m11-notices { display: grid; gap: 10px; margin: 12px 0; }
.m11-notice { display: flex; gap: 18px; justify-content: space-between; padding: 16px; border: 1px solid var(--n-border-color, #aaa5); border-radius: 10px; }
.m11-notice p { margin: 8px 0; }
.m11-notice time { opacity: .7; font-size: .82rem; }
@media(max-width:650px) { .m11-notice, .m11-heading { flex-direction: column; } }
</style>
