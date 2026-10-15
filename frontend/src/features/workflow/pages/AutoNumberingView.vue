<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import { CloseOutline, RefreshOutline } from '@vicons/ionicons5'

import type {
  NumberingSeriesCreate,
  NumberingSeriesPatch,
  Resource,
} from '@/api/generated/types.gen'
import {
  ListPagination,
  ListShell,
  ListTable,
  TableDetailModal,
  TableRowActions,
} from '@/components/lists'
import type { ListColumn } from '@/components/lists/listTypes'
import { useBasePermission } from '@/composables/useBasePermission'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import {
  changeNumberingSeriesState,
  getNumberingSeriesById,
  listNumberingSeriesPage,
  saveNumberingSeries,
  validateNumberingSeries,
  type NumberingSeriesRow,
} from '@/services/autoNumbering'
import { listResourcePage } from '@/services/security'
import { loadLookupPages } from '@/services/listLookups'
import { normalizeApiError, reportApiError } from '@/services/errorBoundary'

const { t } = useI18n()
const { hasBasePermissionKey } = useBasePermission()
const canConfigure = computed(() => hasBasePermissionKey('resource.configure_numbering'))

const resources = ref<Resource[]>([])
const selectedResourceId = ref('')
const rows = ref<NumberingSeriesRow[]>([])
const page = ref(1)
const pageSize = ref(50)
const loadingResources = ref(false)
const loadingSeries = ref(false)
const busy = ref(false)
const error = ref<unknown | null>(null)
const modalOpen = ref(false)
const viewOpen = ref(false)
const viewLoading = ref(false)
const editingId = ref<string | undefined>()
const viewRow = ref<NumberingSeriesRow | null>(null)
const issuedLockedIds = ref(new Set<string>())
const formCard = ref<HTMLElement | null>(null)
const validation = ref<{
  valid: boolean
  normalized_pattern: string
  validation_messages: string[]
} | null>(null)
const validationBusy = ref(false)
const formError = ref<unknown | null>(null)
const seqWidth = ref('6')
const form = ref({
  series_code: '',
  name: '',
  scope_type: 'GROUP' as NumberingSeriesCreate['scope_type'],
  pattern: '',
  reset_policy: 'NEVER' as NumberingSeriesCreate['reset_policy'],
  generation_mode: 'AUTO_REQUIRED' as NumberingSeriesCreate['generation_mode'],
  start_number: '1',
  increment_by: '1',
  is_default: false,
  status: 'ACTIVE' as NumberingSeriesCreate['status'],
})

const { markClean, confirmDiscard } = useUnsavedChanges(form, modalOpen)

const resource = computed(() =>
  resources.value.find((candidate) => String(candidate.id) === selectedResourceId.value),
)
const pageCount = computed(() => Math.max(1, Math.ceil(rows.value.length / pageSize.value)))
const visibleRows = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return rows.value.slice(start, start + pageSize.value)
})
const errorNotice = computed(() => normalizeApiError(error.value ?? formError.value))
const errorMessage = computed(() => {
  const notice = errorNotice.value
  if (!notice) return error.value || formError.value ? t('numbering.requestError') : ''
  return `${t(`errors.status.${notice.status}`)}${notice.code ? ` (${notice.code})` : ''}`
})
const detailFields = computed(() => {
  const row = viewRow.value
  if (!row) return []
  return [
    { key: 'resource', label: t('numbering.resource'), value: resourceLabel(row.resource_id) },
    { key: 'series_code', label: t('numbering.seriesCode'), value: row.series_code },
    { key: 'name', label: t('numbering.name'), value: row.name },
    { key: 'scope_type', label: t('numbering.scope'), value: enumLabel('scope', row.scope_type) },
    { key: 'pattern', label: t('numbering.pattern'), value: row.pattern },
    {
      key: 'reset_policy',
      label: t('numbering.resetPolicy'),
      value: enumLabel('reset', row.reset_policy),
    },
    {
      key: 'generation_mode',
      label: t('numbering.generationMode'),
      value: enumLabel('mode', row.generation_mode),
    },
    { key: 'start_number', label: t('numbering.startNumber'), value: row.start_number },
    { key: 'increment_by', label: t('numbering.incrementBy'), value: String(row.increment_by) },
    {
      key: 'is_default',
      label: t('numbering.default'),
      value: row.is_default ? t('common.yes') : t('common.no'),
    },
    { key: 'issued', label: t('numbering.issued'), value: issuedLabel(row) },
    { key: 'status', label: t('numbering.status'), status: row.status },
  ]
})

