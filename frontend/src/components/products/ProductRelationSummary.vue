<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Product } from '@/api/generated/types.gen'

const props = defineProps<{
  kind: 'PARENT' | 'COMBO'
  total: number
  preview: readonly Product[]
  loading: boolean
}>()
const emit = defineEmits<{
  view: [product: Product]
  showAll: []
}>()
const { t } = useI18n()
function productName(product: Product): string {
  return (
    product.full_name || product.product_name || product.product_child_name || t('list.noValue')
  )
}
</script>

<template>
  <div class="relation-summary" :aria-label="t('product.relationSummary')">
    <div class="relation-heading">
      <span class="relation-kind">{{
        t(kind === 'COMBO' ? 'product.components' : 'product.children')
      }}</span>
      <span v-if="!loading" class="relation-count">{{
        t('product.relationCount', { count: total })
      }}</span>
    </div>
    <span v-if="loading" class="relation-muted">{{ t('list.loading') }}</span>
    <span v-else-if="total === 0" class="relation-muted">{{ t('product.relationEmpty') }}</span>
    <div v-else class="relation-preview-items">
      <button
        v-for="product in props.preview.slice(0, 2)"
        :key="String(product.id)"
        class="relation-product"
        type="button"
        :title="productName(product)"
        @click="emit('view', product)"
      >
        {{ productName(product) }}
      </button>
      <span v-if="total > 2" class="relation-overflow">{{
        t('product.relationOthers', { count: total - 2 })
      }}</span>
      <button v-if="total > 2" class="relation-more" type="button" @click="emit('showAll')">
        {{ t('product.viewMore') }}
        <span aria-hidden="true">↗</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.relation-summary {
  display: grid;
  gap: 5px;
  max-width: 360px;
  padding-top: 6px;
  color: var(--ds-text-secondary);
  font-size: 11px;
}
.relation-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.relation-kind {
  font-weight: 650;
}
.relation-count {
  padding: 1px 6px;
  border-radius: 999px;
  background: var(--ds-info-soft);
  color: var(--ds-info);
  font-weight: 650;
  white-space: nowrap;
}
.relation-preview-items {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 7px;
}
.relation-product {
  max-width: 158px;
  padding: 2px 0;
  overflow: hidden;
  border: 0;
  background: transparent;
  color: var(--ds-primary);
  cursor: pointer;
  font-size: inherit;
  text-align: start;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.relation-product:hover,
.relation-product:focus-visible,
.relation-more:hover,
.relation-more:focus-visible {
  text-decoration: underline;
}
.relation-overflow {
  color: var(--ds-text-secondary);
  white-space: nowrap;
}
.relation-more {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 0;
  border: 0;
  background: transparent;
  color: var(--ds-primary);
  font-size: inherit;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}
.relation-muted {
  color: var(--ds-text-muted);
  font-size: inherit;
}
</style>
