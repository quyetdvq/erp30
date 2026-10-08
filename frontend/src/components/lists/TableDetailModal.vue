<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import TableStatus from './TableStatus.vue'

export type TableDetailField = {
  key: string
  label: string
  value?: string | null
  status?: string | boolean
}

const props = defineProps<{
  open: boolean
  title: string
  fields: readonly TableDetailField[]
}>()

const emit = defineEmits<{
  close: []
}>()

const { t } = useI18n()
</script>

<template>
  <div v-if="props.open" class="table-detail-backdrop">
    <section class="table-detail-modal" role="dialog" aria-modal="true" :aria-label="props.title">
      <header class="table-detail-modal__header">
        <h2>{{ props.title }}</h2>
        <button
          type="button"
          class="table-detail-modal__close"
          :aria-label="t('common.close')"
          :title="t('common.close')"
          @click="emit('close')"
        >
          ×
        </button>
      </header>
      <div class="table-detail-modal__body">
        <dl class="table-detail-modal__fields">
          <template v-for="field in props.fields" :key="field.key">
            <dt>{{ field.label }}</dt>
            <dd>
              <TableStatus v-if="field.status !== undefined" :status="field.status" />
              <template v-else>{{ field.value || t('list.noValue') }}</template>
            </dd>
          </template>
        </dl>
      </div>
      <footer class="table-detail-modal__footer">
        <button type="button" class="secondary-button" @click="emit('close')">
          {{ t('common.close') }}
        </button>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.table-detail-backdrop {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(18 38 54 / 45%);
}
.table-detail-modal {
  width: min(100%, 560px);
  max-height: calc(100vh - 40px);
  overflow: auto;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}
.table-detail-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 15px 18px;
  border-bottom: 1px solid #e4ebef;
}
.table-detail-modal__header h2 {
  margin: 0;
  color: #173c59;
  font-size: 18px;
}
.table-detail-modal__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: 0;
  background: transparent;
  color: #536b7b;
  cursor: pointer;
  font-size: 22px;
}
.table-detail-modal__close:hover,
.table-detail-modal__close:focus-visible {
  color: #1876a8;
}
.table-detail-modal__fields {
  display: grid;
  grid-template-columns: minmax(130px, 0.35fr) minmax(0, 1fr);
  gap: 0;
  margin: 0;
  padding: 8px 18px 18px;
}
.table-detail-modal__body {
  max-height: calc(100vh - 180px);
  overflow: auto;
}
.table-detail-modal__footer {
  display: flex;
  justify-content: flex-end;
  padding: 14px 18px 18px;
  border-top: 1px solid #e4ebef;
}
.table-detail-modal__fields dt,
.table-detail-modal__fields dd {
  margin: 0;
  padding: 10px 0;
  border-bottom: 1px solid #edf2f4;
}
.table-detail-modal__fields dt {
  color: #536b7b;
  font-size: 12px;
  font-weight: 700;
}
.table-detail-modal__fields dd {
  min-width: 0;
  color: #183b57;
  overflow-wrap: anywhere;
}
</style>
