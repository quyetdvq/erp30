<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { STANDARD_PAGE_SIZE_OPTIONS } from './listPageSizePolicy'

const props = withDefaults(
  defineProps<{
    page: number
    pageSize: number
    total: number
    pageSizeOptions?: readonly number[]
    showPageSize?: boolean
    disabled?: boolean
    ariaLabel?: string
  }>(),
  { pageSizeOptions: () => STANDARD_PAGE_SIZE_OPTIONS, showPageSize: true, disabled: false },
)

const emit = defineEmits<{
  'update:page': [value: number]
  'update:pageSize': [value: number]
}>()

const { t } = useI18n()
const pageCount = computed(() => Math.max(1, Math.ceil(props.total / props.pageSize)))
const currentPage = computed(() => Math.min(Math.max(1, props.page), pageCount.value))

function goTo(page: number): void {
  emit('update:page', Math.min(Math.max(1, page), pageCount.value))
}
</script>

<template>
  <nav class="list-pagination" :aria-label="props.ariaLabel ?? t('list.pagination')">
    <div class="list-pagination__summary" aria-live="polite">
      {{ t('list.pageSummary', { page: currentPage, pages: pageCount, total: props.total }) }}
    </div>
    <label v-if="props.showPageSize" class="list-pagination__size">
      <span>{{ t('list.pageSize') }}</span>
      <select
        :value="String(props.pageSize)"
        :disabled="props.disabled"
        :aria-label="t('list.pageSize')"
        @change="emit('update:pageSize', Number(($event.target as HTMLSelectElement).value))"
      >
        <option v-for="size in props.pageSizeOptions" :key="size" :value="size">{{ size }}</option>
      </select>
    </label>
    <div class="list-pagination__actions">
      <button class="ds-icon-control" data-ds-icon="first"
        type="button"
        :disabled="props.disabled || currentPage === 1"
        :aria-label="t('list.first')"
        @click="goTo(1)"
      >
      </button>
      <button class="ds-icon-control" data-ds-icon="previous"
        type="button"
        :disabled="props.disabled || currentPage === 1"
        :aria-label="t('list.previous')"
        @click="goTo(currentPage - 1)"
      >
      </button>
      <span aria-current="page">{{ currentPage }}</span>
      <button class="ds-icon-control" data-ds-icon="next"
        type="button"
        :disabled="props.disabled || currentPage === pageCount"
        :aria-label="t('list.next')"
        @click="goTo(currentPage + 1)"
      >
      </button>
      <button class="ds-icon-control" data-ds-icon="last"
        type="button"
        :disabled="props.disabled || currentPage === pageCount"
        :aria-label="t('list.last')"
        @click="goTo(pageCount)"
      >
      </button>
    </div>
  </nav>
</template>
