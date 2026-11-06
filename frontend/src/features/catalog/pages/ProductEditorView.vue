<script setup lang="ts">
import AppDialogHost from '@/components/ui/AppDialogHost.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import type { Product } from '@/api/generated/types.gen'
import DecimalInput from '@/components/forms/DecimalInput.vue'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import RemoteSearchableSelect from '@/components/forms/RemoteSearchableSelect.vue'
import { useBasePermission } from '@/composables/useBasePermission'
import { useBusinessWorkflowStates } from '@/composables/useBusinessWorkflowStates'
import { useFormatters } from '@/composables/useFormatters'
import {
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
import BusinessWorkflowAction from '@/components/lists/BusinessWorkflowAction.vue'
import ProductImageField from '@/components/products/ProductImageField.vue'
import { productHasImage } from '@/services/productImage'
import ProductEditorPageHeading from '../product-editor/components/ProductEditorPageHeading.vue'
import ProductEditorFeedback from '../product-editor/components/ProductEditorFeedback.vue'
import { useProductEditorLookups } from '../product-editor/useProductEditorLookups'
import {
  emptyProductForm,
  emptyConversion,
  productToForm,
  toConversion,
  toCombo,
} from '../product-editor/model'
import { comboPayload, productPayload, conversionsPayload } from '../product-editor/payload'
import {
  comboValidationKey,
  conversionValidationKey,
  productValidationKey,
} from '../product-editor/validation'
import type { ComboForm, ConversionForm } from '../product-editor/types'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { loadLookupPages } from '@/services/listLookups'
import { productRoleLabel, productUomName, productUsageLabel } from '@/services/productPresentation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { hasBasePermission } = useBasePermission()
const workflowStates = useBusinessWorkflowStates('product')
const formatters = useFormatters()
const id = computed(() => (typeof route.params.id === 'string' ? route.params.id : undefined))
const editing = computed(() => Boolean(id.value))
const product = ref<Product | null>(null)
const imageField = ref<InstanceType<typeof ProductImageField> | null>(null)
const imagePartialError = ref(false)
const imageDirty = ref(false)
function onImageDirty(value: boolean): void {
  imageDirty.value = value
}
const children = ref<Product[]>([])
const conversions = ref<ConversionForm[]>([])
const comboItems = ref<ComboForm[]>([])
const busy = ref(false)
const conversionBusy = ref(false)
const comboBusy = ref(false)
const error = ref<unknown | null>(null)
const conversionError = ref<unknown | null>(null)
const comboError = ref<unknown | null>(null)
const validationError = ref<string | null>(null)
const showLegacyRelations = false
const conversionEditorOpen = ref(false)
const conversionEditorIndex = ref<number | null>(null)
const form = ref(emptyProductForm())
const unsaved = useUnsavedChanges(
  computed(() => ({ form: form.value, imageDirty: imageDirty.value })),
)
const conversionDraft = ref<ConversionForm>(emptyConversion())
const isChild = computed(() => editing.value && Boolean(form.value.parent_product_id))
const isCombo = computed(() => form.value.product_type === 'COMBO')
const role = computed(
  () => product.value?.role ?? (isCombo.value ? 'COMBO' : isChild.value ? 'CHILD' : 'SIMPLE'),
)
const isParentRole = computed(() => role.value === 'PARENT')
const canEdit = computed(() =>
  editing.value ? product.value?.allowed_actions?.edit === true : hasBasePermission('product', 'create'),
)
const canSetState = computed(() => editing.value && product.value?.allowed_actions?.setstate === true)
const {
  groups,
  uoms,
  products,
  lookupsReady,
  groupOptions,
  uomOptions,
  parentSelectOptions,
  comboSelectOptions,
  searchParentProducts,
  searchComboProducts,
  hydrateChosenProducts,
  parentChanged,
  componentChanged,
  baseUomName,
  parentRow,
} = useProductEditorLookups(id, form, comboItems, t)
// Read-only presentation summaries. The canonical Product validator, payload and
// image commit are deliberately unchanged; optional Group/Image are not errors.
const groupSummary = computed(() =>
  groupOptions.value.find((option) => option.value === form.value.product_group_id)?.label
  ?? t('product.editorNotSelected'),
)
const uomSummary = computed(() =>
  uomOptions.value.find((option) => option.value === form.value.base_uom_id)?.label
  ?? t('product.editorNotSelected'),
)
const editorNameComplete = computed(() =>
  Boolean((isChild.value ? form.value.product_child_name : form.value.product_name).trim()),
)
const imageSummary = computed(() => imageDirty.value
  ? t('product.editorImagePending')
  : productHasImage(product.value)
    ? t('product.editorImagePresent')
    : t('product.editorImageAbsent'),
)
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
function resetFromRow(row: Product): void {
  form.value = productToForm(row)
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
    await hydrateChosenProducts(comboItems.value.map((row) => row.component_product_id))
  } catch (caught: unknown) {
    comboError.value = caught
    reportApiError(caught)
  }
}
async function load(): Promise<void> {
  error.value = null
  lookupsReady.value = false
  products.value = []
  try {
    const [groupResult, uomResult] = await Promise.all([
      loadLookupPages(listProductGroupPage, { sort: 'name' }),
      loadLookupPages(listProductUomPage, { sort: 'code' }),
    ])
    groups.value = groupResult
    uoms.value = uomResult
    lookupsReady.value = true
    if (id.value) {
      product.value = await getProductById(id.value)
      resetFromRow(product.value)
      if (!imageDirty.value) unsaved.markClean()
      await Promise.all([
        loadConversions(),
        loadChildren(),
        loadComboItems(),
        hydrateChosenProducts([form.value.parent_product_id]),
      ])
    }
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
function validateComboItems(): boolean {
  validationError.value = null
  const key = comboValidationKey(comboItems.value)
  if (key) validationError.value = t(key)
  return !validationError.value
}
function payload() {
  return productPayload(
    form.value,
    {
      isChild: isChild.value,
      isCombo: isCombo.value,
      isParentRole: isParentRole.value,
      editing: editing.value,
    },
    comboItems.value,
  )
}
async function save(): Promise<void> {
  if (!lookupsReady.value) return
  validationError.value = null
  const key = productValidationKey(
    form.value,
    { isChild: isChild.value, isParentRole: isParentRole.value },
    groupOptions.value,
    uomOptions.value,
  )
  if (key) validationError.value = t(key)
  else if (!editing.value && isCombo.value && !validateComboItems()) return
  if (validationError.value) return
  busy.value = true
  error.value = null
  try {
    const saved = await saveProduct(payload(), id.value)
    imagePartialError.value = false
    // Upload BEFORE replacing Product metadata: a CREATE response changes
    // imagePath from undefined to null and previously cleared selectedFile.
    try {
      // The authorized image mutation already returns Product + image_url;
      // do not issue another SQL-backed GET just to display the new image.
      const imageResult = await imageField.value?.commit(String(saved.id))
      product.value = { ...(imageResult ?? saved), allowed_actions: product.value?.allowed_actions }
    } catch {
      // JSON CREATE/PATCH was committed. Keep the selected file for retry;
      // a subsequent Save on the edit route must PATCH, never create again.
      product.value = { ...saved, allowed_actions: product.value?.allowed_actions }
      imagePartialError.value = true
    }
    // Product JSON is persisted; clear the route guard for the internal
    // CREATE -> EDIT redirect. Restore pending-image dirtiness afterwards.
    imageDirty.value = false
    unsaved.markClean()
    if (!id.value && typeof route.query.return_parent_id === 'string' && !imagePartialError.value) {
      await router.replace(`/products/${route.query.return_parent_id}`)
      product.value = await getProductById(route.query.return_parent_id)
    } else if (!id.value) {
      await router.replace(`/products/${saved.id}`)
    }
    if (product.value) resetFromRow(product.value)
    if (imagePartialError.value) imageDirty.value = true
    else unsaved.markClean()
    await Promise.all([loadConversions(), loadChildren(), loadComboItems()])
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function afterWorkflowChange(): Promise<void> {
  if (!product.value) return
  product.value = await getProductById(String(product.value.id))
  resetFromRow(product.value)
}
function openConversion(index?: number): void {
  conversionEditorIndex.value = index === undefined ? null : index
  conversionDraft.value = index === undefined ? emptyConversion() : { ...conversions.value[index]! }
  conversionEditorOpen.value = true
}
function saveConversionDraft(): void {
  validationError.value = null
  const draft = conversionDraft.value
  const key = conversionValidationKey(draft, form.value.base_uom_id)
  if (key) validationError.value = t(key)
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
    const body = conversionsPayload(conversions.value)
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
  return (
    products.value.find((row) => String(row.id) === componentId)?.full_name ?? t('list.noValue')
  )
}
function componentRole(componentId: string): string {
  const role = products.value.find((row) => String(row.id) === componentId)?.role
  return role ? productRoleLabel(role, t) : t('list.noValue')
}
function componentUom(componentId: string): string {
  const row = products.value.find((item) => String(item.id) === componentId)
  return productUomName(uoms.value, row?.base_uom_id) ?? t('list.noValue')
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
    await replaceProductComboItems(id.value, comboPayload(comboItems.value))
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
  <ProductEditorPageHeading
    :editing="editing"
    save-form-id="product-editor-form"
    :save-disabled="busy || !canEdit || !lookupsReady"
    :save-busy="busy"
    @back="router.push('/products')"
  />
  <section class="editor-panel" :aria-label="t('product.editorWorkspace')">
    <ProductEditorFeedback
      :error="error"
      :validation-error="validationError"
      :is-parent-role="isParentRole"
      :image-partial-error="imagePartialError"
    />
    <form id="product-editor-form" class="editor-form" @submit.prevent="save">
      <div class="product-editor-layout">
        <div class="product-editor-main">
          <section class="product-editor-card" aria-labelledby="product-editor-basics-title">
            <div class="product-editor-card__heading">
              <span class="product-editor-card__index" aria-hidden="true">01</span>
              <h2 id="product-editor-basics-title">{{ t('product.editorBasics') }}</h2>
            </div>
            <fieldset class="product-editor-card__body" :disabled="!canEdit || busy">
              <legend class="sr-only">{{ t('product.editorBasics') }}</legend>
              <div class="product-editor-fields product-editor-fields--two">
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.productCode') }}</span>
                  <input v-model="form.product_code" />
                </label>
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.sku') }}</span>
                  <input v-model="form.sku" />
                </label>
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.barcode') }}</span>
                  <input v-model="form.barcode" />
                </label>
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.editorProductType') }}</span>
                  <select v-model="form.product_type" :disabled="editing || isChild">
                    <option value="STANDARD">{{ t('product.standard') }}</option>
                    <option value="COMBO">{{ t('product.combo') }}</option>
                  </select>
                </label>
                <label v-if="!isChild" class="ds-field product-editor-field">
                  <span>{{ t('product.productName') }} <span class="required-mark" aria-hidden="true">*</span></span>
                  <input v-model="form.product_name" required />
                </label>
                <label v-else class="ds-field product-editor-field">
                  <span>{{ t('product.parentProduct') }} <span class="required-mark" aria-hidden="true">*</span></span>
                  <RemoteSearchableSelect
                    :model-value="form.parent_product_id"
                    :disabled="!canEdit || busy"
                    :selected-options="parentSelectOptions"
                    :search="searchParentProducts"
                    :aria-label="t('product.parentProduct')"
                    @update:model-value="(value) => void parentChanged(value)"
                  />
                </label>
                <label v-if="isChild" class="ds-field product-editor-field">
                  <span>{{ t('product.childName') }} <span class="required-mark" aria-hidden="true">*</span></span>
                  <input v-model="form.product_child_name" required />
                </label>
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.productGroup') }}</span>
                  <SearchableSelect
                    :model-value="form.product_group_id"
                    :disabled="!canEdit || busy"
                    :options="groupOptions"
                    :placeholder="t('list.all')"
                    :aria-label="t('product.productGroup')"
                    @update:model-value="(value) => (form.product_group_id = typeof value === 'string' ? value : '')"
                  />
                </label>
              </div>
              <p v-if="isChild" class="field-note product-editor-child-note">
                {{ t('product.childFullName', { name: `${parentRow?.full_name ?? ''} - ${form.product_child_name}` }) }}
              </p>
            </fieldset>
          </section>

          <section class="product-editor-card" aria-labelledby="product-editor-price-title">
            <div class="product-editor-card__heading">
              <span class="product-editor-card__index" aria-hidden="true">02</span>
              <h2 id="product-editor-price-title">{{ t('product.uomPrice') }}</h2>
            </div>
            <fieldset class="product-editor-card__body" :disabled="!canEdit || busy">
              <legend class="sr-only">{{ t('product.uomPrice') }}</legend>
              <p v-if="isParentRole" class="field-note">{{ t('product.parentNoUomPrice') }}</p>
              <div v-else class="product-editor-fields product-editor-fields--two">
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.editorBaseUom') }} <span class="required-mark" aria-hidden="true">*</span></span>
                  <SearchableSelect
                    :model-value="form.base_uom_id"
                    :disabled="!canEdit || busy"
                    :options="uomOptions"
                    :aria-label="t('product.editorBaseUom')"
                    @update:model-value="(value) => (form.base_uom_id = typeof value === 'string' ? value : '')"
                  />
                  <small>{{ t('product.baseUomRequired') }}</small>
                </label>
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.defaultPrice') }}</span>
                  <DecimalInput
                    v-model="form.price"
                    kind="money"
                    :max-decimals="2"
                    :aria-label="t('product.defaultPrice')"
                  />
                </label>
              </div>
            </fieldset>
          </section>

          <section class="product-editor-card" aria-labelledby="product-editor-package-title">
            <div class="product-editor-card__heading">
              <span class="product-editor-card__index" aria-hidden="true">03</span>
              <h2 id="product-editor-package-title">{{ t('product.editorPackaging') }}</h2>
            </div>
            <fieldset class="product-editor-card__body" :disabled="!canEdit || busy">
              <legend class="sr-only">{{ t('product.editorPackaging') }}</legend>
              <div class="product-editor-fields product-editor-fields--two">
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.netWeight') }}</span>
                  <DecimalInput v-model="form.net_weight_g" :max-decimals="3" :aria-label="t('product.netWeight')" />
                </label>
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.packageGrossWeight') }}</span>
                  <DecimalInput v-model="form.package_gross_weight_g" :max-decimals="3" :aria-label="t('product.packageGrossWeight')" />
                </label>
              </div>
              <div class="product-editor-fields product-editor-fields--three">
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.packageLength') }}</span>
                  <DecimalInput v-model="form.package_length_mm" :max-decimals="3" :aria-label="t('product.packageLength')" />
                </label>
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.packageWidth') }}</span>
                  <DecimalInput v-model="form.package_width_mm" :max-decimals="3" :aria-label="t('product.packageWidth')" />
                </label>
                <label class="ds-field product-editor-field">
                  <span>{{ t('product.packageHeight') }}</span>
                  <DecimalInput v-model="form.package_height_mm" :max-decimals="3" :aria-label="t('product.packageHeight')" />
                </label>
              </div>
            </fieldset>
          </section>

          <section class="product-editor-card" aria-labelledby="product-editor-operations-title">
            <div class="product-editor-card__heading">
              <span class="product-editor-card__index" aria-hidden="true">04</span>
              <h2 id="product-editor-operations-title">{{ t('product.operational') }}</h2>
            </div>
            <fieldset class="product-editor-card__body" :disabled="!canEdit || busy">
              <legend class="sr-only">{{ t('product.operational') }}</legend>
              <p v-if="!editing" class="workflow-initial-note product-editor-status-note" role="note">
                {{ t('workflow.initialManaged') }}
                <strong v-if="workflowStates.initial.value">{{ workflowStates.initial.value }}</strong>
              </p>
              <label v-if="!isParentRole" class="checkbox-field product-editor-serial">
                <input v-model="form.serial_management_enabled" type="checkbox" :disabled="isCombo" />
                <span>{{ t('product.serial') }}<small v-if="isCombo">{{ t('product.comboSerialLocked') }}</small></span>
              </label>
              <p v-else class="field-note">{{ t('product.parentSerialLocked') }}</p>
              <label class="ds-field product-editor-field">
                <span>{{ t('product.description') }}</span>
                <textarea v-model="form.description" rows="3" />
              </label>
            </fieldset>
          </section>
        </div>

        <aside class="product-editor-side" :aria-label="t('product.editorSidebar')">
          <section class="product-editor-card" aria-labelledby="product-editor-image-title">
            <div class="product-editor-card__heading">
              <span class="product-editor-card__index" aria-hidden="true">05</span>
              <h2 id="product-editor-image-title">{{ t('product.editorImageCard') }}</h2>
            </div>
            <div class="product-editor-card__body">
              <ProductImageField
                ref="imageField"
                :product-id="id ?? (product ? String(product.id) : undefined)"
                :image-available="productHasImage(product)"
                :image-path="product?.image_url"
                :editable="canEdit"
                :disabled="busy"
                @dirty="onImageDirty"
              />
            </div>
          </section>

          <section class="product-editor-card" aria-labelledby="product-editor-summary-title">
            <div class="product-editor-card__heading">
              <span class="product-editor-card__index" aria-hidden="true">06</span>
              <h2 id="product-editor-summary-title">{{ t('product.editorSummary') }}</h2>
            </div>
            <dl class="product-editor-summary">
              <div class="product-editor-summary__row">
                <dt>{{ t('product.editorProductType') }}</dt>
                <dd class="product-editor-summary__value is-success">{{ form.product_type }}</dd>
              </div>
              <div class="product-editor-summary__row">
                <dt>{{ isChild ? t('product.childName') : t('product.productName') }}</dt>
                <dd :class="['product-editor-summary__value', editorNameComplete ? 'is-success' : 'is-required']">
                  {{ editorNameComplete ? (isChild ? form.product_child_name : form.product_name) : t('product.editorRequired') }}
                </dd>
              </div>
              <div class="product-editor-summary__row">
                <dt>{{ t('product.productGroup') }}</dt>
                <dd class="product-editor-summary__value">{{ groupSummary }}</dd>
              </div>
              <div v-if="!isParentRole" class="product-editor-summary__row">
                <dt>{{ t('product.editorBaseUom') }}</dt>
                <dd :class="['product-editor-summary__value', form.base_uom_id ? 'is-success' : 'is-required']">
                  {{ uomSummary }}
                </dd>
              </div>
              <div class="product-editor-summary__row">
                <dt>{{ t('product.serial') }}</dt>
                <dd class="product-editor-summary__value">
                  {{ !isCombo && !isParentRole && form.serial_management_enabled ? t('product.editorOn') : t('product.editorOff') }}
                </dd>
              </div>
              <div class="product-editor-summary__row">
                <dt>{{ t('product.image') }}</dt>
                <dd class="product-editor-summary__value">{{ imageSummary }}</dd>
              </div>
              <div v-if="isCombo" class="product-editor-summary__row">
                <dt>{{ t('product.comboComponents') }}</dt>
                <dd class="product-editor-summary__value">{{ comboItems.length }}</dd>
              </div>
            </dl>
          </section>

          <section class="product-editor-card" aria-labelledby="product-editor-notes-title">
            <div class="product-editor-card__heading">
              <span class="product-editor-card__index" aria-hidden="true">07</span>
              <h2 id="product-editor-notes-title">{{ t('product.editorTips') }}</h2>
            </div>
            <ul class="product-editor-tips">
              <li>{{ t('product.editorTipCombo') }}</li>
              <li>{{ t('product.editorTipConversions') }}</li>
              <li>{{ t('product.editorTipWorkflow') }}</li>
            </ul>
          </section>
        </aside>
      </div>
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
        <button data-ds-icon="add"
          v-if="hasBasePermission('product', 'create')"
          type="button"
          class="secondary-button"
          @click="addChild"
        >
          {{ t('product.addChild') }}
        </button>
      </header>
      <div class="data-table-wrap ds-table-scroll">
        <table class="data-table ds-table ds-table--relationship">
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
                {{ productUomName(uoms, row.base_uom_id) ?? t('list.noValue') }}
              </td>
              <td>{{ displayMoney(row.price) }}</td>
              <td>{{ businessStateLabel(row.status) }}</td>
              <td class="table-actions">
                <button
                  v-if="hasBasePermission('product', 'view')"
                  type="button"
                  class="icon-button"
                  :aria-label="`${t('common.view')} ${row.full_name}`"
                  @click="router.push(`/products/${row.id}`)"
                 data-ds-icon="view"></button><button
                  v-if="row.allowed_actions?.edit === true"
                  type="button"
                  class="icon-button"
                  :aria-label="`${t('common.edit')} ${row.full_name}`"
                  @click="router.push(`/products/${row.id}`)"
                 data-ds-icon="edit"></button>
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
        <button data-ds-icon="back"
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
        <button data-ds-icon="add"
          v-if="canEdit"
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
      <div class="data-table-wrap ds-table-scroll">
        <table class="data-table ds-table ds-table--relationship">
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
                {{ productUomName(uoms, row.uom_id) ?? t('list.noValue') }}
              </td>
              <td>
                1 {{ productUomName(uoms, row.uom_id) ?? '' }} =
                {{ displayQuantity(row.conversion_factor) }} {{ baseUomName }}
              </td>
              <td>{{ productUsageLabel(row.usage_type, t) }}</td>
              <td>{{ displayMoney(row.price) }}</td>
              <td>{{ displayPackage(row) }}</td>
              <td>{{ businessStateLabel(row.status) }}</td>
              <td class="table-actions">
                <button
                  v-if="canEdit"
                  type="button"
                  class="icon-button"
                  :aria-label="`${t('common.edit')} ${row.uom_id}`"
                  @click="openConversion(index)"
                 data-ds-icon="edit"></button><button
                  v-if="canEdit"
                  type="button"
                  class="icon-button danger-button"
                  :aria-label="t('common.delete')"
                  @click="removeConversion(index)"
                 data-ds-icon="delete"></button>
              </td>
            </tr>
            <tr v-if="conversions.length === 0">
              <td colspan="7">{{ t('list.noResults') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <button data-ds-icon="save"
        v-if="canEdit"
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
          <p v-if="!editing" class="field-note">{{ t('product.atomicComboCreate') }}</p>
        </div>
        <button data-ds-icon="add"
          v-if="editing ? canEdit : hasBasePermission('product', 'create')"
          type="button"
          class="secondary-button"
          :disabled="editing ? !canEdit : !hasBasePermission('product', 'edit')"
          @click="addComboItem"
        >
          {{ t('product.addComponent') }}
        </button>
      </header>
      <p v-if="comboError" class="inline-error" role="alert">{{ t('product.requestError') }}</p>
      <div class="data-table-wrap ds-table-scroll">
        <table class="data-table ds-table ds-table--relationship">
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
                <RemoteSearchableSelect
                  :model-value="row.component_product_id"
                  :selected-options="comboSelectOptions"
                  :search="searchComboProducts"
                  :aria-label="t('product.componentProduct')"
                  @update:model-value="(value) => void componentChanged(index, value)"
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
                  v-if="editing ? canEdit : hasBasePermission('product', 'edit')"
                  type="button"
                  class="icon-button danger-button"
                  :aria-label="t('common.delete')"
                  @click="removeComboItem(index)"
                 data-ds-icon="delete"></button>
              </td>
            </tr>
            <tr v-if="comboItems.length === 0">
              <td colspan="5">{{ t('list.noResults') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <button data-ds-icon="save"
        v-if="editing && canEdit"
        type="button"
        class="primary-button"
        :disabled="comboBusy"
        @click="saveComboItems"
      >
        {{ comboBusy ? t('common.saving') : t('common.save') }}
      </button>
    </section>

      <footer class="editor-actions">
        <button data-ds-icon="cancel" type="button" class="secondary-button" @click="router.push('/products')">
          {{ t('common.cancel') }}</button
        ><button data-ds-icon="save" type="submit" form="product-editor-form" class="primary-button" :disabled="busy || !canEdit || !lookupsReady">
          {{ busy ? t('common.saving') : t('common.save') }}</button
        ><BusinessWorkflowAction
          v-if="editing && product && canSetState"
          resource="product"
          :target-id="String(product.id)"
          :busy="busy"
          variant="button"
          @changed="afterWorkflowChange"
        />
      </footer>
  </section>
  <AppDialogHost :open="conversionEditorOpen" size="md" @close="conversionEditorOpen = false" variant="standard">
    <section v-if="conversionEditorOpen"
      class="ds-dialog-surface modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('product.conversionForm')"
    >
      <header class="modal-card__header">
        <h2>
          {{ conversionEditorIndex === null ? t('common.create') : t('common.edit') }}
          {{ t('product.conversions') }}
        </h2>
        <button
          type="button"
          class="modal-card-button-close"
          :aria-label="t('common.close')"
          @click="conversionEditorOpen = false"
         data-ds-icon="close"></button>
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
      </div>
      <p v-if="validationError" class="inline-error" role="alert">{{ validationError }}</p>
      <footer class="modal-card__footer editor-actions">
        <button data-ds-icon="cancel" type="button" class="secondary-button" @click="conversionEditorOpen = false">
          {{ t('common.cancel') }}</button
        ><button data-ds-icon="save" type="button" class="primary-button" @click="saveConversionDraft">
          {{ t('common.save') }}
        </button>
      </footer>
    </section>
  </AppDialogHost>
</template>

<style scoped>
/* PR02: page-only composition. Form control chrome remains in forms.css. */
.editor-panel {
  container: product-editor / inline-size;
  display: grid;
  gap: var(--ds-space-4);
  min-width: 0;
}
.editor-form {
  display: grid;
  gap: var(--ds-space-4);
  min-width: 0;
}
.product-editor-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.65fr) minmax(295px, 1fr);
  gap: var(--ds-space-4);
  min-width: 0;
  align-items: start;
}
.product-editor-main,
.product-editor-side {
  display: grid;
  align-content: start;
  gap: var(--ds-space-3);
  min-width: 0;
}
.product-editor-card,
.editor-panel > .relationship-section {
  min-width: 0;
  background: var(--ds-bg-surface);
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-md);
  box-shadow: var(--ds-shadow-card);
  overflow: hidden;
}
.product-editor-card__heading {
  display: flex;
  align-items: center;
  gap: var(--ds-space-2);
  min-width: 0;
  padding: var(--ds-space-3) var(--ds-space-4);
  border-bottom: 1px solid var(--ds-border-subtle);
}
.product-editor-card__heading h2 {
  margin: 0;
  color: var(--ds-text-primary);
  font-size: var(--ds-font-lg);
  font-weight: 700;
  line-height: 1.35;
}
.product-editor-card__index {
  display: inline-grid;
  place-items: center;
  flex: 0 0 27px;
  width: 27px;
  height: 27px;
  border-radius: var(--ds-radius-sm);
  background: var(--ds-primary-soft);
  color: var(--ds-primary);
  font-size: var(--ds-font-xs);
  font-weight: 700;
}
.product-editor-card__body {
  display: grid;
  gap: var(--ds-space-3);
  min-width: 0;
  margin: 0;
  padding: var(--ds-space-4);
  border: 0;
}
.product-editor-fields {
  display: grid;
  gap: var(--ds-space-3);
  min-width: 0;
}
.product-editor-fields--two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.product-editor-fields--three {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.product-editor-field { align-content: start; min-width: 0; }
.product-editor-field > small,
.product-editor-child-note,
.checkbox-field small,
.field-note,
.restriction-note {
  color: var(--ds-text-secondary);
  font-size: var(--ds-font-xs);
  font-weight: 400;
}
.product-editor-child-note { margin: 0; }
.product-editor-status-note {
  margin: 0;
  padding: var(--ds-space-2) var(--ds-space-3);
  background: var(--ds-info-soft);
  color: var(--ds-text-primary);
  border-radius: var(--ds-radius-sm);
  font-size: var(--ds-font-sm);
}
.product-editor-status-note strong { color: var(--ds-primary); }
.product-editor-serial { justify-self: start; }
.product-editor-serial small { display: block; margin-top: 2px; }
.product-editor-summary { display: grid; gap: 0; margin: 0; padding: var(--ds-space-2) var(--ds-space-4); }
.product-editor-summary__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--ds-space-3);
  min-width: 0;
  padding: var(--ds-space-3) 0;
  border-bottom: 1px solid var(--ds-border-subtle);
  font-size: var(--ds-font-sm);
}
.product-editor-summary__row:last-child { border-bottom: 0; }
.product-editor-summary__row dt { flex: 0 0 auto; color: var(--ds-text-secondary); }
.product-editor-summary__value {
  margin: 0;
  min-width: 0;
  max-width: 60%;
  color: var(--ds-text-primary);
  font-weight: 600;
  text-align: end;
  overflow-wrap: anywhere;
}
.product-editor-summary__value.is-success { color: var(--ds-success); }
.product-editor-summary__value.is-required { color: var(--ds-danger); }
.product-editor-tips {
  display: grid;
  gap: var(--ds-space-3);
  margin: 0;
  padding: var(--ds-space-4) var(--ds-space-4) var(--ds-space-4) var(--ds-space-8);
  color: var(--ds-text-secondary);
  background: var(--ds-bg-subtle);
  font-size: var(--ds-font-sm);
  line-height: var(--ds-line-height);
}
.product-editor-tips li::marker { color: var(--ds-primary); }
.inline-error { margin: 0; }
.editor-actions,
.relationship-section header {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: var(--ds-space-2);
}
.editor-actions {
  padding: var(--ds-space-3) var(--ds-space-4);
  background: var(--ds-bg-surface);
  border: 1px solid var(--ds-border-subtle);
  border-radius: var(--ds-radius-md);
}
.relationship-section {
  display: grid;
  gap: var(--ds-space-3);
  padding: var(--ds-space-4);
}
.relationship-section header { justify-content: space-between; }
.relationship-section h2 { margin: 0; font-size: var(--ds-font-lg); }
/* Preserve shared button chrome and icon geometry. */
.icon-button { color: var(--ds-primary); }
.danger-button { color: var(--ds-danger); }
.parent-summary p { margin: 0; color: var(--ds-text-primary); }
.modal-card header { display: flex; align-items: center; justify-content: space-between; }
.modal-card h2 { margin: 0; font-size: var(--ds-font-lg); }
/* Conversion editor grid is owned by forms.css, including its dialog-width @container. */
@container product-editor (max-width: 1040px) {
  .product-editor-layout { grid-template-columns: minmax(0, 1fr); }
  .product-editor-side { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .product-editor-side > :first-child { grid-column: 1 / -1; }
}
@container product-editor (max-width: 640px) {
  .product-editor-fields--two,
  .product-editor-fields--three,
  .product-editor-side { grid-template-columns: minmax(0, 1fr); }
  .product-editor-card__body { padding: var(--ds-space-3); }
  .product-editor-card__heading { padding: var(--ds-space-3); }
  .product-editor-side > :first-child { grid-column: auto; }
  .product-editor-summary { padding-inline: var(--ds-space-3); }
  .editor-actions { justify-content: stretch; }
  .editor-actions > button { flex: 1 1 130px; }
}
</style>
