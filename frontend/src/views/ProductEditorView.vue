<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import type {
  ComboItem,
  ComboItemsRequest,
  Product,
  ProductCreate,
  ProductGroup,
  ProductPatch,
  ProductUom,
  ProductUomConversion,
  ProductUomConversionsRequest,
} from '@/api/generated/types.gen'
import DecimalInput from '@/components/forms/DecimalInput.vue'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import { useBasePermission } from '@/composables/useBasePermission'
import { useFormatters } from '@/composables/useFormatters'
import {
  changeProductState,
  getProductById,
  listProductComboItems,
  listProductConversions,
  listProductGroupPage,
  listProductPage,
  listProductUomPage,
  replaceProductComboItems,
  replaceProductConversions,
  saveProduct,
} from '@/services/productCatalog'
import { reportApiError } from '@/services/errorBoundary'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { loadLookupPages } from '@/services/listLookups'

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
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { hasBasePermission } = useBasePermission()
const formatters = useFormatters()
const id = computed(() => (typeof route.params.id === 'string' ? route.params.id : undefined))
const editing = computed(() => Boolean(id.value))
const product = ref<Product | null>(null)
const groups = ref<ProductGroup[]>([])
const uoms = ref<ProductUom[]>([])
const products = ref<Product[]>([])
const children = ref<Product[]>([])
const conversions = ref<ConversionForm[]>([])
const comboItems = ref<ComboForm[]>([])
const busy = ref(false)
const conversionBusy = ref(false)
const comboBusy = ref(false)
const lookupsReady = ref(false)
const error = ref<unknown | null>(null)
const conversionError = ref<unknown | null>(null)
const comboError = ref<unknown | null>(null)
const validationError = ref<string | null>(null)
const showLegacyRelations = false
const conversionEditorOpen = ref(false)
const conversionEditorIndex = ref<number | null>(null)
const form = ref({
  product_code: '',
  sku: '',
  barcode: '',
  product_type: 'STANDARD' as Product['product_type'],
  product_name: '',
  product_child_name: '',
  parent_product_id: '',
  product_group_id: '',
  base_uom_id: '',
  price: '',
  net_weight_g: '',
  package_gross_weight_g: '',
  package_length_mm: '',
  package_width_mm: '',
  package_height_mm: '',
  serial_management_enabled: false,
  description: '',
  status: 'ACTIVE' as Product['status'],
})
const unsaved = useUnsavedChanges(form)
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
const conversionDraft = ref<ConversionForm>(emptyConversion())
const parentOptions = computed(() =>
  products.value.filter(
    (row) =>
      String(row.id) !== id.value &&
      (String(row.id) === form.value.parent_product_id ||
        (row.role !== 'PARENT' && row.selectable && row.status === 'ACTIVE')),
  ),
)
const comboCandidates = computed(() =>
  products.value.filter(
    (row) =>
      String(row.id) !== id.value &&
      row.role !== 'PARENT' &&
      row.selectable &&
      row.status === 'ACTIVE',
  ),
)
const isChild = computed(() => editing.value && Boolean(form.value.parent_product_id))
const isCombo = computed(() => form.value.product_type === 'COMBO')
const role = computed(
  () => product.value?.role ?? (isCombo.value ? 'COMBO' : isChild.value ? 'CHILD' : 'SIMPLE'),
)
const isParentRole = computed(() => role.value === 'PARENT')
const canEdit = computed(() => hasBasePermission('product', editing.value ? 'edit' : 'create'))
const groupOptions = computed(() =>
  groups.value.map((row) => ({ value: String(row.id), label: `${row.code} - ${row.name}` })),
)
const uomOptions = computed(() =>
  uoms.value.map((row) => ({ value: String(row.id), label: `${row.code} - ${row.name}` })),
)
const parentSelectOptions = computed(() =>
  parentOptions.value.map((row) => ({
    value: String(row.id),
    label: `${row.full_name} (${row.product_code ?? row.id})`,
  })),
)
const comboSelectOptions = computed(() =>
  comboCandidates.value.map((row) => ({
    value: String(row.id),
    label: `${row.full_name} (${row.role})`,
  })),
)
const baseUomName = computed(
  () =>
    uoms.value.find((row) => String(row.id) === form.value.base_uom_id)?.code ?? t('list.noValue'),
)
const parentRow = computed(() =>
  products.value.find((row) => String(row.id) === form.value.parent_product_id),
)
function textValue(value: string | number | null | undefined): string {
  return value === null || value === undefined ? '' : String(value)
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
function resetFromRow(row: Product): void {
  form.value = {
    product_code: textValue(row.product_code),
    sku: textValue(row.sku),
    barcode: textValue(row.barcode),
    product_type: row.product_type,
    product_name: textValue(row.product_name),
    product_child_name: textValue(row.product_child_name),
    parent_product_id: textValue(row.parent_product_id),
    product_group_id: textValue(row.product_group_id),
    base_uom_id: textValue(row.base_uom_id),
    price: textValue(row.price),
    net_weight_g: textValue(row.net_weight_g),
    package_gross_weight_g: textValue(row.package_gross_weight_g),
    package_length_mm: textValue(row.package_length_mm),
    package_width_mm: textValue(row.package_width_mm),
    package_height_mm: textValue(row.package_height_mm),
    serial_management_enabled: Boolean(row.serial_management_enabled),
    description: textValue(row.description),
    status: row.status,
  }
}
function toConversion(row: ProductUomConversion): ConversionForm {
  return {
    id: textValue(row.id),
    uom_id: textValue(row.uom_id),
    conversion_factor: textValue(row.conversion_factor),
    price: textValue(row.price),
    package_gross_weight_g: textValue(row.package_gross_weight_g),
    package_length_mm: textValue(row.package_length_mm),
    package_width_mm: textValue(row.package_width_mm),
    package_height_mm: textValue(row.package_height_mm),
    usage_type: row.usage_type,
    status: row.status,
  }
}
function toCombo(row: ComboItem): ComboForm {
  return {
    id: textValue(row.id),
    component_product_id: textValue(row.component_product_id),
    quantity: textValue(row.quantity),
  }
}
async function loadConversions(): Promise<void> {
  if (!id.value || (role.value !== 'SIMPLE' && role.value !== 'CHILD')) return
  try {
    conversions.value = (await listProductConversions(id.value)).map(toConversion)
  } catch (caught: unknown) {
    conversionError.value = caught
    reportApiError(caught)
  }
}
async function loadChildren(): Promise<void> {
  if (!id.value || !['SIMPLE', 'PARENT'].includes(role.value)) return
  try {
    children.value = await loadLookupPages(listProductPage, {
      sort: 'product_name',
      filter: { parent_product_id: id.value },
    })
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
async function loadComboItems(): Promise<void> {
  if (!id.value || role.value !== 'COMBO') return
  try {
    comboItems.value = (await listProductComboItems(id.value)).map(toCombo)
  } catch (caught: unknown) {
    comboError.value = caught
    reportApiError(caught)
  }
}
async function load(): Promise<void> {
  error.value = null
  lookupsReady.value = false
  try {
    const [groupResult, uomResult, productResult] = await Promise.all([
      loadLookupPages(listProductGroupPage, { sort: 'name' }),
      loadLookupPages(listProductUomPage, { sort: 'code' }),
      loadLookupPages(listProductPage, { sort: 'product_name' }),
    ])
    groups.value = groupResult
    uoms.value = uomResult
    products.value = productResult
    lookupsReady.value = true
    if (id.value) {
      product.value = await getProductById(id.value)
      resetFromRow(product.value)
      unsaved.markClean()
      await Promise.all([loadConversions(), loadChildren(), loadComboItems()])
    }
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
function optionalString(value: string): string | undefined {
  return value === '' ? undefined : value
}
function comboPayload(): ComboItemsRequest {
  return comboItems.value.map((row) => ({
    component_product_id: row.component_product_id,
    quantity: row.quantity,
  }))
}
function validateComboItems(): boolean {
  validationError.value = null
  if (comboItems.value.some((row) => !row.component_product_id)) {
    validationError.value = t('product.componentRequired')
  } else if (comboItems.value.some((row) => !decimalIsPositive(row.quantity))) {
    validationError.value = t('product.quantityRequired')
  }
  return !validationError.value
}
function payload(): ProductCreate | ProductPatch {
  const base: ProductCreate = {
    product_code: form.value.product_code || null,
    sku: form.value.sku || null,
    barcode: form.value.barcode || null,
    product_type: form.value.product_type,
    status: form.value.status,
    ...(form.value.product_group_id ? { product_group_id: form.value.product_group_id } : {}),
    ...(form.value.base_uom_id ? { base_uom_id: form.value.base_uom_id } : {}),
    ...(form.value.parent_product_id ? { parent_product_id: form.value.parent_product_id } : {}),
    ...(isChild.value
      ? { product_child_name: form.value.product_child_name }
      : { product_name: form.value.product_name }),
    ...(optionalString(form.value.price) && !isParentRole.value ? { price: form.value.price } : {}),
    ...(optionalString(form.value.net_weight_g) ? { net_weight_g: form.value.net_weight_g } : {}),
    ...(optionalString(form.value.package_gross_weight_g)
      ? { package_gross_weight_g: form.value.package_gross_weight_g }
      : {}),
    ...(optionalString(form.value.package_length_mm)
      ? { package_length_mm: form.value.package_length_mm }
      : {}),
    ...(optionalString(form.value.package_width_mm)
      ? { package_width_mm: form.value.package_width_mm }
      : {}),
    ...(optionalString(form.value.package_height_mm)
      ? { package_height_mm: form.value.package_height_mm }
      : {}),
    ...(form.value.description ? { description: form.value.description } : {}),
    serial_management_enabled: isCombo.value ? false : form.value.serial_management_enabled,
  }
  return base
}
function decimalIsPositive(value: string): boolean {
  return /^\d+(?:\.\d+)?$/.test(value) && !/^0+(?:\.0+)?$/.test(value)
}
async function save(): Promise<void> {
  if (!lookupsReady.value) return
  validationError.value = null
  if (isChild.value && !form.value.parent_product_id)
    validationError.value = t('product.parentRequired')
  else if (isChild.value && !form.value.product_child_name.trim())
    validationError.value = t('product.childRequired')
  else if (!isChild.value && !form.value.product_name.trim())
    validationError.value = t('product.nameRequired')
  else if (!isParentRole.value && !form.value.base_uom_id)
    validationError.value = t('product.baseUomRequired')
  else if (!editing.value && isCombo.value && !validateComboItems()) return
  if (validationError.value) return
  busy.value = true
  error.value = null
  try {
    const saved = await saveProduct(payload(), id.value)
    product.value = saved
    if (
      !id.value &&
      form.value.product_type === 'COMBO' &&
      hasBasePermission('product', 'edit') &&
      comboItems.value.length > 0
    )
      await replaceProductComboItems(String(saved.id), comboPayload())
    // Save completed: do not ask for discard confirmation on route replacement.
    unsaved.markClean()
    if (!id.value && typeof route.query.return_parent_id === 'string') {
      await router.replace(`/products/${route.query.return_parent_id}`)
      product.value = await getProductById(route.query.return_parent_id)
    } else {
      if (!id.value) await router.replace(`/products/${saved.id}`)
      product.value = saved
    }
    resetFromRow(product.value)
    unsaved.markClean()
    await Promise.all([loadConversions(), loadChildren(), loadComboItems()])
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function toggleState(): Promise<void> {
  if (!product.value) return
  busy.value = true
  try {
    product.value = await changeProductState(
      String(product.value.id),
      product.value.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE',
    )
    resetFromRow(product.value)
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
function openConversion(index?: number): void {
  conversionEditorIndex.value = index === undefined ? null : index
  conversionDraft.value = index === undefined ? emptyConversion() : { ...conversions.value[index]! }
  conversionEditorOpen.value = true
}
function saveConversionDraft(): void {
  validationError.value = null
  const draft = conversionDraft.value
  if (!draft.uom_id || draft.uom_id === form.value.base_uom_id)
    validationError.value = t('product.alternateUomRequired')
  else if (!decimalIsPositive(draft.conversion_factor))
    validationError.value = t('product.factorRequired')
  else if ((draft.usage_type === 'SALES' || draft.usage_type === 'BOTH') && !draft.price)
    validationError.value = t('product.priceRequired')
  if (validationError.value) return
  if (conversionEditorIndex.value === null) conversions.value.push({ ...draft })
  else conversions.value[conversionEditorIndex.value] = { ...draft }
  conversionEditorOpen.value = false
}
function removeConversion(index: number): void {
  conversions.value = conversions.value.filter((_, current) => current !== index)
}
async function saveConversions(): Promise<void> {
  if (!id.value) return
  conversionBusy.value = true
  conversionError.value = null
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
    await replaceProductConversions(id.value, body)
    await loadConversions()
  } catch (caught: unknown) {
    conversionError.value = caught
    reportApiError(caught)
  } finally {
    conversionBusy.value = false
  }
}
function addChild(): void {
  if (id.value)
    void router.push({
      path: '/products/new',
      query: { parent_product_id: id.value, return_parent_id: id.value },
    })
}
function componentName(componentId: string): string {
  return products.value.find((row) => String(row.id) === componentId)?.full_name ?? componentId
}
function componentRole(componentId: string): string {
  return products.value.find((row) => String(row.id) === componentId)?.role ?? t('list.noValue')
}
function componentUom(componentId: string): string {
  const row = products.value.find((item) => String(item.id) === componentId)
  return (
    uoms.value.find((item) => String(item.id) === String(row?.base_uom_id))?.code ??
    t('list.noValue')
  )
}
function addComboItem(): void {
  comboItems.value.push({ id: '', component_product_id: '', quantity: '' })
}
function removeComboItem(index: number): void {
  comboItems.value = comboItems.value.filter((_, current) => current !== index)
}
async function saveComboItems(): Promise<void> {
  if (!id.value) return
  if (!validateComboItems()) return
  comboBusy.value = true
  comboError.value = null
  try {
    await replaceProductComboItems(id.value, comboPayload())
    await loadComboItems()
  } catch (caught: unknown) {
    comboError.value = caught
    reportApiError(caught)
  } finally {
    comboBusy.value = false
  }
}
onMounted(() => void load())
watch(
  () => route.fullPath,
  (next, previous) => {
    if (next !== previous) void load()
  },
)
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ editing ? t('product.editTitle') : t('product.createTitle') }}</h1>
      <p>{{ t('product.editorDescription') }}</p>
    </div>
    <button type="button" class="secondary-button" @click="router.push('/products')">
      {{ t('common.back') }}
    </button>
  </div>
  <section class="content-panel editor-panel">
    <p v-if="error || validationError" class="inline-error" role="alert">
      {{ validationError ?? t('product.requestError') }}
    </p>
    <p v-if="isParentRole" class="restriction-note" role="status">
      {{ t('product.parentRestriction') }}
    </p>
    <form class="editor-form" @submit.prevent="save">
      <fieldset :disabled="!canEdit || busy">
        <legend>{{ t('product.identity') }}</legend>
        <div class="form-grid">
          <label
            ><span>{{ t('product.productCode') }}</span
            ><input v-model="form.product_code" /></label
          ><label
            ><span>{{ t('product.sku') }}</span
            ><input v-model="form.sku" /></label
          ><label
            ><span>{{ t('product.barcode') }}</span
            ><input v-model="form.barcode" /></label
          ><label
            ><span>{{ t('product.type') }}</span
            ><select v-model="form.product_type" :disabled="editing || isChild">
              <option value="STANDARD">{{ t('product.standard') }}</option>
              <option value="COMBO">{{ t('product.combo') }}</option>
            </select></label
          >
        </div>
      </fieldset>
      <fieldset :disabled="!canEdit || busy">
        <legend>{{ t('product.classification') }}</legend>
        <div class="form-grid">
          <label v-if="!isChild"
            ><span>{{ t('product.productName') }}</span
            ><input v-model="form.product_name" required /></label
          ><label v-else
            ><span>{{ t('product.parentProduct') }}</span
            ><SearchableSelect
              :model-value="form.parent_product_id"
              :options="parentSelectOptions"
              :aria-label="t('product.parentProduct')"
              @update:model-value="
                (value) => (form.parent_product_id = typeof value === 'string' ? value : '')
              " /></label
          ><label v-if="isChild"
            ><span>{{ t('product.childName') }}</span
            ><input v-model="form.product_child_name" required /></label
          ><label
            ><span>{{ t('product.productGroup') }}</span
            ><SearchableSelect
              :model-value="form.product_group_id"
              :options="groupOptions"
              :placeholder="t('list.all')"
              :aria-label="t('product.productGroup')"
              @update:model-value="
                (value) => (form.product_group_id = typeof value === 'string' ? value : '')
              "
          /></label>
        </div>
        <p v-if="isChild" class="field-note">
          {{
            t('product.childFullName', {
              name: `${parentRow?.full_name ?? ''} - ${form.product_child_name}`,
            })
          }}
        </p>
      </fieldset>
      <fieldset :disabled="!canEdit || busy">
        <legend>{{ t('product.uomPrice') }}</legend>
        <p v-if="isParentRole" class="field-note">{{ t('product.parentNoUomPrice') }}</p>
        <div v-else class="form-grid">
          <label
            ><span>{{ t('product.baseUom') }}</span
            ><SearchableSelect
              :model-value="form.base_uom_id"
              :options="uomOptions"
              :aria-label="t('product.baseUom')"
              @update:model-value="
                (value) => (form.base_uom_id = typeof value === 'string' ? value : '')
              "
            /><small v-if="!isParentRole">{{ t('product.baseUomRequired') }}</small></label
          ><label
            ><span>{{ t('product.defaultPrice') }}</span
            ><DecimalInput
              v-model="form.price"
              kind="money"
              :max-decimals="2"
              :aria-label="t('product.defaultPrice')"
          /></label>
        </div>
      </fieldset>
      <fieldset :disabled="!canEdit || busy">
        <legend>{{ t('product.packaging') }}</legend>
        <div class="form-grid">
          <label
            ><span>{{ t('product.netWeight') }}</span
            ><DecimalInput
              v-model="form.net_weight_g"
              :max-decimals="3"
              :aria-label="t('product.netWeight')" /></label
          ><label
            ><span>{{ t('product.packageGrossWeight') }}</span
            ><DecimalInput
              v-model="form.package_gross_weight_g"
              :max-decimals="3"
              :aria-label="t('product.packageGrossWeight')" /></label
          ><label
            ><span>{{ t('product.packageLength') }}</span
            ><DecimalInput
              v-model="form.package_length_mm"
              :max-decimals="3"
              :aria-label="t('product.packageLength')" /></label
          ><label
            ><span>{{ t('product.packageWidth') }}</span
            ><DecimalInput
              v-model="form.package_width_mm"
              :max-decimals="3"
              :aria-label="t('product.packageWidth')" /></label
          ><label
            ><span>{{ t('product.packageHeight') }}</span
            ><DecimalInput
              v-model="form.package_height_mm"
              :max-decimals="3"
              :aria-label="t('product.packageHeight')"
          /></label>
        </div>
      </fieldset>
      <fieldset :disabled="!canEdit || busy">
        <legend>{{ t('product.operational') }}</legend>
        <div class="form-grid">
          <label v-if="!editing"
            ><span>{{ t('product.status') }}</span
            ><select v-model="form.status">
              <option value="ACTIVE">{{ t('status.active') }}</option>
              <option value="INACTIVE">{{ t('status.inactive') }}</option>
            </select></label
          ><label v-if="!isParentRole" class="checkbox-field"
            ><input
              v-model="form.serial_management_enabled"
              type="checkbox"
              :disabled="isCombo"
            /><span
              >{{ t('product.serial')
              }}<small v-if="isCombo">{{ t('product.comboSerialLocked') }}</small></span
            ></label
          >
          <p v-else class="field-note">{{ t('product.parentSerialLocked') }}</p>
        </div>
        <label
          ><span>{{ t('product.description') }}</span
          ><textarea v-model="form.description" rows="3" />
        </label>
      </fieldset>
      <footer class="editor-actions">
        <button type="button" class="secondary-button" @click="router.push('/products')">
          {{ t('common.cancel') }}</button
        ><button type="submit" class="primary-button" :disabled="busy || !canEdit || !lookupsReady">
          {{ busy ? t('common.saving') : t('common.save') }}</button
        ><button
          v-if="editing && product && hasBasePermission('product', 'setstate')"
          type="button"
          class="secondary-button"
          :disabled="busy"
          @click="toggleState"
        >
          {{ product.status === 'ACTIVE' ? t('common.deactivate') : t('common.activate') }}
        </button>
      </footer>
    </form>
    <section
      v-if="
        showLegacyRelations &&
        editing &&
        (role === 'SIMPLE' || role === 'PARENT') &&
        hasBasePermission('product', 'view')
      "
      class="relationship-section"
    >
      <header>
        <div>
          <h2>{{ t('product.children') }}</h2>
          <p class="field-note">{{ t('product.childrenDescription') }}</p>
        </div>
        <button
          v-if="hasBasePermission('product', 'create')"
          type="button"
          class="secondary-button"
          @click="addChild"
        >
          {{ t('product.addChild') }}
        </button>
      </header>
      <div class="data-table-wrap">
        <table class="data-table">
          <caption class="sr-only">
            {{
              t('product.children')
            }}
          </caption>
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
              <td>{{ row.full_name }}</td>
              <td>{{ row.sku ?? t('list.noValue') }}</td>
              <td>
                {{
                  uoms.find((item) => String(item.id) === String(row.base_uom_id))?.code ??
                  t('list.noValue')
                }}
              </td>
              <td>{{ displayMoney(row.price) }}</td>
              <td>{{ row.status === 'ACTIVE' ? t('status.active') : t('status.inactive') }}</td>
              <td class="table-actions">
                <button
                  v-if="hasBasePermission('product', 'view')"
                  type="button"
                  class="icon-button"
                  :aria-label="`${t('common.view')} ${row.full_name}`"
                  @click="router.push(`/products/${row.id}`)"
                >
                  {{ t('common.view') }}</button
                ><button
                  v-if="hasBasePermission('product', 'edit')"
                  type="button"
                  class="icon-button"
                  :aria-label="`${t('common.edit')} ${row.full_name}`"
                  @click="router.push(`/products/${row.id}`)"
                >
                  {{ t('common.edit') }}
                </button>
              </td>
            </tr>
            <tr v-if="children.length === 0">
              <td colspan="7">{{ t('list.noResults') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    <section
      v-if="showLegacyRelations && editing && role === 'CHILD'"
      class="relationship-section parent-summary"
    >
      <header>
        <h2>{{ t('product.parentProduct') }}</h2>
        <button
          v-if="parentRow && hasBasePermission('product', 'view')"
          type="button"
          class="secondary-button"
          @click="router.push(`/products/${parentRow.id}`)"
        >
          {{ t('product.viewParent') }}
        </button>
      </header>
      <p v-if="parentRow">
        {{ parentRow.full_name }} ({{ parentRow.product_code ?? t('list.noValue') }})
      </p>
    </section>
    <section
      v-if="
        showLegacyRelations &&
        editing &&
        (role === 'SIMPLE' || role === 'CHILD') &&
        hasBasePermission('product', 'view')
      "
      class="relationship-section"
    >
      <header>
        <div>
          <h2>{{ t('product.conversions') }}</h2>
          <p class="field-note">{{ t('product.conversionDirection', { uom: baseUomName }) }}</p>
        </div>
        <button
          v-if="hasBasePermission('product', 'edit')"
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
      <div class="data-table-wrap">
        <table class="data-table">
          <caption class="sr-only">
            {{
              t('product.conversions')
            }}
          </caption>
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
                {{ uoms.find((item) => String(item.id) === row.uom_id)?.code ?? t('list.noValue') }}
              </td>
              <td>
                1 {{ uoms.find((item) => String(item.id) === row.uom_id)?.code ?? '' }} =
                {{ displayQuantity(row.conversion_factor) }} {{ baseUomName }}
              </td>
              <td>{{ row.usage_type }}</td>
              <td>{{ displayMoney(row.price) }}</td>
              <td>{{ displayPackage(row) }}</td>
              <td>{{ row.status === 'ACTIVE' ? t('status.active') : t('status.inactive') }}</td>
              <td class="table-actions">
                <button
                  v-if="hasBasePermission('product', 'edit')"
                  type="button"
                  class="icon-button"
                  :aria-label="`${t('common.edit')} ${row.uom_id}`"
                  @click="openConversion(index)"
                >
                  {{ t('common.edit') }}</button
                ><button
                  v-if="hasBasePermission('product', 'edit')"
                  type="button"
                  class="icon-button danger-button"
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
        v-if="hasBasePermission('product', 'edit')"
        type="button"
        class="primary-button"
        :disabled="conversionBusy"
        @click="saveConversions"
      >
        {{ conversionBusy ? t('common.saving') : t('common.save') }}
      </button>
    </section>
    <section
      v-if="
        ((!editing && isCombo) || (showLegacyRelations && editing && role === 'COMBO')) &&
        (hasBasePermission('product', 'view') || hasBasePermission('product', 'create'))
      "
      class="relationship-section"
    >
      <header>
        <div>
          <h2>{{ t('product.comboComponents') }}</h2>
          <p class="field-note">{{ t('product.comboComponentsDescription') }}</p>
          <p v-if="!editing" class="field-note">{{ t('product.saveBeforeComponents') }}</p>
        </div>
        <button
          v-if="hasBasePermission('product', editing ? 'edit' : 'create')"
          type="button"
          class="secondary-button"
          :disabled="!hasBasePermission('product', 'edit')"
          @click="addComboItem"
        >
          {{ t('product.addComponent') }}
        </button>
      </header>
      <p v-if="comboError" class="inline-error" role="alert">{{ t('product.requestError') }}</p>
      <div class="data-table-wrap">
        <table class="data-table">
          <caption class="sr-only">
            {{
              t('product.comboComponents')
            }}
          </caption>
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
                <SearchableSelect
                  :model-value="row.component_product_id"
                  :options="comboSelectOptions"
                  :aria-label="t('product.componentProduct')"
                  @update:model-value="
                    (value) => (row.component_product_id = typeof value === 'string' ? value : '')
                  "
                />
              </td>
              <td>
                {{ componentName(row.component_product_id)
                }}<small class="field-note">{{ componentRole(row.component_product_id) }}</small>
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
                  v-if="hasBasePermission('product', 'edit')"
                  type="button"
                  class="icon-button danger-button"
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
        v-if="editing && hasBasePermission('product', 'edit')"
        type="button"
        class="primary-button"
        :disabled="comboBusy"
        @click="saveComboItems"
      >
        {{ comboBusy ? t('common.saving') : t('common.save') }}
      </button>
    </section>
  </section>
  <div v-if="conversionEditorOpen" class="modal-backdrop" role="presentation">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('product.conversionForm')"
    >
      <header>
        <h2>
          {{ conversionEditorIndex === null ? t('common.create') : t('common.edit') }}
          {{ t('product.conversions') }}
        </h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="conversionEditorOpen = false"
        >
          ×
        </button>
      </header>
      <div class="form-grid">
        <label
          ><span>{{ t('product.alternateUom') }}</span
          ><SearchableSelect
            :model-value="conversionDraft.uom_id"
            :options="uomOptions.filter((option) => option.value !== form.base_uom_id)"
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
            <option value="PURCHASE">PURCHASE</option>
            <option value="SALES">SALES</option>
            <option value="BOTH">BOTH</option>
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
      </div>
      <p v-if="validationError" class="inline-error" role="alert">{{ validationError }}</p>
      <footer class="editor-actions">
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
.editor-panel {
  display: grid;
  gap: 18px;
  padding: 20px;
}
.editor-form {
  display: grid;
  gap: 18px;
}
fieldset {
  display: grid;
  gap: 12px;
  border: 1px solid #dbe5ea;
  border-radius: 6px;
  padding: 16px;
}
legend {
  padding: 0 6px;
  color: #294b65;
  font-weight: 700;
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}
label {
  display: grid;
  gap: 5px;
  color: #536b7b;
  font-size: 12px;
  font-weight: 600;
}
input,
select,
textarea {
  min-height: 34px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 9px;
  color: #183b57;
  font: inherit;
}
.checkbox-field {
  display: flex;
  align-items: center;
  gap: 8px;
}
.checkbox-field input {
  min-height: auto;
}
.checkbox-field small,
.field-note,
.restriction-note {
  color: #536b7b;
  font-size: 12px;
}
.inline-error {
  margin: 0;
  color: #b64444;
  font-size: 12px;
}
.editor-actions,
.relationship-section header {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 8px;
}
.relationship-section {
  display: grid;
  gap: 12px;
  border-top: 1px solid #dbe5ea;
  padding-top: 18px;
}
.relationship-section header {
  justify-content: space-between;
}
.relationship-section h2 {
  margin: 0;
  font-size: 18px;
}
.data-table-wrap {
  overflow-x: auto;
  border: 1px solid #dbe5ea;
  border-radius: 6px;
}
.data-table {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
  color: #183b57;
  font-size: 13px;
}
.data-table th,
.data-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e8eef1;
  text-align: start;
  vertical-align: middle;
}
.data-table th {
  background: #f7fafb;
  color: #536b7b;
  font-size: 12px;
  font-weight: 700;
}
.data-table tbody tr:last-child td {
  border-bottom: 0;
}
.table-actions {
  white-space: nowrap;
}
.icon-button {
  border: 0;
  background: transparent;
  color: #1876a8;
  cursor: pointer;
  padding: 4px 6px;
}
.danger-button {
  color: #b64444;
}
.parent-summary p {
  margin: 0;
  color: #183b57;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 20;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgb(24 59 87 / 35%);
}
.modal-card {
  width: min(720px, 100%);
  max-height: min(90vh, 760px);
  overflow: auto;
  display: grid;
  gap: 16px;
  border-radius: 6px;
  background: #fff;
  padding: 20px;
  box-shadow: 0 16px 40px rgb(24 59 87 / 22%);
}
.modal-card header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.modal-card h2 {
  margin: 0;
  font-size: 18px;
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
@media (max-width: 760px) {
  .data-table {
    min-width: 640px;
  }
}
</style>
