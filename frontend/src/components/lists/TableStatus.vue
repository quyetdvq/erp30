<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import { BanOutline, CheckmarkCircleOutline, CloseCircleOutline } from '@vicons/ionicons5'

const props = defineProps<{
  status: string | boolean
}>()

const { t } = useI18n()

const normalizedStatus = computed(() => {
  if (typeof props.status === 'boolean') return props.status ? 'ACTIVE' : 'INACTIVE'
  return props.status.trim().toUpperCase()
})

const isActive = computed(() => normalizedStatus.value === 'ACTIVE')
const isClosed = computed(() => normalizedStatus.value === 'CLOSED')
const icon = computed(() => {
  if (isActive.value) return CheckmarkCircleOutline
  if (isClosed.value) return BanOutline
  return CloseCircleOutline
})
const label = computed(() => {
  const key = normalizedStatus.value.toLowerCase()
  const translated = t(`status.${key}`)
  return translated === `status.${key}` ? normalizedStatus.value : translated
})
</script>

<template>
  <span
    class="table-status"
    :class="{
      'table-status--active': isActive,
      'table-status--closed': isClosed,
      'table-status--inactive': !isActive && !isClosed,
    }"
    :title="label"
    :aria-label="label"
    role="img"
  >
    <NIcon :component="icon" aria-hidden="true" />
    <span class="sr-only">{{ label }}</span>
  </span>
</template>

<style scoped>
.table-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  font-size: 19px;
  line-height: 1;
}
.table-status--active {
  color: #15803d;
  background: #dcfce7;
}
.table-status--inactive {
  color: #b45309;
  background: #fef3c7;
}
.table-status--closed {
  color: #64748b;
  background: #e2e8f0;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
