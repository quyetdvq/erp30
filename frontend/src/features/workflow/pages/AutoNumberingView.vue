<script setup lang="ts">
import AppDialogHost from '@/components/ui/AppDialogHost.vue'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import { rowAllowedActions } from '@/components/lists/rowActionPolicy'
import { computed, nextTick, ref, shallowRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'


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
  listAllNumberingSeries,
  numberSeriesSnapshotForAll,
  numberSeriesSnapshotForResource,
  numberSeriesSnapshotWithMutation,
  saveNumberingSeries,
  type NumberingSeriesSnapshot,
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
// Effective row-action hints come from the authorized Resource catalog. They
// narrow UI choices; every numbering request is still authorized by the API.
const configurableResources = computed(() =>
  resources.value.filter((item) => rowAllowedActions(item)?.configure_numbering === true),
)
const resourceById = computed(() =>
  new Map(resources.value.map((item) => [String(item.id), item])),
)
const configurableResourceIds = computed(() =>
  configurableResources.value.map((item) => String(item.id)),
)
const resourceFilterOptions = computed(() =>
  configurableResources.value.map((item) => ({
    value: String(item.id),
    label: `${item.type} - ${item.name}`,
  })),
)
// Empty means All resource; this is a listing filter, not the form's Resource.
const selectedResourceId = ref('')
const formResourceId = ref('')
// Kept only for the lifetime of this view and cleared on permission/catalog
// changes. All and per-Resource filters share the same complete snapshots.
const seriesSnapshots = shallowRef<NumberingSeriesSnapshot>(new Map())
const rows = computed<NumberingSeriesRow[]>(() => {
  const ids = selectedResourceId.value ? [selectedResourceId.value] : configurableResourceIds.value
  // Never render an incomplete All-Resource aggregation as a full result.
  if (ids.some((id) => !seriesSnapshots.value.has(id))) return []
  return ids.flatMap((id) => [...(seriesSnapshots.value.get(id) ?? [])])
})
const page = ref(1)
const pageSize = ref(50)
const loadingResources = ref(false)
const loadingSeries = ref(false)
// Prevent repeated Resource GET -> 403 -> auth refresh -> Resource GET loops.
// Explicit Refresh can retry after the server has updated the permission snapshot.
const deniedReadUntilRefresh = ref(false)
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

const formDraft = computed(() => ({ ...form.value, resource_id: formResourceId.value }))
const { markClean, confirmDiscard } = useUnsavedChanges(formDraft, modalOpen)

const resource = computed(() => resourceById.value.get(formResourceId.value))
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
  { key: 'resource_id', labelKey: 'numbering.resource', value: (row) => resourceLabel(row.resource_id) },
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
  const item = resourceById.value.get(String(resourceId))
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
  return (seriesSnapshots.value.get(formResourceId.value) ?? [])
    .find((row) => String(row.id) === editingId.value) ?? null
}

function setResourceFilter(value: string | string[]): void {
  // Clearing the searchable select means All resource; it is not a real API ID.
  selectedResourceId.value = typeof value === 'string' ? value : ''
}

function applyPageSize(value: number): void {
  pageSize.value = value
  page.value = Math.min(page.value, pageCount.value)
}

let resourceLoadGeneration = 0
let seriesLoadGeneration = 0

function clearSeriesSnapshots(): void {
  ++seriesLoadGeneration
  seriesSnapshots.value = new Map()
  loadingSeries.value = false
}

function stopAfterDeniedRead(caught: unknown): boolean {
  if (normalizeApiError(caught)?.status !== 403) return false
  deniedReadUntilRefresh.value = true
  clearSeriesSnapshots()
  resources.value = []
  error.value = caught
  reportApiError(caught)
  return true
}

async function refreshSeries(): Promise<void> {
  if (!canConfigure.value) return
  if (deniedReadUntilRefresh.value) {
    deniedReadUntilRefresh.value = false
    await loadResources() // re-check the authorized catalog before any series GET.
  } else {
    await loadSeries(true)
  }
}

