<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import type {
  Product,
  ProductCreate,
  ProductGroup,
  ProductPatch,
  ProductUom,
  ProductUomConversion,
  ProductUomConversionsRequest,
} from '@/api/generated/types.gen'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import { useBasePermission } from '@/composables/useBasePermission'
import {
  changeProductState,
  getProductById,
  listProductGroupPage,
  listProductPage,
  listProductUomPage,
  listProductConversions,
  replaceProductConversions,
  saveProduct,
} from '@/services/productCatalog'
import { reportApiError } from '@/services/errorBoundary'

type ConversionForm = ProductUomConversion & {
  uom_id: string
  conversion_factor: string
  price: string
  usage_type: ProductUomConversion['usage_type']
  status: ProductUomConversion['status']
}
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { hasBasePermission } = useBasePermission()
const id = computed(() => (typeof route.params.id === 'string' ? route.params.id : undefined))
const editing = computed(() => Boolean(id.value))
const product = ref<Product | null>(null)
const groups = ref<ProductGroup[]>([])
const uoms = ref<ProductUom[]>([])
const products = ref<Product[]>([])
const conversions = ref<ConversionForm[]>([])
const busy = ref(false)
const conversionBusy = ref(false)
const error = ref<unknown | null>(null)
const conversionError = ref<unknown | null>(null)
const validationError = ref<string | null>(null)
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
  serial_management_enabled: false,
  description: '',
  status: 'ACTIVE' as Product['status'],
})

