<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  ComboItem,
  ComboItemsRequest,
  Product,
  ProductCreate,
  ProductGroup,
  ProductUom,
  ProductUomConversion,
  ProductUomConversionsRequest,
} from '@/api/generated/types.gen'
import DecimalInput from '@/components/forms/DecimalInput.vue'
import RemoteSearchableSelect from '@/components/forms/RemoteSearchableSelect.vue'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import TableStatus from '@/components/lists/TableStatus.vue'
import TableRowActions from '@/components/lists/TableRowActions.vue'
import ProductImageField from '@/components/products/ProductImageField.vue'
import { productHasImage } from '@/services/productImage'
import { useBasePermission } from '@/composables/useBasePermission'
import { useFormatters } from '@/composables/useFormatters'
import {
  getProductById,
  listProductComboItems,
  listProductConversions,
  listProductPage,
  replaceProductComboItems,
  replaceProductConversions,
  saveProduct,
} from '@/services/productCatalog'
import { reportApiError } from '@/services/errorBoundary'
import { useAuthStore } from '@/stores/auth'
import {
  productRoleLabel,
  productUomName,
  productUsageLabel,
  selectableProductUomOptions,
} from '@/services/productPresentation'
import {
  resolveProductOptions,
  searchProductOptions,
  type ProductOption,
} from '@/services/productLookups'

type Tab = 'general' | 'components' | 'children' | 'conversions' | 'shipping'
type ConversionForm = {
  id: string
  uom_id: string
  conversion_factor: string
  price: string
  package_gross_weight_g: string
  package_length_mm: string
  package_width_mm: string
  package_height_mm: string
  usage_type: ProductUomConversion['usage_type']
  status: ProductUomConversion['status']
}
type ComboForm = { id: string; component_product_id: string; quantity: string }

const props = defineProps<{
  open: boolean
  product: Product | null
  groups: readonly ProductGroup[]
  uoms: readonly ProductUom[]
  products: readonly Product[]
}>()
const emit = defineEmits<{
  close: []
  edit: [product: Product]
}>()
const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const auth = useAuthStore()
const formatters = useFormatters()
const currentProduct = ref<Product | null>(null)
const parentProduct = ref<Product | null>(null)
const activeTab = ref<Tab>('general')
const children = ref<Product[]>([])
const conversions = ref<ConversionForm[]>([])
const comboItems = ref<ComboForm[]>([])
const componentMetadata = ref<ProductOption[]>([])
let viewEpoch = 0
const childFormOpen = ref(false)
const childImageField = ref<InstanceType<typeof ProductImageField> | null>(null)
const childImagePartialError = ref(false)
const childForm = ref({
  product_code: '',
  sku: '',
  barcode: '',
  product_child_name: '',
  base_uom_id: '',
  price: '',
})
const conversionEditorOpen = ref(false)
const conversionEditorIndex = ref<number | null>(null)
const conversionDraft = ref<ConversionForm>(emptyConversion())
const busy = ref(false)
const error = ref<unknown | null>(null)
const conversionError = ref<unknown | null>(null)
const comboError = ref<unknown | null>(null)
const childError = ref<unknown | null>(null)
const validationError = ref<string | null>(null)

