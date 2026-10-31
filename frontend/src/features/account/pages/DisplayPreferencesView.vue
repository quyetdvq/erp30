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
  <div class="m11-tabs ds-settings-tabs" role="tablist" :aria-label="t('notificationSettings.tabs')">
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