const columns: readonly ListColumn<NumberingSeriesRow>[] = [
  { key: 'series_code', labelKey: 'numbering.seriesCode', sortable: false },
  { key: 'name', labelKey: 'numbering.name', sortable: false },
  {
    key: 'scope_type',
    labelKey: 'numbering.scope',
    value: (row) => enumLabel('scope', row.scope_type),
  },
  { key: 'pattern', labelKey: 'numbering.pattern', sortable: false },
  {
    key: 'reset_policy',
    labelKey: 'numbering.resetPolicy',
    value: (row) => enumLabel('reset', row.reset_policy),
  },
  {
    key: 'generation_mode',
    labelKey: 'numbering.generationMode',
    value: (row) => enumLabel('mode', row.generation_mode),
  },
  {
    key: 'is_default',
    labelKey: 'numbering.default',
    value: (row) => (row.is_default ? t('common.yes') : t('common.no')),
    align: 'center',
  },
  { key: 'issued', labelKey: 'numbering.issued', value: issuedLabel, align: 'center' },
  { key: 'status', labelKey: 'numbering.status', align: 'center' },
  { key: 'actions', labelKey: 'numbering.actions', align: 'end' },
]

const tokens = [
  { value: 'SEQ', labelKey: 'numbering.tokenSeq' },
  { value: 'YYYY', labelKey: 'numbering.tokenYear' },
  { value: 'YY', labelKey: 'numbering.tokenYearShort' },
  { value: 'MM', labelKey: 'numbering.tokenMonth' },
  { value: 'DD', labelKey: 'numbering.tokenDay' },
  { value: 'COMPANY_CODE', labelKey: 'numbering.tokenCompany' },
  { value: 'ORG_CODE', labelKey: 'numbering.tokenOrganization' },
  { value: 'RESOURCE', labelKey: 'numbering.tokenResource' },
] as const

function resourceLabel(resourceId: string): string {
  const item = resources.value.find((candidate) => String(candidate.id) === String(resourceId))
  return item ? `${item.type} - ${item.name}` : `#${resourceId}`
}

function enumLabel(kind: 'scope' | 'reset' | 'mode', value: string): string {
  const translated = t(
    `numbering.${kind === 'scope' ? `scope${value[0]}${value.slice(1).toLowerCase()}` : kind === 'reset' ? `reset${value[0]}${value.slice(1).toLowerCase()}` : value === 'AUTO_REQUIRED' ? 'modeRequired' : 'modeIfEmpty'}`,
  )
  return translated.startsWith('numbering.') ? value : translated
}

function issuedState(row: NumberingSeriesRow): boolean | null {
  if (issuedLockedIds.value.has(String(row.id))) return true
  if (row.issued === true || row.is_issued === true) return true
  if (row.issued === false || row.is_issued === false) return false
  return null
}

function issuedLabel(row: NumberingSeriesRow): string {
  const state = issuedState(row)
  return state === true
    ? t('numbering.issuedYes')
    : state === false
      ? t('numbering.issuedNo')
      : t('numbering.issuedUnknown')
}

function resetForm(): void {
  form.value = {
    series_code: '',
    name: '',
    scope_type: 'GROUP',
    pattern: '',
    reset_policy: 'NEVER',
    generation_mode: 'AUTO_REQUIRED',
    start_number: '1',
    increment_by: '1',
    is_default: false,
    status: 'ACTIVE',
  }
  seqWidth.value = '6'
  validation.value = null
  formError.value = null
}

function isIssued(row: NumberingSeriesRow | null): boolean {
  return row ? issuedState(row) === true : false
}

function currentRow(): NumberingSeriesRow | null {
  return rows.value.find((row) => String(row.id) === editingId.value) ?? null
}

function applyPageSize(value: number): void {
  pageSize.value = value
  page.value = Math.min(page.value, pageCount.value)
}

async function loadResources(): Promise<void> {
  loadingResources.value = true
  error.value = null
  try {
    const result = await loadLookupPages(listResourcePage, { sort: 'type' })
    resources.value = result
    if (!selectedResourceId.value && result[0]) selectedResourceId.value = String(result[0].id)
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    loadingResources.value = false
  }
}