function emptyConversion(): ConversionForm {
  return {
    id: '',
    uom_id: '',
    conversion_factor: '',
    price: '',
    package_gross_weight_g: '',
    package_length_mm: '',
    package_width_mm: '',
    package_height_mm: '',
    usage_type: 'BOTH',
    status: 'ACTIVE',
  }
}
function text(value: string | number | null | undefined): string {
  return value === null || value === undefined ? '' : String(value)
}
function businessStateLabel(code: string | null | undefined): string {
  if (!code) return t('list.noValue')
  const key = `status.${code.toLowerCase()}`
  const translated = t(key)
  return translated === key ? code : translated
}
function displayMoney(value: string | number | null | undefined): string {
  return value === null || value === undefined || value === ''
    ? t('list.noValue')
    : formatters.formatMoney(String(value))
}
function displayQuantity(value: string | number | null | undefined): string {
  return value === null || value === undefined || value === ''
    ? t('list.noValue')
    : formatters.formatQuantity(String(value))
}
function displayPackage(row: ConversionForm): string {
  return (
    [row.package_gross_weight_g, row.package_length_mm, row.package_width_mm, row.package_height_mm]
      .filter(Boolean)
      .map((value) => displayQuantity(value))
      .join(' / ') || t('list.noValue')
  )
}
function toConversion(row: ProductUomConversion): ConversionForm {
  return {
    id: text(row.id),
    uom_id: text(row.uom_id),
    conversion_factor: text(row.conversion_factor),
    price: text(row.price),
    package_gross_weight_g: text(row.package_gross_weight_g),
    package_length_mm: text(row.package_length_mm),
    package_width_mm: text(row.package_width_mm),
    package_height_mm: text(row.package_height_mm),
    usage_type: row.usage_type,
    status: row.status,
  }
}
function toCombo(row: ComboItem): ComboForm {
  return {
    id: text(row.id),
    component_product_id: text(row.component_product_id),
    quantity: text(row.quantity),
  }
}
const role = computed(() => currentProduct.value?.role)
const isCombo = computed(() => role.value === 'COMBO')
const isParent = computed(() => role.value === 'PARENT')
const canEditCurrent = computed(() => currentProduct.value?.allowed_actions?.edit === true)
const baseUomName = computed(
  () => productUomName(props.uoms, currentProduct.value?.base_uom_id) ?? t('list.noValue'),
)
const groupName = computed(
  () =>
    props.groups.find((row) => String(row.id) === String(currentProduct.value?.product_group_id))
      ?.name ?? t('list.noValue'),
)
const selectedComponentOptions = computed(() =>
  componentMetadata.value
    .filter((row) => String(row.id) !== String(currentProduct.value?.id))
    .map((row) => ({
      value: String(row.id),
      label: `${row.full_name} (${productRoleLabel(row.role, t)})`,
      disabled: row.status !== 'ACTIVE' || !row.selectable,
    })),
)
async function searchComboProducts(q: string, page: number) {
  const response = await searchProductOptions('combo_component', q, page)
  return {
    options: response.data
      .filter((row) => String(row.id) !== String(currentProduct.value?.id))
      .map((row) => ({
        value: String(row.id),
        label: `${row.full_name} (${productRoleLabel(row.role, t)})`,
        disabled: row.status !== 'ACTIVE' || !row.selectable,
      })),
    total: response.meta.total,
  }
}
async function hydrateComponents(ids: readonly string[], epoch: number): Promise<void> {
  const distinct = [...new Set(ids.filter(Boolean))]
  if (!distinct.length) return
  const generation = auth.getSessionGeneration()
  const resolved = await resolveProductOptions(distinct)
  if (epoch !== viewEpoch || generation !== auth.getSessionGeneration()) return
  const merged = new Map(componentMetadata.value.map((row) => [String(row.id), row]))
  for (const row of resolved) merged.set(String(row.id), row)
  componentMetadata.value = [...merged.values()]
}
async function componentChanged(index: number, value: string): Promise<void> {
  const row = comboItems.value[index]
  if (!row) return
  row.component_product_id = value
  if (value) {
    try {
      await hydrateComponents([value], viewEpoch)
    } catch (caught: unknown) {
      comboError.value = caught
      reportApiError(caught)
    }
  }
}
const uomOptions = computed(() =>
  selectableProductUomOptions(props.uoms, childForm.value.base_uom_id),
)
const tabItems = computed(() => {
  const tabs: Array<{ key: Tab; label: string }> = [
    { key: 'general', label: t('product.tabGeneral') },
    { key: 'children', label: t('product.tabChildren') },
  ]
  if (isCombo.value) tabs.splice(1, 0, { key: 'components', label: t('product.tabComponents') })
  if (!isCombo.value && !isParent.value)
    tabs.push({ key: 'conversions', label: t('product.tabConversions') })
  tabs.push({ key: 'shipping', label: t('product.tabShippingPricing') })
  return tabs
})

