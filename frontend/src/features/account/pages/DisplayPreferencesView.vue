<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const activeTab = computed(() => route.query.tab === 'notifications' ? 'notifications' : 'display')
const DisplayTab = defineAsyncComponent(() => import('./DisplayPreferencesDisplayTab.vue'))
const NotificationsTab = defineAsyncComponent(() => import('@/features/notifications/pages/NotificationPreferencesTab.vue'))
function selectTab(tab: 'display' | 'notifications') {
  void router.push({ path: route.path, query: { ...route.query, tab } })
}
</script>

<template>
  <div class="m11-tabs" role="tablist" :aria-label="t('notificationSettings.tabs')">
    <button type="button" role="tab" :aria-selected="activeTab === 'display'" @click="selectTab('display')">
      {{ t('notificationSettings.displayTab') }}
    </button>
    <button type="button" role="tab" :aria-selected="activeTab === 'notifications'" @click="selectTab('notifications')">
      {{ t('notificationSettings.notificationsTab') }}
    </button>
  </div>
  <DisplayTab v-if="activeTab === 'display'" />
  <NotificationsTab v-else />
</template>

<style scoped>
.m11-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.m11-tabs button { background: transparent; border: 1px solid #8297a9; border-radius: 7px; padding: 9px 15px; color: inherit; cursor: pointer; }
.m11-tabs button[aria-selected="true"] { background: #215d86; color: #fff; border-color: #215d86; }
</style>
