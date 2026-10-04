<script setup lang="ts">
defineProps<{ title: string; description: string }>()
</script>

<template>
  <div class="breadcrumbs">⌂ <span>›</span> Workspace</div>
  <div class="page-heading"><div><h1>{{ title }}</h1><p>{{ description }}</p></div></div>
  <section class="dashboard-blank"><div><div class="dashboard-icon">◇</div><strong>Module đang được chuẩn bị</strong><p>Khung workspace đã sẵn sàng. Chức năng nghiệp vụ sẽ được kết nối theo từng phase.</p></div></section>
</template>