async function loadSeries(): Promise<void> {
  if (!selectedResourceId.value) {
    rows.value = []
    page.value = 1
    return
  }
  loadingSeries.value = true
  error.value = null
  try {
    const result = await listNumberingSeriesPage(selectedResourceId.value)
    rows.value = result.data
    page.value = 1
  } catch (caught: unknown) {
    rows.value = []
    error.value = caught
    reportApiError(caught)
  } finally {
    loadingSeries.value = false
  }
}

function openCreate(): void {
  if (!selectedResourceId.value) return
  editingId.value = undefined
  resetForm()
  markClean()
  modalOpen.value = true
}

function fillForm(row: NumberingSeriesRow): void {
  form.value = {
    series_code: row.series_code,
    name: row.name,
    scope_type: row.scope_type,
    pattern: row.pattern,
    reset_policy: row.reset_policy,
    generation_mode: row.generation_mode,
    start_number: String(row.start_number),
    increment_by: String(row.increment_by),
    is_default: row.is_default,
    status: row.status,
  }
  validation.value = null
  formError.value = null
}

function openEdit(row: NumberingSeriesRow): void {
  editingId.value = String(row.id)
  selectedResourceId.value = String(row.resource_id)
  fillForm(row)
  markClean()
  modalOpen.value = true
}

async function openView(row: NumberingSeriesRow): Promise<void> {
  viewOpen.value = true
  viewLoading.value = true
  viewRow.value = row
  try {
    viewRow.value = await getNumberingSeriesById(String(row.resource_id), String(row.id))
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    viewLoading.value = false
  }
}

function closeModal(): void {
  if (busy.value || !confirmDiscard()) return
  markClean()
  modalOpen.value = false
  editingId.value = undefined
}

function insertToken(token: string): void {
  const width = Math.max(1, Number.parseInt(seqWidth.value, 10) || 6)
  const value = token === 'SEQ' ? `{SEQ:${width}}` : `{${token}}`
  form.value.pattern = `${form.value.pattern}${value}`
}

function fullPayload(): NumberingSeriesCreate {
  return {
    series_code: form.value.series_code.trim(),
    name: form.value.name.trim(),
    scope_type: form.value.scope_type,
    pattern: form.value.pattern.trim(),
    reset_policy: form.value.reset_policy,
    generation_mode: form.value.generation_mode,
    start_number: form.value.start_number.trim(),
    increment_by: Number(form.value.increment_by),
    is_default: form.value.is_default,
    status: form.value.status,
  }
}

function editablePayload(): NumberingSeriesPatch {
  const payload: NumberingSeriesPatch = {
    name: form.value.name.trim(),
    pattern: form.value.pattern.trim(),
    generation_mode: form.value.generation_mode,
    start_number: form.value.start_number.trim(),
    increment_by: Number(form.value.increment_by),
    is_default: form.value.is_default,
    status: form.value.status,
  }
  if (!isIssued(currentRow())) {
    payload.series_code = form.value.series_code.trim()
    payload.scope_type = form.value.scope_type
    payload.reset_policy = form.value.reset_policy
  }
  return payload
}

function sampleFromPattern(pattern: string): string {
  const now = new Date()
  return pattern
    .replace(/\{SEQ(?::(\d+))?\}/g, (_match, width: string | undefined) =>
      '1'.padStart(Number(width ?? 1), '0'),
    )
    .replace(/\{YYYY\}/g, String(now.getFullYear()))
    .replace(/\{YY\}/g, String(now.getFullYear()).slice(-2))
    .replace(/\{MM\}/g, String(now.getMonth() + 1).padStart(2, '0'))
    .replace(/\{DD\}/g, String(now.getDate()).padStart(2, '0'))
    .replace(/\{COMPANY_CODE\}/g, 'COMPANY')
    .replace(/\{ORG_CODE\}/g, 'ORG')
    .replace(/\{RESOURCE\}/g, resource.value?.type ?? 'RESOURCE')
}

