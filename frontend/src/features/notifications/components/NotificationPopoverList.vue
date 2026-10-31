<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { NButton } from 'naive-ui'

import { RouterLink } from 'vue-router'
import { notificationDestination, type InboxNotification } from '@/services/notificationInbox'

const props = defineProps<{
  items: InboxNotification[]
  busy: boolean
  error: boolean
}>()
const emit = defineEmits<{
  navigate: []
  markRead: [id: string]
}>()
const { t, locale } = useI18n()
const rows = computed(() => props.items.slice(0, 5).map(item => ({
  item,
  destination: notificationDestination(item),
})))
const visibleRows = computed(() => (props.busy || props.error) ? [] : rows.value)

function displayTime(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleString(locale.value === 'vi' ? 'vi-VN' : 'en-US')
}
</script>

<template>
  <div class="notification-list" role="list">
    <p v-if="busy" role="status" aria-busy="true">{{ t('notificationsInbox.loading') }}</p>
    <p v-else-if="error" role="alert">{{ t('notificationsInbox.requestError') }}</p>
    <p v-else-if="rows.length === 0" role="status">{{ t('notificationsInbox.empty') }}</p>
    <article v-for="row in visibleRows" :key="row.item.id" class="notification-item" role="listitem">
      <span v-if="row.item.read_at === null" class="notification-dot" aria-hidden="true" />
      <div class="notification-item-content">
        <strong>{{ t(row.item.title_key, row.item.params) }}</strong>
        <p>{{ t(row.item.body_key, row.item.params) }}</p>
        <time :datetime="row.item.created_at">{{ displayTime(row.item.created_at) }}</time>
        <small v-if="row.destination?.kind === 'list'" class="notification-context">#{{ row.item.target.id }}</small>
        <div class="notification-item-actions">
          <RouterLink
            v-if="row.destination"
            :to="row.destination.path"
            :aria-label="`${t(row.destination.kind === 'detail' ? 'notificationsInbox.openTarget' : 'notificationsInbox.openList')}: ${t(row.item.title_key, row.item.params)}`"
            @click="emit('navigate')"
          >
            {{ t(row.destination.kind === 'detail' ? 'notificationsInbox.openTarget' : 'notificationsInbox.openList') }}
          </RouterLink>
          <span v-else class="notification-target-unavailable">{{ t('notificationsInbox.targetUnavailable') }}</span>
          <NButton v-if="row.item.read_at === null" text size="tiny" @click="emit('markRead', row.item.id)" data-ds-icon="check">{{ t('notificationsInbox.markRead') }}
          </NButton>
        </div>
      </div>
    </article>
  </div>
</template>

<style scoped>
.notification-list { display: grid; gap: 2px; padding-top: 5px; }
.notification-item { display: flex; gap: 10px; padding: 10px 4px; border-bottom: 1px solid var(--ds-border-subtle); }
.notification-item:last-child { border-bottom: 0; }
.notification-item-content { min-width: 0; width: 100%; }
.notification-item strong { color: var(--ds-text-primary); font-size: 12px; }
.notification-item p { margin: 3px 0 0; color: var(--ds-text-secondary); font-size: 11px; line-height: 1.45; }
.notification-item time { display: block; margin-top: 4px; font-size: 10px; opacity: .72; }
.notification-context { display: inline-block; margin-top: 4px; font-size: 10px; opacity: .75; }
.notification-item-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 7px; font-size: 11px; }
.notification-item-actions a { color: var(--ds-primary); text-underline-offset: 2px; }
.notification-item-actions a:focus-visible { outline: 2px solid currentColor; outline-offset: 3px; }
.notification-target-unavailable { opacity: .7; }
.notification-dot { width: 8px; height: 8px; flex: 0 0 auto; margin-top: 5px; border-radius: 50%; background: var(--ds-primary); }
</style>
