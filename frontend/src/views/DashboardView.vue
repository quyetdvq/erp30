<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const { t } = useI18n()
const firstName = computed(() => auth.user?.username || 'bạn')
</script>

<template>
  <div class="breadcrumbs">⌂ <span>›</span> {{ t('dashboard.breadcrumb') }}</div>
  <div class="page-heading">
    <div>
      <h1>{{ t('dashboard.title') }}</h1>
      <p>{{ t('dashboard.greeting', { name: firstName }) }}</p>
    </div>
    <button class="primary-button" type="button">＋ {{ t('dashboard.quickCreate') }}</button>
  </div>
  <div class="metric-grid">
    <div class="metric-card">
      <small>{{ t('dashboard.groups') }}</small
      ><strong>{{ auth.user?.groups.length || 0 }}</strong>
    </div>
    <div class="metric-card">
      <small>{{ t('dashboard.organizationScope') }}</small
      ><strong>0</strong>
    </div>
    <div class="metric-card">
      <small>{{ t('dashboard.pendingRequests') }}</small
      ><strong>0</strong>
    </div>
    <div class="metric-card">
      <small>{{ t('dashboard.sessionStatus') }}</small
      ><strong class="metric-live">●</strong>
    </div>
  </div>
  <section class="dashboard-blank">
    <div>
      <div class="dashboard-icon">◉</div>
      <strong>{{ t('dashboard.ready') }}</strong>
      <p>{{ t('dashboard.readyDescription') }}</p>
    </div>
  </section>
</template>

<style scoped>
.dashboard-icon {
  display: grid;
  width: 54px;
  height: 54px;
  margin: auto;
  place-items: center;
  border-radius: 7px;
  background: #e7f0f8;
  color: #0d76b5;
  font-size: 22px;
}
.metric-live {
  color: #1c9a68 !important;
}
</style>