async function validateConfig(): Promise<void> {
  if (!selectedResourceId.value) return
  validationBusy.value = true
  validation.value = null
  formError.value = null
  try {
    validation.value = await validateNumberingSeries(selectedResourceId.value, fullPayload())
  } catch (caught: unknown) {
    formError.value = caught
    reportApiError(caught)
  } finally {
    validationBusy.value = false
  }
}

async function save(): Promise<void> {
  if (!selectedResourceId.value) return
  busy.value = true
  formError.value = null
  validation.value = null
  try {
    await saveNumberingSeries(
      selectedResourceId.value,
      editingId.value ? editablePayload() : fullPayload(),
      editingId.value,
    )
    markClean()
    modalOpen.value = false
    editingId.value = undefined
    await loadSeries()
  } catch (caught: unknown) {
    formError.value = caught
    const notice = normalizeApiError(caught)
    if (notice?.code === 'NUM_ISSUED_FIELD_IMMUTABLE' && editingId.value) {
      issuedLockedIds.value = new Set(issuedLockedIds.value).add(editingId.value)
    }
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

async function toggle(row: NumberingSeriesRow): Promise<void> {
  if (!selectedResourceId.value || !window.confirm(t('common.confirmStateChange'))) return
  busy.value = true
  error.value = null
  try {
    await changeNumberingSeriesState(
      selectedResourceId.value,
      String(row.id),
      row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE',
    )
    await loadSeries()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

watch(selectedResourceId, () => void loadSeries())
watch(modalOpen, (open) => {
  if (open) void nextTick(() => formCard.value?.focus())
})

onMounted(() => {
  if (canConfigure.value) void loadResources()
})
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('numbering.title') }}</h1>
      <p>{{ t('numbering.description') }}</p>
    </div>
    <button v-if="canConfigure" type="button" class="primary-button" :disabled="!selectedResourceId || loadingResources"
      @click="openCreate">
      {{ t('numbering.create') }}
    </button>
  </div>

  <section class="content-panel numbering-panel">
    <form class="numbering-query-controls" role="search" @submit.prevent="void loadSeries()">
      <label>
        <span>{{ t('numbering.resource') }}</span>
        <select v-model="selectedResourceId" :disabled="!canConfigure || loadingResources"
          :aria-label="t('numbering.selectResource')">
          <option value="">{{ t('numbering.selectResource') }}</option>
          <option v-for="item in resources" :key="String(item.id)" :value="String(item.id)">
            {{ item.type }} - {{ item.name }}
          </option>
        </select>
      </label>
      <button type="submit" class="secondary-button" :disabled="!selectedResourceId || loadingSeries">
        <NIcon :component="RefreshOutline" aria-hidden="true" />
        {{ t('common.refresh') }}
      </button>
    </form>

    <p v-if="errorMessage" class="inline-error" role="alert">{{ errorMessage }}</p>
    <p v-if="!canConfigure" class="permission-note" role="status">{{ t('common.noPermission') }}</p>

    <ListPagination :aria-label="t('numbering.topPagination')" :page="page" :page-size="pageSize" :total="rows.length"
      :disabled="loadingSeries || !selectedResourceId" @update:page="page = $event" @update:page-size="applyPageSize" />

    <ListShell :initial-loading="loadingResources || loadingSeries" :has-data="visibleRows.length > 0"
      :empty-dataset="Boolean(selectedResourceId) && !loadingSeries && rows.length === 0" :empty-filtered-result="false"
      :error="error">
      <template #empty>
        <p>{{ t('numbering.noSeries') }}</p>
      </template>
      <ListTable :rows="visibleRows" :columns="columns">
        <template #cell-scope_type="{ row }">{{ enumLabel('scope', row.scope_type) }}</template>
        <template #cell-reset_policy="{ row }">{{ enumLabel('reset', row.reset_policy) }}</template>
        <template #cell-generation_mode="{ row }">{{
          enumLabel('mode', row.generation_mode)
          }}</template>
        <template #cell-issued="{ row }">{{ issuedLabel(row) }}</template>
        <template #cell-actions="{ row }">
          <TableRowActions :active="row.status === 'ACTIVE'" :busy="busy" :show-view="canConfigure"
            :show-edit="canConfigure" :show-toggle="canConfigure" view-permission="resource.configure_numbering"
            edit-permission="resource.configure_numbering" toggle-permission="resource.configure_numbering"
            :view-label="t('common.view')" :edit-label="t('common.edit')" :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')" @view="void openView(row)" @edit="openEdit(row)"
            @toggle="void toggle(row)" />
        </template>
      </ListTable>
      <ListPagination :aria-label="t('numbering.bottomPagination')" :page="page" :page-size="pageSize"
        :total="rows.length" :disabled="loadingSeries" @update:page="page = $event" @update:page-size="applyPageSize" />
    </ListShell>
  </section>

  <div v-if="modalOpen" class="modal-backdrop">
    <section ref="formCard" class="modal-card" role="dialog" aria-modal="true" :aria-label="t('numbering.form')"
      tabindex="-1">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24">
            <QrCodeOutline />
          </NIcon>
          {{ editingId ? t('numbering.edit') : t('numbering.create') }}
        </h2>
        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')" @click="closeModal">
        </button>
      </header>
      <form id="numbering-form" class="modal-card__body" @submit.prevent="void save()">
        <label>
          <span>{{ t('numbering.resource') }}</span>
          <select v-model="selectedResourceId" required :disabled="Boolean(editingId)">
            <option value="">{{ t('numbering.selectResource') }}</option>
            <option v-for="item in resources" :key="String(item.id)" :value="String(item.id)">
              {{ item.type }} - {{ item.name }}
            </option>
          </select>
        </label>
        <label>
          <span>{{ t('numbering.seriesCode') }}</span>
          <input v-model="form.series_code" required :disabled="isIssued(currentRow())" />
        </label>
        <label>
          <span>{{ t('numbering.name') }}</span>
          <input v-model="form.name" required />
        </label>
        <label>
          <span>{{ t('numbering.scope') }}</span>
          <select v-model="form.scope_type" required :disabled="isIssued(currentRow())">
            <option value="GROUP">{{ t('numbering.scopeGroup') }}</option>
            <option value="COMPANY">{{ t('numbering.scopeCompany') }}</option>
            <option value="ORGANIZATION">{{ t('numbering.scopeOrganization') }}</option>
          </select>
        </label>
        <label>
          <span>{{ t('numbering.pattern') }}</span>
          <input v-model="form.pattern" required aria-describedby="numbering-pattern-note" />
        </label>
        <div class="pattern-builder" aria-label="Pattern builder">
          <span>{{ t('numbering.patternBuilder') }}</span>
          <label class="pattern-builder__width">
            <span>{{ t('numbering.sequenceWidth') }}</span>
            <input v-model="seqWidth" type="number" min="1" max="18" inputmode="numeric" />
          </label>
          <div class="pattern-builder__tokens">
            <button v-for="token in tokens" :key="token.value" type="button" class="token-button"
              :aria-label="`${t('numbering.insertToken')}: ${t(token.labelKey)}`" @click="insertToken(token.value)">
              {{ token.value }}
            </button>
          </div>
          <small id="numbering-pattern-note">{{ t('numbering.immutableNote') }}</small>
        </div>
        <label>
          <span>{{ t('numbering.resetPolicy') }}</span>
          <select v-model="form.reset_policy" required :disabled="isIssued(currentRow())">
            <option value="NEVER">{{ t('numbering.resetNever') }}</option>
            <option value="YEARLY">{{ t('numbering.resetYearly') }}</option>
            <option value="MONTHLY">{{ t('numbering.resetMonthly') }}</option>
            <option value="DAILY">{{ t('numbering.resetDaily') }}</option>
          </select>
        </label>
        <label>
          <span>{{ t('numbering.generationMode') }}</span>
          <select v-model="form.generation_mode" required>
            <option value="AUTO_REQUIRED">{{ t('numbering.modeRequired') }}</option>
            <option value="AUTO_IF_EMPTY">{{ t('numbering.modeIfEmpty') }}</option>
          </select>
        </label>
        <label>
          <span>{{ t('numbering.startNumber') }}</span>
          <input v-model="form.start_number" required inputmode="numeric" pattern="[0-9]+" />
        </label>
        <label>
          <span>{{ t('numbering.incrementBy') }}</span>
          <input v-model="form.increment_by" required type="number" min="1" step="1" />
        </label>
        <label class="checkbox-field">
          <input v-model="form.is_default" type="checkbox" />
          <span>{{ t('numbering.default') }}</span>
        </label>
        <label>
          <span>{{ t('numbering.status') }}</span>
          <select v-model="form.status" required>
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select>
        </label>

        <section class="validation-panel" aria-live="polite">
          <div class="validation-panel__header">
            <h3>{{ t('numbering.validationPreview') }}</h3>
            <button type="button" class="secondary-button" :disabled="validationBusy || !canConfigure"
              @click="void validateConfig()">
              {{ validationBusy ? t('common.loading') : t('numbering.validate') }}
            </button>
          </div>
          <p v-if="validation" :class="validation.valid ? 'validation-ok' : 'validation-fail'">
            {{
              validation.valid ? t('numbering.validationValid') : t('numbering.validationInvalid')
            }}
          </p>
          <dl v-if="validation" class="validation-results">
            <dt>{{ t('numbering.normalizedPattern') }}</dt>
            <dd>{{ validation.normalized_pattern }}</dd>
            <dt>{{ t('numbering.sample') }}</dt>
            <dd>{{ sampleFromPattern(validation.normalized_pattern) }}</dd>
          </dl>
          <ul v-if="validation?.validation_messages.length" class="validation-messages">
            <li v-for="message in validation.validation_messages" :key="message">{{ message }}</li>
          </ul>
          <small>{{ t('numbering.sampleNote') }}</small>
        </section>
        <p v-if="isIssued(currentRow())" class="immutable-note" role="status">
          {{ t('numbering.immutableDetected') }}
        </p>
        <p v-if="formError" class="inline-error" role="alert">{{ errorMessage }}</p>

      </form>
      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="closeModal">
          <NIcon :size="24"> <Refresh /> </NIcon>
          {{ t('common.cancel') }}
        </button>
        <button type="submit" class="primary-button" :disabled="busy || !canConfigure">
          <NIcon :size="24"> <SaveOutline /> </NIcon>
          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
    </section>
  </div>

  <TableDetailModal :open="viewOpen" :title="viewLoading ? t('common.loading') : t('numbering.view')"
    :fields="detailFields" @close="viewOpen = false" />