async function loadResources(): Promise<void> {
  const generation = ++resourceLoadGeneration
  clearSeriesSnapshots()
  resources.value = []
  loadingResources.value = true
  error.value = null
  try {
    const result = await loadLookupPages((query) => listResourcePage(query, true), { sort: 'type' })
    if (generation !== resourceLoadGeneration || !canConfigure.value) return
    resources.value = result
    // Losing a Resource hint cannot leave its old filter or cached rows visible.
    if (selectedResourceId.value && !configurableResourceIds.value.includes(selectedResourceId.value)) {
      selectedResourceId.value = '' // Its watcher performs the only list read.
    } else {
      await loadSeries()
    }
  } catch (caught: unknown) {
    if (stopAfterDeniedRead(caught)) return
    if (generation !== resourceLoadGeneration || !canConfigure.value) return
    clearSeriesSnapshots()
    error.value = caught
    reportApiError(caught)
  } finally {
    if (generation === resourceLoadGeneration) loadingResources.value = false
  }
}

/**
 * Resource filter changes are local when a complete snapshot is available.
 * Only initial/missing data and explicit Refresh contact the existing
 * Resource-scoped API. No global All Resource endpoint is introduced.
 */
async function loadSeries(force = false): Promise<void> {
  const generation = ++seriesLoadGeneration
  if (!canConfigure.value || deniedReadUntilRefresh.value) {
    if (!canConfigure.value) seriesSnapshots.value = new Map()
    loadingSeries.value = false
    return
  }
  const resourceId = selectedResourceId.value
  const ids = resourceId ? [resourceId] : configurableResourceIds.value
  // A stale Resource selector must never issue an unapproved API request.
  if (resourceId && !configurableResourceIds.value.includes(resourceId)) {
    seriesSnapshots.value = new Map()
    loadingSeries.value = false
    return
  }
  error.value = null
  if (!force && ids.every((id) => seriesSnapshots.value.has(id))) {
    loadingSeries.value = false
    return
  }
  loadingSeries.value = true
  try {
    if (resourceId) {
      const result = await listNumberingSeriesPage(resourceId)
      if (generation !== seriesLoadGeneration || !canConfigure.value) return
      seriesSnapshots.value = numberSeriesSnapshotForResource(seriesSnapshots.value, resourceId, result.data)
    } else {
      const data = await listAllNumberingSeries(ids)
      if (generation !== seriesLoadGeneration || !canConfigure.value) return
      seriesSnapshots.value = numberSeriesSnapshotForAll(ids, data)
    }
    page.value = Math.min(page.value, pageCount.value)
  } catch (caught: unknown) {
    if (stopAfterDeniedRead(caught)) return
    if (generation !== seriesLoadGeneration || !canConfigure.value) return
    // Fail closed: never keep stale cached records after a Resource-scoped
    // authorization or data-integrity failure.
    seriesSnapshots.value = new Map()
    error.value = caught
    reportApiError(caught)
  } finally {
    if (generation === seriesLoadGeneration) loadingSeries.value = false
  }
}

/** Use the canonical mutation response instead of N Resource GETs. */
async function reconcileMutation(resourceId: string, row: NumberingSeriesRow): Promise<void> {
  ++seriesLoadGeneration // Pending list responses must not overwrite this write.
  loadingSeries.value = false
  if (!canConfigure.value || !configurableResourceIds.value.includes(resourceId)) {
    clearSeriesSnapshots()
    return
  }
  const hasSnapshot = seriesSnapshots.value.has(resourceId)
  seriesSnapshots.value = numberSeriesSnapshotWithMutation(seriesSnapshots.value, resourceId, row)
  // A write cannot establish completeness for a Resource that was not loaded.
  if (!hasSnapshot) await loadSeries(true)
  page.value = Math.min(page.value, pageCount.value)
}

