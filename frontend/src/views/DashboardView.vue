<script setup lang="ts">
import { computed } from 'vue'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const firstName = computed(() => auth.user?.username || 'bạn')
</script>

<template>
  <div class="breadcrumbs">⌂ <span>›</span> Workspace</div>
  <div class="page-heading"><div><h1>Tổng quan</h1><p>Không gian làm việc của {{ firstName }}.</p></div><button class="primary-button" type="button">＋ Tạo nhanh</button></div>
  <div class="metric-grid"><div class="metric-card"><small>Nhóm quyền của bạn</small><strong>{{ auth.user?.groups.length || 0 }}</strong></div><div class="metric-card"><small>Phạm vi tổ chức</small><strong>0</strong></div><div class="metric-card"><small>Yêu cầu chờ xử lý</small><strong>0</strong></div><div class="metric-card"><small>Trạng thái phiên</small><strong class="metric-live">●</strong></div></div>
  <section class="dashboard-blank"><div><div class="dashboard-icon">◉</div><strong>Workspace của bạn đã sẵn sàng</strong><p>Mở màn hình từ menu bên trái hoặc tìm kiếm trong workspace để bắt đầu.</p></div></section>
</template>

<style scoped>
.dashboard-icon { display: grid; width: 54px; height: 54px; margin: auto; place-items: center; border-radius: 7px; background: #e7f0f8; color: #0d76b5; font-size: 22px; }
.metric-live { color: #1c9a68 !important; }
</style>