</template>

<style scoped>
.numbering-panel {
  display: grid;
  gap: 16px;
  padding: 18px 20px 20px;
}

.numbering-query-controls {
  display: flex;
  align-items: end;
  gap: 10px;
  flex-wrap: wrap;
}

.numbering-query-controls label,
.modal-card__body label {
  display: grid;
  gap: 5px;
  color: #536b7b;
  font-size: 12px;
  font-weight: 600;
}

.numbering-query-controls label {
  min-width: min(440px, 100%);
}

.numbering-query-controls select,
.modal-card__body input,
.modal-card__body select {
  min-height: 34px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 9px;
  background: #fff;
  color: #183b57;
  font: inherit;
}

.inline-error {
  margin: 0;
  color: #a23a32;
  font-size: 12px;
}

.permission-note,
.immutable-note {
  margin: 0;
  color: #536b7b;
  font-size: 12px;
}

.modal-card {
  width: min(100%, 760px);
  max-height: calc(100vh - 40px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}


.checkbox-field {
  display: flex !important;
  align-items: center;
  gap: 8px !important;
}

.checkbox-field input {
  min-height: auto;
}

.pattern-builder,
.validation-panel {
  display: grid;
  gap: 9px;
  padding: 12px;
  border: 1px solid #dbe5ea;
  border-radius: 6px;
  background: #f8fbfc;
}

.pattern-builder__width {
  max-width: 180px;
}

.pattern-builder__tokens {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.token-button {
  min-height: 30px;
  border: 1px solid #b8ccd8;
  border-radius: 4px;
  padding: 4px 8px;
  background: #fff;
  color: #294b65;
  cursor: pointer;
  font: inherit;
  font-size: 12px;
}

.validation-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.validation-results {
  display: grid;
  grid-template-columns: minmax(130px, 0.35fr) minmax(0, 1fr);
  gap: 6px 12px;
  margin: 0;
}

.validation-results dt {
  color: #536b7b;
  font-weight: 700;
}

.validation-results dd {
  margin: 0;
  overflow-wrap: anywhere;
}

.validation-ok {
  margin: 0;
  color: #15803d;
}

.validation-fail {
  margin: 0;
  color: #a23a32;
}

.validation-messages {
  margin: 0;
  padding-left: 20px;
  color: #a23a32;
}
</style>