function isAccessDenied(caught: unknown): boolean {
  const status = normalizeApiError(caught)?.status
  return status === 401 || status === 403
}

function openCreate(): void {
  if (!configurableResources.value.length || loadingResources.value) return
  editingId.value = undefined
  formResourceId.value = selectedResourceId.value
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
  formResourceId.value = String(row.resource_id)
  fillForm(row)
  markClean()
  modalOpen.value = true
}

let viewLoadGeneration = 0
async function openView(row: NumberingSeriesRow): Promise<void> {
  const generation = ++viewLoadGeneration
  viewOpen.value = true
  viewLoading.value = true
  viewRow.value = row
  try {
    const detail = await getNumberingSeriesById(String(row.resource_id), String(row.id))
    if (generation !== viewLoadGeneration || !viewOpen.value || !canConfigure.value) return
    if (String(detail.id) !== String(row.id) || String(detail.resource_id) !== String(row.resource_id)) {
      throw new Error('Numbering detail returned a different series or Resource.')
    }
    viewRow.value = detail
  } catch (caught: unknown) {
    if (generation !== viewLoadGeneration || !viewOpen.value || !canConfigure.value) return
    if (stopAfterDeniedRead(caught) || isAccessDenied(caught)) {
      clearSeriesSnapshots()
      viewOpen.value = false
      viewRow.value = null
    }
    error.value = caught
    reportApiError(caught)
  } finally {
    if (generation === viewLoadGeneration) viewLoading.value = false
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

let validationGeneration = 0
async function validateConfig(): Promise<void> {
  if (!formResourceId.value || validationBusy.value || busy.value || !canConfigure.value) return
  const generation = ++validationGeneration
  validationBusy.value = true
  validation.value = null
  formError.value = null
  try {
    const result = await validateNumberingSeries(formResourceId.value, fullPayload(), editingId.value)
    if (generation !== validationGeneration || !modalOpen.value || !canConfigure.value) return
    validation.value = result
  } catch (caught: unknown) {
    if (generation !== validationGeneration || !modalOpen.value || !canConfigure.value) return
    formError.value = caught
    reportApiError(caught)
  } finally {
    if (generation === validationGeneration) validationBusy.value = false
  }
}

async function save(): Promise<void> {
  if (!formResourceId.value || busy.value || !canConfigure.value) return
  ++validationGeneration
  validationBusy.value = false
  const resourceId = formResourceId.value
  const editId = editingId.value
  const authGeneration = resourceLoadGeneration
  busy.value = true
  formError.value = null
  validation.value = null
  try {
    const saved = await saveNumberingSeries(
      resourceId,
      editId ? editablePayload() : fullPayload(),
      editId,
    )
    if (authGeneration !== resourceLoadGeneration || !canConfigure.value) return
    if (editId && String(saved.id) !== editId) {
      throw new Error('Numbering update returned a different series.')
    }
    await reconcileMutation(resourceId, saved)
    markClean()
    modalOpen.value = false
    editingId.value = undefined
  } catch (caught: unknown) {
    if (authGeneration !== resourceLoadGeneration || !canConfigure.value) return
    if (!stopAfterDeniedRead(caught) && isAccessDenied(caught)) clearSeriesSnapshots()
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
  if (busy.value || !canConfigure.value || !window.confirm(t('common.confirmStateChange'))) return
  const authGeneration = resourceLoadGeneration
  busy.value = true
  error.value = null
  try {
    const updated = await changeNumberingSeriesState(
      String(row.resource_id),
      String(row.id),
      row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE',
    )
    if (authGeneration !== resourceLoadGeneration || !canConfigure.value) return
    if (String(updated.id) !== String(row.id)) {
      throw new Error('Numbering state update returned a different series.')
    }
    await reconcileMutation(String(row.resource_id), updated)
  } catch (caught: unknown) {
    if (authGeneration !== resourceLoadGeneration || !canConfigure.value) return
    if (!stopAfterDeniedRead(caught) && isAccessDenied(caught)) clearSeriesSnapshots()
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

watch(selectedResourceId, () => {
  page.value = 1
  void loadSeries()
})
// A late validation result cannot describe an edited draft or a new dialog.
watch(formDraft, () => {
  ++validationGeneration
  validationBusy.value = false
  validation.value = null
}, { deep: true })
watch(viewOpen, (open) => {
  if (!open) {
    ++viewLoadGeneration
    viewLoading.value = false
  }
})
watch(modalOpen, (open) => {
  if (!open) {
    ++validationGeneration
    validationBusy.value = false
  }

  if (open) void nextTick(() => formCard.value?.focus())
})

// Direct route entry can mount before /auth/me resolves. Fetch on permission
// readiness instead of a one-shot mount check; invalidate pending reads on loss.
watch(canConfigure, (authorized) => {
  if (authorized) {
    if (!deniedReadUntilRefresh.value) void loadResources()
    return
  }
  ++resourceLoadGeneration
  clearSeriesSnapshots()
  ++viewLoadGeneration
  ++validationGeneration
  resources.value = []
  loadingResources.value = false
  loadingSeries.value = false
  selectedResourceId.value = ''
  formResourceId.value = ''
  if (modalOpen.value) markClean()
  modalOpen.value = false
  viewOpen.value = false
  viewLoading.value = false
  validationBusy.value = false
}, { immediate: true })
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('numbering.title') }}</h1>
      <p>{{ t('numbering.description') }}</p>
    </div>
    <button data-ds-icon="add" v-if="canConfigure" type="button" class="primary-button" :disabled="!configurableResources.length || loadingResources"
      @click="openCreate">
      {{ t('numbering.create') }}
    </button>
  </div>

  <section class="content-panel numbering-panel ds-page-panel ds-page-panel--relaxed">
    <div class="ds-filter-container">
    <form class="numbering-query-controls ds-filter-form" role="search" @submit.prevent="void refreshSeries()">
      <div class="ds-filter-fields">
      <label class="numbering-resource-filter">
        <span>{{ t('numbering.resource') }}</span>
        <SearchableSelect
          :model-value="selectedResourceId"
          :options="resourceFilterOptions"
          :placeholder="t('numbering.allResources')"
          :aria-label="t('numbering.resource')"
          :disabled="!canConfigure || loadingResources"
          :clearable="true"
          @update:model-value="setResourceFilter"
        />
      </label>
      </div>
      <div class="ds-filter-actions">
      <button type="submit" class="secondary-button" :disabled="loadingResources || loadingSeries" data-ds-icon="refresh">

        {{ t('common.refresh') }}
      </button>
      </div>
    </form>
    </div>

    <p v-if="errorMessage" class="inline-error" role="alert">{{ errorMessage }}</p>
    <p v-if="!canConfigure" class="permission-note" role="status">{{ t('common.noPermission') }}</p>

    <ListPagination :aria-label="t('numbering.topPagination')" :page="page" :page-size="pageSize" :total="rows.length"
      :disabled="loadingResources || loadingSeries" @update:page="page = $event" @update:page-size="applyPageSize" />

    <ListShell :initial-loading="loadingResources || (loadingSeries && rows.length === 0)"
      :refreshing="loadingSeries && rows.length > 0" :has-data="visibleRows.length > 0"
      :empty-dataset="!loadingResources && !loadingSeries && rows.length === 0" :empty-filtered-result="false"
      :error="error">
      <template #empty>
        <p>{{ t('numbering.noSeries') }}</p>
      </template>
      <ListTable :rows="visibleRows" :columns="columns" :busy="loadingSeries">
        <template #cell-scope_type="{ row }">{{ enumLabel('scope', row.scope_type) }}</template>
        <template #cell-reset_policy="{ row }">{{ enumLabel('reset', row.reset_policy) }}</template>
        <template #cell-generation_mode="{ row }">{{
          enumLabel('mode', row.generation_mode)
          }}</template>
        <template #cell-issued="{ row }">{{ issuedLabel(row) }}</template>
        <template #cell-actions="{ row }">
          <TableRowActions authorization-mode="effective" :view-scope-verified="true" view-action-code="configure_numbering" :allowed-actions="rowAllowedActions(resourceById.get(String(row.resource_id)))" :action-codes="{ edit: 'configure_numbering', toggle: 'configure_numbering' }" :active="row.status === 'ACTIVE'" :busy="busy || loadingSeries" :show-view="canConfigure"
            :show-edit="canConfigure" :show-toggle="canConfigure" view-permission="resource.configure_numbering"
            edit-permission="resource.configure_numbering" toggle-permission="resource.configure_numbering"
            :view-label="t('common.view')" :edit-label="t('common.edit')" :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')" @view="void openView(row)" @edit="openEdit(row)"
            @toggle="void toggle(row)" />
        </template>
      </ListTable>
      <ListPagination :aria-label="t('numbering.bottomPagination')" :page="page" :page-size="pageSize" :show-page-size="false"
        :total="rows.length" :disabled="loadingResources || loadingSeries" @update:page="page = $event" @update:page-size="applyPageSize" />
    </ListShell>
  </section>

  <AppDialogHost :open="modalOpen" size="lg" @close="closeModal">
    <section v-if="modalOpen" ref="formCard" class="ds-dialog-surface modal-card" role="dialog" aria-modal="true" :aria-label="t('numbering.form')"
      tabindex="-1">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24" aria-hidden="true">
            <QrCodeOutline />
          </NIcon>
          {{ editingId ? t('numbering.edit') : t('numbering.create') }}
        </h2>
        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')" @click="closeModal" data-ds-icon="close">
        </button>
      </header>
      <form id="numbering-form" class="modal-card__body ds-dialog-form-fields" @submit.prevent="void save()">
        <label>
          <span>{{ t('numbering.resource') }}</span>
          <select v-model="formResourceId" required :disabled="Boolean(editingId)">
            <option value="">{{ t('numbering.selectResource') }}</option>
            <option v-for="item in configurableResources" :key="String(item.id)" :value="String(item.id)">
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
            <button data-ds-icon="validate" type="button" class="secondary-button" :disabled="validationBusy || busy || !canConfigure || !formResourceId"
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
        <button type="button" class="secondary-button" @click="closeModal" data-ds-icon="cancel">

          {{ t('common.cancel') }}
        </button>
        <button type="submit" form="numbering-form" class="primary-button" :disabled="busy || !canConfigure" data-ds-icon="save">

          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
    </section>
  </AppDialogHost>

  <TableDetailModal :open="viewOpen" :title="viewLoading ? t('common.loading') : t('numbering.view')"
    :fields="detailFields" @close="viewOpen = false" />
</template>

<style scoped>
.numbering-query-controls { min-width: 0; }
.numbering-query-controls label { min-width: 0; }
.numbering-resource-filter { max-width: 480px; }
.numbering-query-controls .numbering-resource-filter :deep(.n-base-selection) { min-height: var(--ds-button-height-md); }
.permission-note,
.immutable-note {
  margin: 0;
  color: var(--ds-text-secondary);
  font-size: 12px;
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
  border: 1px solid var(--ds-border-subtle);
  border-radius: 6px;
  background: var(--ds-bg-subtle);
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
  border: 1px solid var(--ds-border-subtle);
  border-radius: 4px;
  padding: 4px 8px;
  background: var(--ds-bg-surface);
  color: var(--ds-text-primary);
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
  color: var(--ds-text-secondary);
  font-weight: 700;
}

.validation-results dd {
  margin: 0;
  overflow-wrap: anywhere;
}

.validation-ok {
  margin: 0;
  color: var(--ds-success);
}

.validation-fail {
  margin: 0;
  color: var(--ds-danger);
}

.validation-messages {
  margin: 0;
  padding-left: 20px;
  color: var(--ds-danger);
}
</style>
