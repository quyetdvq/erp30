<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const props = withDefaults(
  defineProps<{
    title?: string
    initialLoading?: boolean
    refreshing?: boolean
    hasData?: boolean
    emptyDataset?: boolean
    emptyFilteredResult?: boolean
    error?: unknown | null
    retryable?: boolean
  }>(),
  {
    initialLoading: false,
    refreshing: false,
    hasData: false,
    emptyDataset: false,
    emptyFilteredResult: false,
    retryable: false,
  },
)

const { t } = useI18n()
const emit = defineEmits<{ retry: [] }>()
</script>

<template>
  <section class="list-shell" :aria-busy="props.initialLoading || props.refreshing">
    <header v-if="props.title || $slots.toolbar" class="list-shell__header">
      <h2 v-if="props.title" class="list-shell__title">{{ props.title }}</h2>
      <div v-if="$slots.toolbar" class="list-shell__toolbar"><slot name="toolbar" /></div>
    </header>

    <div
      v-if="props.initialLoading && !props.hasData"
      class="list-shell__state"
      role="status"
      aria-live="polite"
    >
      <slot name="loading">{{ t('list.loading') }}</slot>
    </div>
    <div v-else-if="props.error && !props.hasData" class="list-shell__state" role="alert">
      <slot name="error">{{ t('list.error') }}</slot>
      <button v-if="props.retryable" type="button" class="list-shell__retry" @click="emit('retry')">
        {{ t('list.retry') }}
      </button>
    </div>
    <template v-else-if="props.hasData">
      <div v-if="props.error" class="list-shell__notice" role="alert">
        <slot name="refresh-error">{{ t('list.refreshError') }}</slot>
        <button
          v-if="props.retryable && !props.refreshing"
          type="button"
          class="list-shell__retry"
          @click="emit('retry')"
        >
          {{ t('list.retry') }}
        </button>
      </div>
      <div v-if="props.refreshing" class="list-shell__refreshing" role="status" aria-live="polite">
        {{ t('list.refreshing') }}
      </div>
      <slot />
    </template>
    <div v-else-if="props.emptyFilteredResult" class="list-shell__state" role="status">
      <slot name="empty-filtered">{{ t('list.emptyFiltered') }}</slot>
    </div>
    <div v-else-if="props.emptyDataset" class="list-shell__state" role="status">
      <slot name="empty">{{ t('list.empty') }}</slot>
    </div>
    <slot v-else />
  </section>
</template>

<style scoped>
.list-shell {
  display: grid;
  gap: 16px;
  min-width: 0;
}
.list-shell__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.list-shell__title {
  margin: 0;
  font-size: 18px;
}
.list-shell__toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.list-shell__state,
.list-shell__notice,
.list-shell__refreshing {
  padding: 16px;
  border: 1px solid #dbe5ea;
  border-radius: 6px;
}
.list-shell__notice {
  color: #a23a32;
  background: #fff7f6;
}
.list-shell__refreshing {
  color: #536b7b;
  font-size: 13px;
}
.list-shell__retry {
  display: block;
  margin-top: 8px;
  padding: 4px 0;
  border: 0;
  background: transparent;
  color: #1876a8;
  text-decoration: underline;
  cursor: pointer;
}
.list-shell__retry:focus-visible {
  outline: 2px solid #1876a8;
  outline-offset: 2px;
}
</style>
