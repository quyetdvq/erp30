<script setup lang="ts">
import AppDialogHost from '@/components/ui/AppDialogHost.vue'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ComboItem, Product, ProductUom } from '@/api/generated/types.gen'
import { useFormatters } from '@/composables/useFormatters'
import { productRoleLabel } from '@/services/productPresentation'
import TableStatus from '@/components/lists/TableStatus.vue'

const props = defineProps<{
  open: boolean
  owner: Product | null
  products: readonly Product[]
  total: number
  comboItems: readonly ComboItem[]
  loading: boolean
  error: boolean
  uoms: readonly ProductUom[]
}>()
const emit = defineEmits<{
  close: []
  retry: []
  view: [product: Product]
}>()
const { t } = useI18n()
const formatters = useFormatters()
const closeButton = ref<HTMLButtonElement | null>(null)
const page = ref(1)
const pageSize = 12
const isCombo = computed(() => props.owner?.role === 'COMBO')
const kindLabel = computed(() => t(isCombo.value ? 'product.components' : 'product.children'))
const title = computed(() =>
  t('product.relationFullTitle', {
    kind: kindLabel.value,
    product: props.owner?.full_name || props.owner?.product_name || '',
  }),
)
const uomById = computed(() => new Map(props.uoms.map((uom) => [String(uom.id), uom.name])))
const quantitiesById = computed(
  () => new Map(props.comboItems.map((item) => [String(item.component_product_id), item.quantity])),
)
const pages = computed(() => Math.max(1, Math.ceil(props.products.length / pageSize)))
const currentPage = computed(() => Math.min(page.value, pages.value))
const from = computed(() => (props.products.length ? (currentPage.value - 1) * pageSize + 1 : 0))
const to = computed(() => Math.min(currentPage.value * pageSize, props.products.length))
const pageProducts = computed(() => props.products.slice(from.value ? from.value - 1 : 0, to.value))
function productName(product: Product): string {
  return (
    product.full_name || product.product_name || product.product_child_name || t('list.noValue')
  )
}
function quantity(product: Product): string {
  const value = quantitiesById.value.get(String(product.id))
  return value === null || value === undefined
    ? t('list.noValue')
    : formatters.formatQuantity(String(value))
}
function uom(product: Product): string {
  return uomById.value.get(String(product.base_uom_id)) ?? t('list.noValue')
}
function stateLabel(status: string): string {
  const key = `status.${status.toLowerCase()}`
  const translated = t(key)
  return translated === key ? status : translated
}
watch(
  () => [props.open, props.owner?.id] as const,
  async ([open]) => {
    page.value = 1
    if (open) {
      await nextTick()
      closeButton.value?.focus()
    }
  },
)
</script>