const parentOptions = computed(() =>
  products.value.filter(
    (row) =>
      String(row.id) !== id.value &&
      row.role !== 'PARENT' &&
      row.selectable &&
      row.status === 'ACTIVE',
  ),
)
const isChild = computed(() => Boolean(form.value.parent_product_id))
const isCombo = computed(() => form.value.product_type === 'COMBO')
const role = computed(
  () => product.value?.role ?? (isCombo.value ? 'COMBO' : isChild.value ? 'CHILD' : 'SIMPLE'),
)
const isParentRole = computed(() => role.value === 'PARENT')
const canEdit = computed(
  () => hasBasePermission('product', editing.value ? 'edit' : 'create') && !isParentRole.value,
)
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
function resetFromRow(row: Product): void {
  form.value = {
    product_code: row.product_code ?? '',
    sku: row.sku ?? '',
    barcode: row.barcode ?? '',
    product_type: row.product_type,
    product_name: row.product_name ?? '',
    product_child_name: row.product_child_name ?? '',
    parent_product_id: row.parent_product_id ? String(row.parent_product_id) : '',
    product_group_id: row.product_group_id ? String(row.product_group_id) : '',
    base_uom_id: row.base_uom_id ? String(row.base_uom_id) : '',
    price: row.price ?? '',
    serial_management_enabled: Boolean(row.serial_management_enabled),
    description: row.description ?? '',
    status: row.status,
  }
}
async function load(): Promise<void> {
  error.value = null
  try {
    const [groupResult, uomResult, productResult] = await Promise.all([
      listProductGroupPage({ q: '', page: 1, page_size: 200, sort: 'name', filter: {} }),
      listProductUomPage({ q: '', page: 1, page_size: 200, sort: 'code', filter: {} }),
      listProductPage({ q: '', page: 1, page_size: 200, sort: 'product_name', filter: {} }),
    ])
    groups.value = groupResult.data
    uoms.value = uomResult.data
    products.value = productResult.data
    if (id.value) {
      product.value = await getProductById(id.value)
      resetFromRow(product.value)
      if (product.value.role === 'SIMPLE' || product.value.role === 'CHILD') await loadConversions()
    }
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
async function loadConversions(): Promise<void> {
  if (!id.value || (role.value !== 'SIMPLE' && role.value !== 'CHILD')) return
  try {
    const rows = await listProductConversions(id.value)
    conversions.value = rows.map((row) => ({
      ...row,
      uom_id: String(row.uom_id),
      conversion_factor: row.conversion_factor,
      price: row.price ?? '',
    }))
  } catch (caught: unknown) {
    conversionError.value = caught
    reportApiError(caught)
  }
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
    ...(form.value.price !== '' ? { price: form.value.price } : {}),
    ...(form.value.description ? { description: form.value.description } : {}),
    serial_management_enabled: isCombo.value ? false : form.value.serial_management_enabled,
  }
  return base
}
async function save(): Promise<void> {
  validationError.value = null
  if (isChild.value && !form.value.parent_product_id) {
    validationError.value = t('product.parentRequired')
    return
  }
  if (isChild.value && !form.value.product_child_name.trim()) {
    validationError.value = t('product.childRequired')
    return
  }
  if (!isChild.value && !form.value.product_name.trim()) {
    validationError.value = t('product.nameRequired')
    return
  }
  if (!isParentRole.value && !form.value.base_uom_id) {
    validationError.value = t('product.baseUomRequired')
    return
  }
  busy.value = true
  error.value = null
  try {
    const saved = await saveProduct(payload(), id.value)
    product.value = saved
    if (!id.value) await router.replace(`/products/${saved.id}`)
    else resetFromRow(saved)
    await loadConversions()
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
  error.value = null
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
function addConversion(): void {
  conversions.value = [
    ...conversions.value,
    {
      id: '',
      product_id: id.value ?? '',
      uom_id: '',
      conversion_factor: '',
      price: '',
      package_gross_weight_g: null,
      package_length_mm: null,
      package_width_mm: null,
      package_height_mm: null,
      usage_type: 'BOTH',
      status: 'ACTIVE',
    },
  ]
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
      ...(row.price !== '' ? { price: row.price } : {}),
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
onMounted(() => void load())
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
              name: `${parentOptions.find((row) => String(row.id) === form.parent_product_id)?.full_name ?? ''} - ${form.product_child_name}`,
            })
          }}
        </p>
      </fieldset>
      <fieldset :disabled="!canEdit || busy">
        <legend>{{ t('product.uomPrice') }}</legend>
        <div class="form-grid">
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
            ><input v-model="form.price" inputmode="decimal"
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
          ><label class="checkbox-field"
            ><input
              v-model="form.serial_management_enabled"
              type="checkbox"
              :disabled="isCombo"
            /><span
              >{{ t('product.serial')
              }}<small v-if="isCombo">{{ t('product.comboSerialLocked') }}</small></span
            ></label
          >
        </div>
        <label
          ><span>{{ t('product.description') }}</span
          ><textarea v-model="form.description" rows="3" />
        </label>
      </fieldset>
      <footer class="editor-actions">
        <button type="button" class="secondary-button" @click="router.push('/products')">
          {{ t('common.cancel') }}</button
        ><button type="submit" class="primary-button" :disabled="busy || !canEdit">
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
        editing && (role === 'SIMPLE' || role === 'CHILD') && hasBasePermission('product', 'view')
      "
      class="conversion-section"
    >
      <header>
        <h2>{{ t('product.conversions') }}</h2>
        <button
          v-if="hasBasePermission('product', 'edit')"
          type="button"
          class="secondary-button"
          @click="addConversion"
        >
          {{ t('product.addConversion') }}
        </button>
      </header>
      <p v-if="conversionError" class="inline-error" role="alert">
        {{ t('product.requestError') }}
      </p>
      <div v-for="(row, index) in conversions" :key="row.id || index" class="conversion-row">
        <label
          ><span>{{ t('product.baseUom') }}</span
          ><SearchableSelect
            :model-value="row.uom_id"
            :options="uomOptions.filter((option) => option.value !== form.base_uom_id)"
            :aria-label="t('product.baseUom')"
            @update:model-value="
              (value) => (row.uom_id = typeof value === 'string' ? value : '')
            " /></label
        ><label
          ><span>{{ t('product.factor') }}</span
          ><input v-model="row.conversion_factor" inputmode="decimal" /></label
        ><label
          ><span>{{ t('product.sellingPrice') }}</span
          ><input v-model="row.price" inputmode="decimal" /></label
        ><label
          ><span>{{ t('product.usage') }}</span
          ><select v-model="row.usage_type">
            <option value="PURCHASE">PURCHASE</option>
            <option value="SALES">SALES</option>
            <option value="BOTH">BOTH</option>
          </select></label
        ><button
          type="button"
          class="icon-button"
          :aria-label="t('common.delete')"
          @click="removeConversion(index)"
        >
          x
        </button>
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
  </section>
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
.conversion-section header {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 8px;
}
.conversion-section {
  display: grid;
  gap: 12px;
  border-top: 1px solid #dbe5ea;
  padding-top: 18px;
}
.conversion-section header {
  justify-content: space-between;
}
.conversion-section h2 {
  margin: 0;
  font-size: 18px;
}
.conversion-row {
  display: grid;
  grid-template-columns: minmax(180px, 1.3fr) repeat(3, minmax(130px, 1fr)) auto;
  align-items: end;
  gap: 10px;
  padding: 12px;
  border: 1px solid #dbe5ea;
  border-radius: 5px;
}
.icon-button {
  border: 0;
  background: transparent;
  color: #536b7b;
  cursor: pointer;
}
@media (max-width: 760px) {
  .conversion-row {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
