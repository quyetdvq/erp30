<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import {
  BanOutline,
  CheckmarkCircleOutline,
  CloseCircleOutline,
  DocumentTextOutline,
  HelpCircleOutline,
} from '@vicons/ionicons5'

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
const isDraft = computed(() => normalizedStatus.value === 'DRAFT')
const isInactive = computed(() => normalizedStatus.value === 'INACTIVE')
const icon = computed(() => {
  if (isActive.value) return CheckmarkCircleOutline
  if (isClosed.value) return BanOutline
  if (isDraft.value) return DocumentTextOutline
  if (isInactive.value) return CloseCircleOutline
  return HelpCircleOutline
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
      'table-status--inactive': isInactive,
      'table-status--draft': isDraft,
      'table-status--other': !isActive && !isClosed && !isDraft && !isInactive,
    }"
    :title="label"
    :aria-label="label"
    role="img"
  >
    <NIcon :component="icon" aria-hidden="true" />
    <span class="sr-only">{{ label }}</span>
  </span>
</template>