<template>

    <AppDialogHost :open="open" size="lg" :mask-closable="true" @close="emit('close')">
      <section v-if="open"
        class="ds-dialog-surface relation-dialog"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
      >
        <header class="relation-dialog-header">
          <div>
            <p class="relation-eyebrow">{{ kindLabel }}</p>
            <h2>{{ title }}</h2>
            <p class="relation-subtitle">
              {{ t('product.relationCount', { count: loading ? total : products.length }) }}
            </p>
          </div>
          <button
            ref="closeButton"
            type="button"
            class="modal-card-button-close"
            :aria-label="t('common.close')"
            @click="emit('close')"
           data-ds-icon="close"></button>
        </header>
        <div class="relation-dialog-body">
          <p v-if="loading" class="relation-empty" role="status">{{ t('list.loading') }}</p>
          <p v-else-if="error" class="relation-empty" role="alert">
            {{ t('product.requestError') }}
            <button type="button" class="relation-retry ds-button ds-button--secondary" data-ds-icon="retry" @click="emit('retry')">
              {{ t('list.retry') }}
            </button>
          </p>
          <p v-else-if="products.length === 0" class="relation-empty">
            {{ t('product.relationEmpty') }}
          </p>
          <div v-else class="relation-table-scroll">
            <table class="relation-table">
              <thead>
                <tr>
                  <th>{{ t('product.productCode') }}</th>
                  <th>{{ t('product.fullName') }}</th>
                  <th>{{ t('product.sku') }}</th>
                  <th v-if="isCombo">{{ t('product.role') }}</th>
                  <th v-if="isCombo">{{ t('product.quantity') }}</th>
                  <th>{{ t('product.baseUom') }}</th>
                  <th>{{ t('product.status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product in pageProducts" :key="String(product.id)">
                  <td class="relation-code">{{ product.product_code || t('list.noValue') }}</td>
                  <td class="relation-name-cell">
                    <button type="button" class="relation-name" @click="emit('view', product)">
                      {{ productName(product) }}
                    </button>
                  </td>
                  <td class="relation-code">{{ product.sku || t('list.noValue') }}</td>
                  <td v-if="isCombo">{{ productRoleLabel(product.role, t) }}</td>
                  <td v-if="isCombo" class="relation-quantity">{{ quantity(product) }}</td>
                  <td>{{ uom(product) }}</td>
                  <td>
                    <span class="relation-status">
                      <TableStatus :status="product.status" />
                      {{ stateLabel(product.status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <footer class="relation-dialog-footer">
          <span v-if="!loading && products.length > 0" class="relation-page-info">
            {{ t('product.relationPageInfo', { from, to, total: products.length }) }}
          </span>
          <div v-if="!loading && pages > 1" class="relation-pagination">
            <button type="button" class="ds-button ds-button--secondary ds-button--sm" data-ds-icon="previous" :disabled="currentPage <= 1" @click="page = currentPage - 1">
              {{ t('list.previous') }}
            </button>
            <span>{{ currentPage }}/{{ pages }}</span>
            <button type="button" class="ds-button ds-button--secondary ds-button--sm" data-ds-icon="next" :disabled="currentPage >= pages" @click="page = currentPage + 1">
              {{ t('list.next') }}
            </button>
          </div>
          <button type="button" class="relation-done ds-button ds-button--secondary" data-ds-icon="close" @click="emit('close')">
            {{ t('common.close') }}
          </button>
        </footer>
      </section>
    </AppDialogHost>

</template>

<style scoped>
.relation-dialog-header {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  padding: 22px 26px 18px;
  border-bottom: 1px solid var(--ds-border-subtle);
}
.relation-eyebrow {
  margin: 0 0 6px;
  color: var(--ds-primary);
  font-size: 12px;
  font-weight: 700;
}
.relation-dialog-header h2 {
  margin: 0;
  font-size: 20px;
  line-height: 1.3;
  overflow-wrap: anywhere;
}
.relation-subtitle {
  margin: 8px 0 0;
  color: var(--ds-text-secondary);
  font-size: 13px;
}
.relation-dialog-body {
  min-height: 100px;
  overflow: auto;
  padding: 18px 26px;
}
.relation-table-scroll {
  overflow: auto;
  border: 1px solid var(--ds-border-subtle);
  border-radius: 8px;
}
.relation-table {
  width: 100%;
  min-width: 780px;
  border-collapse: collapse;
  font-size: 13px;
}
.relation-table th,
.relation-table td {
  padding: 12px 13px;
  border-bottom: 1px solid var(--ds-border-subtle);
  text-align: start;
  vertical-align: middle;
}
.relation-table th {
  position: sticky;
  top: 0;
  background: var(--ds-bg-subtle);
  color: var(--ds-text-secondary);
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}
.relation-table tbody tr:last-child td {
  border-bottom: 0;
}
.relation-table tbody tr:hover {
  background: var(--ds-bg-subtle);
}
.relation-name-cell {
  min-width: 240px;
}
.relation-name {
  padding: 0;
  border: 0;
  background: none;
  color: var(--ds-primary);
  font: inherit;
  font-weight: 600;
  text-align: start;
  cursor: pointer;
}
.relation-name:hover,
.relation-name:focus-visible {
  text-decoration: underline;
}
.relation-code {
  white-space: nowrap;
  color: var(--ds-text-secondary);
  font-variant-numeric: tabular-nums;
}
.relation-quantity {
  text-align: end !important;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.relation-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}
.relation-empty {
  margin: 0;
  padding: 36px 12px;
  color: var(--ds-text-secondary);
  text-align: center;
}
.relation-dialog-footer {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 26px;
  border-top: 1px solid var(--ds-border-subtle);
}
.relation-page-info {
  color: var(--ds-text-secondary);
  font-size: 12px;
}
.relation-pagination {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-inline-start: auto;
  font-size: 12px;
}
.relation-done { margin-inline-start: auto; }
@media (max-width: 700px) {
  .relation-dialog-header,
  .relation-dialog-footer {
    padding-inline: 16px;
  }
  .relation-dialog-body {
    padding: 12px;
  }
  .relation-dialog-footer {
    flex-wrap: wrap;
  }
}
</style>