/** Load only data needed by the tab the user actually opens. */
async function loadTabData(): Promise<void> {
  const row = currentProduct.value
  if (!props.open || !row) return
  const productId = String(row.id)
  const tab = activeTab.value
  const epoch = ++viewEpoch
  const generation = auth.getSessionGeneration()
  const live = () =>
    epoch === viewEpoch &&
    generation === auth.getSessionGeneration() &&
    props.open &&
    String(currentProduct.value?.id) === productId &&
    activeTab.value === tab
  error.value = null
  try {
    if (tab === 'children' && (row.role === 'PARENT' || row.role === 'SIMPLE')) {
      const result = (
        await listProductPage({
          q: '',
          page: 1,
          page_size: 200,
          sort: 'product_name',
          filter: { parent_product_id: productId },
        })
      ).data
      if (live()) children.value = result
    } else if (tab === 'components' && row.role === 'COMBO') {
      const items = (await listProductComboItems(productId)).map(toCombo)
      if (!live()) return
      comboItems.value = items
      componentMetadata.value = []
      await hydrateComponents(
        items.map((item) => item.component_product_id),
        epoch,
      )
    } else if (tab === 'conversions' && row.role !== 'COMBO' && row.role !== 'PARENT') {
      const result = (await listProductConversions(productId)).map(toConversion)
      if (live()) conversions.value = result
    }
  } catch (caught: unknown) {
    if (!live()) return
    error.value = caught
    reportApiError(caught)
  }
}
async function openChild(row: Product): Promise<void> {
  const epoch = ++viewEpoch
  const generation = auth.getSessionGeneration()
  try {
    const resolved = await getProductById(String(row.id))
    if (epoch !== viewEpoch || !props.open || generation !== auth.getSessionGeneration()) return
    parentProduct.value = currentProduct.value
    currentProduct.value = resolved
    activeTab.value = 'general'
  } catch (caught: unknown) {
    if (epoch !== viewEpoch) return
    error.value = caught
    reportApiError(caught)
  }
}
function backToParent(): void {
  if (!parentProduct.value) return
  currentProduct.value = parentProduct.value
  parentProduct.value = null
  activeTab.value = 'children'
}
function editCurrent(): void {
  if (currentProduct.value?.allowed_actions?.edit === true) emit('edit', currentProduct.value)
}
function resetChildForm(): void {
  childForm.value = {
    product_code: '',
    sku: '',
    barcode: '',
    product_child_name: '',
    base_uom_id: '',
    price: '',
  }
}
function openChildForm(): void {
  resetChildForm()
  childError.value = null
  childImagePartialError.value = false
  childFormOpen.value = true
}
async function createChild(): Promise<void> {
  if (!currentProduct.value) return
  childError.value = null
  if (
    !childForm.value.product_child_name.trim() ||
    !childForm.value.sku.trim() ||
    !childForm.value.base_uom_id ||
    !childForm.value.price
  ) {
    childError.value = new Error(t('product.childFieldsRequired'))
    return
  }
  busy.value = true
  try {
    const payload: ProductCreate = {
      product_code: childForm.value.product_code || null,
      sku: childForm.value.sku,
      barcode: childForm.value.barcode || null,
      product_type: 'STANDARD',
      parent_product_id: String(currentProduct.value.id),
      product_child_name: childForm.value.product_child_name,
      base_uom_id: childForm.value.base_uom_id,
      price: childForm.value.price,
      serial_management_enabled: false,
    }
    const saved = await saveProduct(payload)
    try {
      await childImageField.value?.commit(String(saved.id))
    } catch {
      // Product child exists. Close CREATE to prevent duplicate submission.
      childImagePartialError.value = true
      error.value = new Error(t('product.imagePartialSave'))
    }
    childFormOpen.value = false
    await loadTabData()
  } catch (caught: unknown) {
    childError.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
function decimalPositive(value: string): boolean {
  return /^\d+(?:\.\d+)?$/.test(value) && !/^0+(?:\.0+)?$/.test(value)
}
function openConversion(index?: number): void {
  validationError.value = null
  conversionEditorIndex.value = index === undefined ? null : index
  conversionDraft.value = index === undefined ? emptyConversion() : { ...conversions.value[index]! }
  conversionEditorOpen.value = true
}
function saveConversionDraft(): void {
  validationError.value = null
  if (
    !conversionDraft.value.uom_id ||
    conversionDraft.value.uom_id === String(currentProduct.value?.base_uom_id)
  )
    validationError.value = t('product.alternateUomRequired')
  else if (!decimalPositive(conversionDraft.value.conversion_factor))
    validationError.value = t('product.factorRequired')
  else if (
    (conversionDraft.value.usage_type === 'SALES' || conversionDraft.value.usage_type === 'BOTH') &&
    !conversionDraft.value.price
  )
    validationError.value = t('product.priceRequired')
  if (validationError.value) return
  if (conversionEditorIndex.value === null) conversions.value.push({ ...conversionDraft.value })
  else conversions.value[conversionEditorIndex.value] = { ...conversionDraft.value }
  conversionEditorOpen.value = false
}
async function saveConversions(): Promise<void> {
  if (!currentProduct.value) return
  busy.value = true
  try {
    const body: ProductUomConversionsRequest = conversions.value.map((row) => ({
      uom_id: row.uom_id,
      conversion_factor: row.conversion_factor,
      ...(row.price ? { price: row.price } : {}),
      ...(row.package_gross_weight_g ? { package_gross_weight_g: row.package_gross_weight_g } : {}),
      ...(row.package_length_mm ? { package_length_mm: row.package_length_mm } : {}),
      ...(row.package_width_mm ? { package_width_mm: row.package_width_mm } : {}),
      ...(row.package_height_mm ? { package_height_mm: row.package_height_mm } : {}),
      usage_type: row.usage_type,
      status: row.status,
    }))
    await replaceProductConversions(String(currentProduct.value.id), body)
    conversions.value = (await listProductConversions(String(currentProduct.value.id))).map(
      toConversion,
    )
  } catch (caught: unknown) {
    conversionError.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
function removeConversion(index: number): void {
  conversions.value = conversions.value.filter((_, current) => current !== index)
}
function findComponent(id: string): ProductOption | Product | undefined {
  return (
    componentMetadata.value.find((row) => String(row.id) === id) ??
    props.products.find((row) => String(row.id) === id)
  )
}
function componentName(id: string): string {
  return findComponent(id)?.full_name ?? t('list.noValue')
}
function componentRole(id: string): string {
  const role = findComponent(id)?.role
  return role ? productRoleLabel(role, t) : t('list.noValue')
}
function componentUom(id: string): string {
  const product = findComponent(id)
  return productUomName(props.uoms, product?.base_uom_id) ?? t('list.noValue')
}
function addComboItem(): void {
  comboItems.value.push({ id: '', component_product_id: '', quantity: '' })
}
function removeComboItem(index: number): void {
  comboItems.value = comboItems.value.filter((_, current) => current !== index)
}
async function saveComboItems(): Promise<void> {
  if (!currentProduct.value) return
  comboError.value = null
  if (comboItems.value.some((row) => !row.component_product_id || !decimalPositive(row.quantity))) {
    comboError.value = new Error(t('product.componentFieldsRequired'))
    return
  }
  busy.value = true
  try {
    const body: ComboItemsRequest = comboItems.value.map((row) => ({
      component_product_id: row.component_product_id,
      quantity: row.quantity,
    }))
    await replaceProductComboItems(String(currentProduct.value.id), body)
    comboItems.value = (await listProductComboItems(String(currentProduct.value.id))).map(toCombo)
    await hydrateComponents(
      comboItems.value.map((row) => row.component_product_id),
      viewEpoch,
    )
  } catch (caught: unknown) {
    comboError.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
watch(
  () => [props.open, props.product?.id] as const,
  ([open]) => {
    ++viewEpoch
    if (!open || !props.product) {
      currentProduct.value = null
      componentMetadata.value = []
      return
    }
    currentProduct.value = props.product
    parentProduct.value = null
    activeTab.value = 'general'
    childFormOpen.value = false
    childImagePartialError.value = false
    children.value = []
    comboItems.value = []
    conversions.value = []
  },
)
watch(
  () => [props.open, currentProduct.value?.id, activeTab.value] as const,
  ([open, productId, tab]) => {
    ++viewEpoch
    if (open && productId && tab !== 'general' && tab !== 'shipping') void loadTabData()
  },
)
</script>

<template>
  <div v-if="props.open" class="detail-backdrop">
    <section
      class="detail-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="t('product.productDetails')"
    >
      <header class="detail-header">
        <div>
          <p class="eyebrow">
            {{
              currentProduct?.role ? productRoleLabel(currentProduct.role, t) : t('list.noValue')
            }}
          </p>
          <h2>{{ currentProduct?.full_name }}</h2>
        </div>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          ×
        </button>
      </header>
      <div v-if="parentProduct" class="detail-back">
        <button type="button" class="secondary-button" @click="backToParent">
          {{ t('product.backToParent') }}
        </button>
      </div>
      <nav class="detail-tabs" :aria-label="t('product.detailTabs')">
        <button
          v-for="tab in tabItems"
          :key="tab.key"
          type="button"
          :class="{ active: activeTab === tab.key }"
          :aria-selected="activeTab === tab.key"
          role="tab"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>
      <div class="detail-body">
        <p v-if="error && !childImagePartialError" class="inline-error" role="alert">
          {{ t('product.requestError') }}
        </p>
        <p v-if="childImagePartialError" class="inline-error" role="alert">
          {{ t('product.imagePartialSave') }}
        </p>
        <section v-if="activeTab === 'general'" class="tab-panel">
          <ProductImageField
            :product-id="currentProduct ? String(currentProduct.id) : undefined"
            :image-available="productHasImage(currentProduct)"
            :image-path="currentProduct?.image_url"
          />
          <dl class="detail-grid">
            <dt>{{ t('product.productCode') }}</dt>
            <dd>{{ currentProduct?.product_code ?? t('list.noValue') }}</dd>
            <dt>{{ t('product.fullName') }}</dt>
            <dd>{{ currentProduct?.full_name }}</dd>
            <dt>{{ t('product.type') }}</dt>
            <dd>
              {{
                currentProduct?.product_type === 'COMBO'
                  ? t('product.combo')
                  : t('product.standard')
              }}
            </dd>
            <dt>{{ t('product.role') }}</dt>
            <dd>
              {{
                currentProduct?.role ? productRoleLabel(currentProduct.role, t) : t('list.noValue')
              }}
            </dd>
            <dt>{{ t('product.sku') }}</dt>
            <dd>{{ currentProduct?.sku ?? t('list.noValue') }}</dd>
            <dt>{{ t('product.productGroup') }}</dt>
            <dd>{{ groupName }}</dd>
            <dt>{{ t('product.baseUom') }}</dt>
            <dd>{{ baseUomName }}</dd>
            <dt>{{ t('product.status') }}</dt>
            <dd>
              {{ businessStateLabel(currentProduct?.status) }}
            </dd>
          </dl>
        </section>
        <section v-else-if="activeTab === 'components'" class="tab-panel">
          <header class="section-header">
            <h3>{{ t('product.comboComponents') }}</h3>
            <button
              v-if="canEditCurrent"
              type="button"
              class="secondary-button"
              @click="addComboItem"
            >
              {{ t('product.addComponent') }}
            </button>
          </header>
          <p v-if="comboError" class="inline-error" role="alert">{{ t('product.requestError') }}</p>
          <div class="table-wrap">
            <table class="detail-table">
              <thead>
                <tr>
                  <th>{{ t('product.componentProduct') }}</th>
                  <th>{{ t('product.role') }}</th>
                  <th>{{ t('product.quantity') }}</th>
                  <th>{{ t('product.baseUom') }}</th>
                  <th>{{ t('product.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in comboItems" :key="row.id || index">
                  <td>
                    <RemoteSearchableSelect
                      :model-value="row.component_product_id"
                      :selected-options="selectedComponentOptions"
                      :search="searchComboProducts"
                      :aria-label="t('product.componentProduct')"
                      @update:model-value="(value) => void componentChanged(index, value)"
                    />
                  </td>
                  <td>
                    {{ componentName(row.component_product_id)
                    }}<small>{{ componentRole(row.component_product_id) }}</small>
                  </td>
                  <td>
                    <DecimalInput
                      v-model="row.quantity"
                      :max-decimals="6"
                      :aria-label="t('product.quantity')"
                    />
                  </td>
                  <td>{{ componentUom(row.component_product_id) }}</td>
                  <td>
                    <button
                      v-if="canEditCurrent"
                      type="button"
                      class="icon-button danger"
                      :aria-label="t('common.delete')"
                      @click="removeComboItem(index)"
                    >
                      {{ t('common.delete') }}
                    </button>
                  </td>
                </tr>
                <tr v-if="comboItems.length === 0">
                  <td colspan="5">{{ t('list.noResults') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <button
            v-if="canEditCurrent"
            type="button"
            class="primary-button"
            :disabled="busy"
            @click="saveComboItems"
          >
            {{ t('common.save') }}
          </button>
        </section>
        <section v-else-if="activeTab === 'children'" class="tab-panel">
          <header class="section-header">
            <h3>{{ t('product.tabChildren') }}</h3>
            <button
              v-if="(isParent || role === 'SIMPLE') && hasBasePermission('product', 'create')"
              type="button"
              class="secondary-button"
              @click="openChildForm"
            >
              {{ t('product.addChild') }}
            </button>
          </header>
          <div class="table-wrap">
            <table class="detail-table">
              <thead>
                <tr>
                  <th>{{ t('product.productCode') }}</th>
                  <th>{{ t('product.fullName') }}</th>
                  <th>{{ t('product.sku') }}</th>
                  <th>{{ t('product.baseUom') }}</th>
                  <th>{{ t('product.defaultPrice') }}</th>
                  <th>{{ t('product.status') }}</th>
                  <th>{{ t('product.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in children" :key="String(row.id)">
                  <td>{{ row.product_code ?? t('list.noValue') }}</td>
                  <td>
                    <button type="button" class="link-button" @click="openChild(row)">
                      {{ row.full_name }}
                    </button>
                  </td>
                  <td>{{ row.sku ?? t('list.noValue') }}</td>
                  <td>
                    {{ productUomName(props.uoms, row.base_uom_id) ?? t('list.noValue') }}
                  </td>
                  <td class="child-price">{{ displayMoney(row.price) }}</td>
                  <td class="child-state">
                    <span class="child-status-label">
                      <TableStatus :status="row.status" />
                      <span>{{ businessStateLabel(row.status) }}</span>
                    </span>
                  </td>
                  <td class="child-actions">
                    <TableRowActions
                      :show-view="true"
                      :show-edit="row.allowed_actions?.edit === true"
                      :busy="busy"
                      :view-label="`${t('common.view')} ${row.full_name}`"
                      :edit-label="`${t('common.edit')} ${row.full_name}`"
                      @view="openChild(row)"
                      @edit="emit('edit', row)"
                    />
                  </td>
                </tr>
                <tr v-if="children.length === 0">
                  <td colspan="7">{{ t('list.noResults') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="field-note">{{ t('product.deleteChildUnavailable') }}</p>
        </section>
        <section v-else-if="activeTab === 'conversions'" class="tab-panel">
          <header class="section-header">
            <div>
              <h3>{{ t('product.conversions') }}</h3>
              <p class="field-note">1 {{ t('product.alternateUom') }} = factor {{ baseUomName }}</p>
            </div>
            <button
              v-if="canEditCurrent"
              type="button"
              class="secondary-button"
              @click="openConversion()"
            >
              {{ t('product.addConversion') }}
            </button>
          </header>
          <p v-if="conversionError" class="inline-error" role="alert">
            {{ t('product.requestError') }}
          </p>
          <div class="table-wrap">
            <table class="detail-table">
              <thead>
                <tr>
                  <th>{{ t('product.alternateUom') }}</th>
                  <th>{{ t('product.conversionFactor') }}</th>
                  <th>{{ t('product.usage') }}</th>
                  <th>{{ t('product.sellingPrice') }}</th>
                  <th>{{ t('product.package') }}</th>
                  <th>{{ t('product.status') }}</th>
                  <th>{{ t('product.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in conversions" :key="row.id || index">
                  <td>
                    {{ productUomName(props.uoms, row.uom_id) ?? t('list.noValue') }}
                  </td>
                  <td>
                    1 {{ productUomName(props.uoms, row.uom_id) ?? '' }} =
                    {{ displayQuantity(row.conversion_factor) }} {{ baseUomName }}
                  </td>
                  <td>{{ productUsageLabel(row.usage_type, t) }}</td>
                  <td>{{ displayMoney(row.price) }}</td>
                  <td>{{ displayPackage(row) }}</td>
                  <td>{{ row.status === 'ACTIVE' ? t('status.active') : t('status.inactive') }}</td>
                  <td>
                    <button
                      v-if="canEditCurrent"
                      type="button"
                      class="icon-button"
                      @click="openConversion(index)"
                    >
                      {{ t('common.edit') }}</button
                    ><button
                      v-if="canEditCurrent"
                      type="button"
                      class="icon-button danger"
                      :aria-label="t('common.delete')"
                      @click="removeConversion(index)"
                    >
                      {{ t('common.delete') }}
                    </button>
                  </td>
                </tr>
                <tr v-if="conversions.length === 0">
                  <td colspan="7">{{ t('list.noResults') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <button
            v-if="canEditCurrent"
            type="button"
            class="primary-button"
            :disabled="busy"
            @click="saveConversions"
          >
            {{ t('common.save') }}
          </button>
        </section>
        <section v-else class="tab-panel">
          <dl class="detail-grid">
            <dt>{{ t('product.netWeight') }}</dt>
            <dd>{{ displayQuantity(currentProduct?.net_weight_g) }} g</dd>
            <dt>{{ t('product.packageGrossWeight') }}</dt>
            <dd>{{ displayQuantity(currentProduct?.package_gross_weight_g) }} g</dd>
            <dt>{{ t('product.packageLength') }}</dt>
            <dd>{{ displayQuantity(currentProduct?.package_length_mm) }} mm</dd>
            <dt>{{ t('product.packageWidth') }}</dt>
            <dd>{{ displayQuantity(currentProduct?.package_width_mm) }} mm</dd>
            <dt>{{ t('product.packageHeight') }}</dt>
            <dd>{{ displayQuantity(currentProduct?.package_height_mm) }} mm</dd>
            <dt>{{ t('product.defaultPrice') }}</dt>
            <dd>{{ displayMoney(currentProduct?.price) }}</dd>
          </dl>
        </section>
      </div>
      <footer class="detail-footer">
        <button
          v-if="canEditCurrent"
          type="button"
          class="secondary-button"
          @click="editCurrent"
        >
          {{ t('common.edit') }}</button
        ><button type="button" class="primary-button" @click="emit('close')">
          {{ t('common.close') }}
        </button>
      </footer>
    </section>
  </div>
  <div v-if="childFormOpen" class="nested-backdrop">
    <section
      class="child-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="t('product.childForm')"
    >
      <header>
        <h3>{{ t('product.addChild') }}</h3>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="childFormOpen = false"
        >
          ×
        </button>
      </header>
      <label
        ><span>{{ t('product.productCode') }}</span
        ><input v-model="childForm.product_code" /></label
      ><label
        ><span>{{ t('product.sku') }}</span
        ><input v-model="childForm.sku" required /></label
      ><label
        ><span>{{ t('product.barcode') }}</span
        ><input v-model="childForm.barcode" /></label
      ><label
        ><span>{{ t('product.childName') }}</span
        ><input v-model="childForm.product_child_name" required /></label
      ><label
        ><span>{{ t('product.baseUom') }}</span
        ><SearchableSelect
          :model-value="childForm.base_uom_id"
          :options="uomOptions"
          :aria-label="t('product.baseUom')"
          @update:model-value="
            (value) => (childForm.base_uom_id = typeof value === 'string' ? value : '')
          " /></label
      ><label
        ><span>{{ t('product.defaultPrice') }}</span
        ><DecimalInput
          v-model="childForm.price"
          kind="money"
          :max-decimals="2"
          :aria-label="t('product.defaultPrice')"
          required
      /></label>
      <ProductImageField
        ref="childImageField"
        :editable="hasBasePermission('product', 'create')"
        :disabled="busy"
      />
      <p v-if="childError" class="inline-error" role="alert">
        {{ t('product.childFieldsRequired') }}
      </p>
      <footer>
        <button type="button" class="secondary-button" @click="childFormOpen = false">
          {{ t('common.cancel') }}</button
        ><button type="button" class="primary-button" :disabled="busy" @click="createChild">
          {{ t('common.save') }}
        </button>
      </footer>
    </section>
  </div>
  <div v-if="conversionEditorOpen" class="nested-backdrop">
    <section
      class="child-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="t('product.conversionForm')"
    >
      <header>
        <h3>{{ t('product.conversionForm') }}</h3>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="conversionEditorOpen = false"
        >
          ×
        </button>
      </header>
      <label
        ><span>{{ t('product.alternateUom') }}</span
        ><SearchableSelect
          :model-value="conversionDraft.uom_id"
          :options="
            uomOptions.filter((option) => option.value !== String(currentProduct?.base_uom_id))
          "
          :aria-label="t('product.alternateUom')"
          @update:model-value="
            (value) => (conversionDraft.uom_id = typeof value === 'string' ? value : '')
          " /></label
      ><label
        ><span>{{ t('product.conversionFactor') }}</span
        ><DecimalInput
          v-model="conversionDraft.conversion_factor"
          :max-decimals="6"
          :aria-label="t('product.conversionFactor')" /></label
      ><label
        ><span>{{ t('product.usage') }}</span
        ><select v-model="conversionDraft.usage_type">
          <option value="PURCHASE">{{ t('product.usagePurchase') }}</option>
          <option value="SALES">{{ t('product.usageSales') }}</option>
          <option value="BOTH">{{ t('product.usageBoth') }}</option>
        </select></label
      ><label
        ><span>{{ t('product.sellingPrice') }}</span
        ><DecimalInput
          v-model="conversionDraft.price"
          kind="money"
          :max-decimals="2"
          :aria-label="t('product.sellingPrice')" /></label
      ><label
        ><span>{{ t('product.packageGrossWeight') }}</span
        ><DecimalInput
          v-model="conversionDraft.package_gross_weight_g"
          :max-decimals="3"
          :aria-label="t('product.packageGrossWeight')" /></label
      ><label
        ><span>{{ t('product.packageLength') }}</span
        ><DecimalInput
          v-model="conversionDraft.package_length_mm"
          :max-decimals="3"
          :aria-label="t('product.packageLength')" /></label
      ><label
        ><span>{{ t('product.packageWidth') }}</span
        ><DecimalInput
          v-model="conversionDraft.package_width_mm"
          :max-decimals="3"
          :aria-label="t('product.packageWidth')" /></label
      ><label
        ><span>{{ t('product.packageHeight') }}</span
        ><DecimalInput
          v-model="conversionDraft.package_height_mm"
          :max-decimals="3"
          :aria-label="t('product.packageHeight')" /></label
      ><label
        ><span>{{ t('product.status') }}</span
        ><select v-model="conversionDraft.status">
          <option value="ACTIVE">{{ t('status.active') }}</option>
          <option value="INACTIVE">{{ t('status.inactive') }}</option>
        </select></label
      >
      <p v-if="validationError" class="inline-error" role="alert">{{ validationError }}</p>
      <footer>
        <button type="button" class="secondary-button" @click="conversionEditorOpen = false">
          {{ t('common.cancel') }}</button
        ><button type="button" class="primary-button" @click="saveConversionDraft">
          {{ t('common.save') }}
        </button>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.detail-backdrop,
.nested-backdrop {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: grid;
  place-items: center;
  padding: 18px;
  background: rgb(18 38 54 / 48%);
}
.detail-modal {
  width: min(1100px, 100%);
  max-height: calc(100vh - 36px);
  overflow: hidden;
  display: grid;
  grid-template-rows: auto auto auto 1fr auto;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}
.detail-header,
.detail-footer,
.section-header,
.child-modal header,
.child-modal footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.detail-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e4ebef;
}
.detail-header h2,
.child-modal h3 {
  margin: 0;
  color: #173c59;
}
.eyebrow {
  margin: 0 0 4px;
  color: #1876a8;
  font-size: 11px;
  font-weight: 700;
}
.detail-back {
  padding: 10px 20px 0;
}
.detail-tabs {
  display: flex;
  gap: 4px;
  overflow-x: auto;
  padding: 12px 20px 0;
  border-bottom: 1px solid #dbe5ea;
}
.detail-tabs button {
  border: 0;
  border-bottom: 3px solid transparent;
  padding: 10px 12px;
  background: transparent;
  color: #536b7b;
  cursor: pointer;
  white-space: nowrap;
}
.detail-tabs button.active {
  border-bottom-color: #1876a8;
  color: #173c59;
  font-weight: 700;
}
.detail-body {
  overflow: auto;
  padding: 18px 20px;
}
.tab-panel {
  display: grid;
  gap: 14px;
}
.detail-grid {
  display: grid;
  grid-template-columns: minmax(150px, 0.35fr) minmax(0, 1fr);
  margin: 0;
}
.detail-grid dt,
.detail-grid dd {
  margin: 0;
  padding: 10px 0;
  border-bottom: 1px solid #edf2f4;
  overflow-wrap: anywhere;
}
.detail-grid dt {
  color: #536b7b;
  font-size: 12px;
  font-weight: 700;
}
.detail-grid dd {
  color: #183b57;
}
.table-wrap {
  overflow-x: auto;
  border: 1px solid #dbe5ea;
  border-radius: 6px;
}
.detail-table {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
  color: #183b57;
  font-size: 13px;
}
.detail-table th,
.detail-table td {
  padding: 9px 10px;
  border-bottom: 1px solid #e8eef1;
  text-align: start;
  vertical-align: middle;
}
.detail-table th {
  background: #f7fafb;
  color: #536b7b;
  font-size: 12px;
}
.child-price {
  text-align: right !important;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.child-state,
.child-actions {
  text-align: center !important;
  white-space: nowrap;
}
.child-status-label {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
}
.detail-table tbody tr:hover {
  background: #f8fbfd;
}
.detail-table tbody tr:last-child td {
  border-bottom: 0;
}
.detail-table small {
  display: block;
  color: #536b7b;
}
.detail-footer {
  justify-content: flex-end;
  padding: 14px 20px 18px;
  border-top: 1px solid #e4ebef;
}
.icon-button,
.link-button {
  border: 0;
  background: transparent;
  color: #1876a8;
  cursor: pointer;
}
.danger {
  color: #b64444;
}
.inline-error {
  margin: 0;
  color: #b64444;
  font-size: 12px;
}
.field-note {
  margin: 0;
  color: #536b7b;
  font-size: 12px;
}
.child-modal {
  width: min(460px, 100%);
  max-height: calc(100vh - 36px);
  overflow: auto;
  display: grid;
  gap: 12px;
  border-radius: 7px;
  background: #fff;
  padding: 20px;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}
.child-modal label {
  display: grid;
  gap: 5px;
  color: #536b7b;
  font-size: 12px;
  font-weight: 600;
}
.child-modal input,
.child-modal select {
  min-height: 34px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 9px;
  font: inherit;
}
.child-modal footer {
  justify-content: flex-end;
  margin-top: 4px;
}
</style>
