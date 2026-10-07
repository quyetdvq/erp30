<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

import type { DisplaySettingDefinition, DisplaySettingValue } from '@/api/generated/types.gen'
import { useQueryState } from '@/composables/useQueryState'
import {
  getDisplaySettingDefinitions,
  getDisplaySettingOrganizations,
  getDisplaySettingValues,
  resetDisplaySettingOverrides,
  setDisplaySettingOverrides,
  type DisplaySettingsContext,
} from '@/services/displaySettingsAdmin'
import { valuesToRuntime } from '@/services/displaySettings'
import {
  formatDate,
  formatDateTime,
  formatMoney,
  formatPercentage,
  formatQuantity,
  formatTime,
} from '@/presentation/formatters'
import { useAuthStore } from '@/stores/auth'
import { useDisplaySettingsStore } from '@/stores/displaySettings'

type Category = 'DATE_TIME' | 'NUMBER' | 'MONEY'

const { t } = useI18n()
const auth = useAuthStore()
const displayStore = useDisplaySettingsStore()
const contextType = ref<'SYSTEM' | 'ORGANIZATION'>('SYSTEM')
const organizationId = ref('')
const drafts = reactive<Record<string, unknown>>({})
const values = ref<DisplaySettingValue[]>([])
const pageError = ref('')
const savingKey = ref<string | null>(null)

const definitionsQuery = useQueryState({ request: getDisplaySettingDefinitions })
const organizationsQuery = useQueryState({ request: getDisplaySettingOrganizations })
const valuesQuery = useQueryState({ request: () => getDisplaySettingValues(context.value) })

const context = computed<DisplaySettingsContext>(() =>
  contextType.value === 'SYSTEM'
    ? { type: 'SYSTEM' }
    : { type: 'ORGANIZATION', organization_id: organizationId.value },
)
const definitions = computed(() => definitionsQuery.data.value ?? [])
const organizations = computed(() => organizationsQuery.data.value ?? [])
const busy = computed(
  () =>
    definitionsQuery.isLoading.value ||
    organizationsQuery.isLoading.value ||
    valuesQuery.isLoading.value,
)

const groups = computed(() =>
  (['DATE_TIME', 'NUMBER', 'MONEY'] as Category[]).map((category) => ({
    category,
    title: t(`displaySettings.categories.${category}`),
    items: definitions.value.filter((definition) => definition.category === category),
  })),
)

const fieldLabels: Record<string, string> = {
  'Display.timezone': 'displaySettings.fields.timezone',
  'Display.dateFormat': 'displaySettings.fields.dateFormat',
  'Display.dateTimeFormat': 'displaySettings.fields.dateTimeFormat',
  'Display.timeFormat': 'displaySettings.fields.timeFormat',
  'Display.firstDayOfWeek': 'displaySettings.fields.firstDayOfWeek',
  'Display.number.decimalSeparator': 'displaySettings.fields.decimalSeparator',
  'Display.number.groupSeparator': 'displaySettings.fields.groupSeparator',
  'Display.quantityDecimals': 'displaySettings.fields.quantityDecimals',
  'Display.percentageDecimals': 'displaySettings.fields.percentageDecimals',
  'Display.trailingZeros': 'displaySettings.fields.trailingZeros',
  'Display.money.decimalSeparator': 'displaySettings.fields.decimalSeparator',
  'Display.money.groupSeparator': 'displaySettings.fields.groupSeparator',
  'Display.money.symbolMode': 'displaySettings.fields.symbolMode',
  'Display.money.symbolPosition': 'displaySettings.fields.symbolPosition',
  'Display.money.decimalsMode': 'displaySettings.fields.decimalsMode',
}

const selectOptions: Record<string, Array<{ value: string; label: string }>> = {
  'Display.timezone': [
    { value: 'Asia/Ho_Chi_Minh', label: 'Asia/Ho_Chi_Minh' },
    { value: 'UTC', label: 'UTC' },
    { value: 'Asia/Tokyo', label: 'Asia/Tokyo' },
    { value: 'America/New_York', label: 'America/New_York' },
  ],
  'Display.dateFormat': [
    { value: 'DD/MM/YYYY', label: 'DD/MM/YYYY' },
    { value: 'MM/DD/YYYY', label: 'MM/DD/YYYY' },
    { value: 'YYYY-MM-DD', label: 'YYYY-MM-DD' },
  ],
  'Display.dateTimeFormat': [
    { value: 'DD/MM/YYYY HH:mm', label: 'DD/MM/YYYY HH:mm' },
    { value: 'MM/DD/YYYY HH:mm', label: 'MM/DD/YYYY HH:mm' },
    { value: 'YYYY-MM-DD HH:mm', label: 'YYYY-MM-DD HH:mm' },
  ],
  'Display.timeFormat': [
    { value: 'HH:mm', label: 'HH:mm' },
    { value: 'HH:mm:ss', label: 'HH:mm:ss' },
  ],
  'Display.firstDayOfWeek': [
    { value: 'MONDAY', label: 'Monday' },
    { value: 'SUNDAY', label: 'Sunday' },
  ],
  'Display.number.decimalSeparator': [
    { value: '.', label: 'Dấu chấm (.)' },
    { value: ',', label: 'Dấu phẩy (,)' },
  ],
  'Display.number.groupSeparator': [
    { value: ',', label: 'Dấu phẩy (,)' },
    { value: '.', label: 'Dấu chấm (.)' },
    { value: '', label: 'displaySettings.noSeparator' },
  ],
  'Display.money.decimalSeparator': [
    { value: '.', label: 'Dấu chấm (.)' },
    { value: ',', label: 'Dấu phẩy (,)' },
  ],
  'Display.money.groupSeparator': [
    { value: ',', label: 'Dấu phẩy (,)' },
    { value: '.', label: 'Dấu chấm (.)' },
    { value: '', label: 'displaySettings.noSeparator' },
  ],
  'Display.money.symbolMode': [
    { value: 'SYMBOL', label: 'SYMBOL' },
    { value: 'CODE', label: 'CODE' },
    { value: 'NONE', label: 'NONE' },
  ],
  'Display.money.symbolPosition': [
    { value: 'AUTO', label: 'AUTO' },
    { value: 'PREFIX', label: 'PREFIX' },
    { value: 'SUFFIX', label: 'SUFFIX' },
  ],
  'Display.money.decimalsMode': [
    { value: 'CURRENCY_DEFAULT', label: 'CURRENCY_DEFAULT' },
    { value: 'INTEGER', label: 'INTEGER' },
    { value: 'DECIMAL_2', label: 'DECIMAL_2' },
    { value: 'DECIMAL_3', label: 'DECIMAL_3' },
  ],
}

function contextKey(): string {
  return contextType.value === 'SYSTEM' ? 'system' : organizationId.value
}

function organizationName(id: string | null): string {
  if (!id) return t('displaySettings.source.system')
  const item = organizations.value.find((organization) => String(organization.id) === id)
  return item ? `${item.name} (#${item.id})` : `${t('displaySettings.source.organization')} #${id}`
}

function sourceLabel(row: DisplaySettingValue): string {
  return row.source.type === 'SYSTEM'
    ? t('displaySettings.source.system')
    : organizationName(row.source.organization_id)
}

function labelFor(definition: DisplaySettingDefinition): string {
  return t(fieldLabels[definition.key] || definition.key)
}

function valueFor(row: DisplaySettingValue): unknown {
  return drafts[row.key] ?? row.override_value ?? row.effective_value
}

function onDraftChange(definition: DisplaySettingDefinition, event: Event): void {
  const target = event.target as HTMLInputElement | HTMLSelectElement
  if (isBoolean(definition)) drafts[definition.key] = (target as HTMLInputElement).checked
  else if (isInteger(definition)) drafts[definition.key] = Number(target.value)
  else drafts[definition.key] = target.value
}

function rowFor(definition: DisplaySettingDefinition): DisplaySettingValue {
  return (
    values.value.find((item) => item.key === definition.key) ?? {
      key: definition.key,
      override_value: null,
      effective_value: definition.default,
      source: { type: 'SYSTEM', organization_id: null },
      inherited: true,
    }
  )
}

function displayCanonical(value: unknown): string {
  if (value === null || value === undefined) return '—'
  if (typeof value === 'boolean') return value ? 'true' : 'false'
  return String(value)
}

function optionsFor(definition: DisplaySettingDefinition): Array<{ value: string; label: string }> {
  return (
    selectOptions[definition.key] ??
    (definition.allowed_values ?? []).map((value) => ({
      value: String(value),
      label: String(value),
    }))
  )
}

function isSelect(definition: DisplaySettingDefinition): boolean {
  return optionsFor(definition).length > 0
}

function isBoolean(definition: DisplaySettingDefinition): boolean {
  return definition.data_type === 'boolean'
}

function isInteger(definition: DisplaySettingDefinition): boolean {
  return definition.data_type === 'integer'
}

function isOverridden(row: DisplaySettingValue): boolean {
  return row.override_value !== null && row.override_value !== undefined
}

function previewFor(row: DisplaySettingValue): string {
  const allValues: Record<string, unknown> = { ...displayStore.serverValues }
  for (const item of values.value) allValues[item.key] = item.effective_value
  for (const [key, value] of Object.entries(drafts)) {
    if (value !== undefined) allValues[key] = value
  }
  const runtime = valuesToRuntime(allValues as Parameters<typeof valuesToRuntime>[0])
  switch (row.key) {
    case 'Display.dateFormat':
      return formatDate('2026-01-05', runtime)
    case 'Display.dateTimeFormat':
      return formatDateTime('2026-01-05T14:30:00Z', runtime)
    case 'Display.timeFormat':
      return formatTime('14:30:00', runtime)
    case 'Display.quantityDecimals':
      return formatQuantity('1234.56789', runtime)
    case 'Display.percentageDecimals':
      return formatPercentage('12.34567', runtime)
    case 'Display.money.decimalsMode':
    case 'Display.money.symbolMode':
    case 'Display.money.symbolPosition':
    case 'Display.money.decimalSeparator':
    case 'Display.money.groupSeparator':
      return formatMoney('1234.5', runtime, '₫')
    default:
      return displayCanonical(row.effective_value)
  }
}

function syncRows(rows: DisplaySettingValue[]): void {
  values.value = rows
  for (const row of rows) drafts[row.key] = row.override_value ?? row.effective_value
}

async function loadValues(): Promise<void> {
  pageError.value = ''
  try {
    syncRows(await valuesQuery.execute())
  } catch {
    pageError.value = t('displaySettings.loadError')
  }
}

async function save(row: DisplaySettingValue): Promise<void> {
  savingKey.value = row.key
  pageError.value = ''
  try {
    const response = await setDisplaySettingOverrides(context.value, [
      { key: row.key, value: valueFor(row) },
    ])
    syncRows(response)
    await loadValues()
    if (auth.user && contextType.value === 'SYSTEM') await displayStore.load(auth.user.id)
    if (auth.user && contextType.value === 'ORGANIZATION')
      await displayStore.load(auth.user.id, organizationId.value)
  } catch (error: unknown) {
    pageError.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? t('displaySettings.forbidden')
        : t('displaySettings.saveError')
  } finally {
    savingKey.value = null
  }
}

async function reset(row: DisplaySettingValue): Promise<void> {
  savingKey.value = row.key
  pageError.value = ''
  try {
    syncRows(await resetDisplaySettingOverrides(context.value, [row.key]))
    await loadValues()
    if (auth.user && contextType.value === 'SYSTEM') await displayStore.load(auth.user.id)
    if (auth.user && contextType.value === 'ORGANIZATION')
      await displayStore.load(auth.user.id, organizationId.value)
  } catch (error: unknown) {
    pageError.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? t('displaySettings.forbidden')
        : t('displaySettings.saveError')
  } finally {
    savingKey.value = null
  }
}

watch(contextKey, () => {
  if (contextType.value === 'ORGANIZATION' && !organizationId.value) return
  void loadValues()
})

watch(organizationId, () => {
  if (contextType.value === 'ORGANIZATION') void loadValues()
})

onMounted(async () => {
  try {
    await Promise.all([definitionsQuery.execute(), organizationsQuery.execute()])
    const firstOrganization = organizations.value[0]
    if (firstOrganization) organizationId.value = String(firstOrganization.id)
    await loadValues()
  } catch {
    pageError.value = t('displaySettings.loadError')
  }
})
</script>

<template>
  <div class="breadcrumbs">⌂ <span>›</span> {{ t('displaySettings.breadcrumb') }}</div>
  <div class="page-heading">
    <div>
      <h1>{{ t('displaySettings.title') }}</h1>
      <p>{{ t('displaySettings.description') }}</p>
    </div>
  </div>

  <section class="content-panel display-settings-panel">
    <div class="context-toolbar">
      <label for="display-context">{{ t('displaySettings.context') }}</label>
      <select id="display-context" v-model="contextType" class="field-input context-select">
        <option value="SYSTEM">{{ t('displaySettings.system') }}</option>
        <option value="ORGANIZATION">{{ t('displaySettings.organization') }}</option>
      </select>
      <select
        v-if="contextType === 'ORGANIZATION'"
        id="display-organization"
        v-model="organizationId"
        class="field-input context-select"
        :disabled="organizationsQuery.isLoading.value"
      >
        <option value="" disabled>{{ t('displaySettings.selectOrganization') }}</option>
        <option
          v-for="organization in organizations"
          :key="String(organization.id)"
          :value="String(organization.id)"
        >
          {{ organization.name }} ({{ organization.code }})
        </option>
      </select>
    </div>
    <p v-if="pageError" class="inline-error" role="alert">{{ pageError }}</p>
    <div v-if="busy && values.length === 0" class="display-settings-skeleton" aria-busy="true">
      <span v-for="index in 6" :key="index" />
    </div>
    <div v-else class="display-settings-groups">
      <section
        v-for="group in groups"
        :key="group.category"
        class="display-group"
        :aria-labelledby="`display-group-${group.category}`"
      >
        <h2 :id="`display-group-${group.category}`">{{ group.title }}</h2>
        <div class="display-fields">
          <article v-for="definition in group.items" :key="definition.key" class="display-field">
            <template v-if="rowFor(definition)">
              <div class="display-field-main">
                <label :for="`setting-${definition.key}`">{{ labelFor(definition) }}</label>
                <small class="setting-key">{{ definition.key }}</small>
                <div class="display-control">
                  <select
                    v-if="isSelect(definition)"
                    :id="`setting-${definition.key}`"
                    :value="String(drafts[definition.key])"
                    class="field-input"
                    @change="onDraftChange(definition, $event)"
                  >
                    <option
                      v-for="option in optionsFor(definition)"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{
                        option.label.startsWith('displaySettings.') ? t(option.label) : option.label
                      }}
                    </option>
                  </select>
                  <input
                    v-else-if="isBoolean(definition)"
                    :id="`setting-${definition.key}`"
                    :checked="Boolean(drafts[definition.key])"
                    type="checkbox"
                    class="check-input"
                    @change="onDraftChange(definition, $event)"
                  />
                  <input
                    v-else
                    :id="`setting-${definition.key}`"
                    :value="String(drafts[definition.key])"
                    class="field-input"
                    :type="isInteger(definition) ? 'number' : 'text'"
                    :min="definition.min"
                    :max="definition.max"
                    @change="onDraftChange(definition, $event)"
                  />
                </div>
              </div>
              <div class="display-field-meta">
                <span class="effective-value"
                  ><strong>{{ t('displaySettings.effective') }}:</strong>
                  {{ displayCanonical(rowFor(definition).effective_value) }}</span
                >
                <span
                  ><strong>{{ t('displaySettings.overrideValue') }}:</strong>
                  {{ displayCanonical(rowFor(definition).override_value) }}</span
                >
                <span
                  ><strong>{{ t('displaySettings.sourceLabel') }}:</strong>
                  {{ sourceLabel(rowFor(definition)) }}</span
                >
                <span
                  :class="isOverridden(rowFor(definition)) ? 'state-override' : 'state-inherited'"
                >
                  {{
                    isOverridden(rowFor(definition))
                      ? t('displaySettings.override')
                      : t('displaySettings.inherited')
                  }}
                </span>
                <span class="preview"
                  ><strong>{{ t('displaySettings.preview') }}:</strong>
                  {{ previewFor(rowFor(definition)) }}</span
                >
              </div>
              <div class="display-field-actions">
                <button
                  class="primary-button"
                  type="button"
                  :disabled="savingKey === rowFor(definition).key"
                  @click="save(rowFor(definition))"
                >
                  {{
                    savingKey === rowFor(definition).key
                      ? t('displaySettings.saving')
                      : t('displaySettings.save')
                  }}
                </button>
                <button
                  v-if="isOverridden(rowFor(definition))"
                  class="secondary-button"
                  type="button"
                  :disabled="savingKey === rowFor(definition).key"
                  @click="reset(rowFor(definition))"
                >
                  {{ t('displaySettings.reset') }}
                </button>
              </div>
            </template>
          </article>
          <p v-if="group.items.length === 0" class="empty-state">
            {{ t('displaySettings.noFields') }}
          </p>
        </div>
      </section>
    </div>
  </section>
</template>

<style scoped>
.display-settings-panel {
  display: grid;
  gap: 20px;
}
.context-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.context-toolbar label {
  color: #294b65;
  font-size: 12px;
  font-weight: 700;
}
.context-select {
  width: min(280px, 100%);
}
.display-settings-groups {
  display: grid;
  gap: 22px;
}
.display-group {
  border-top: 1px solid #e4ebef;
  padding-top: 16px;
}
.display-group h2 {
  margin: 0 0 12px;
  color: #183b57;
  font-size: 16px;
}
.display-fields {
  display: grid;
  gap: 10px;
}
.display-field {
  display: grid;
  grid-template-columns: minmax(220px, 1fr) minmax(300px, 1.4fr) auto;
  align-items: center;
  gap: 16px;
  padding: 14px;
  border: 1px solid #e4ebef;
  border-radius: 7px;
  background: #fff;
}
.display-field-main {
  min-width: 0;
}
.display-field-main label {
  display: block;
  color: #294b65;
  font-size: 13px;
  font-weight: 700;
}
.setting-key {
  display: block;
  margin-top: 4px;
  color: #8798a6;
  font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
  font-size: 10px;
}
.display-control .field-input {
  width: 100%;
}
.check-input {
  width: 18px;
  height: 18px;
}
.display-field-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 12px;
  color: #6e8190;
  font-size: 11px;
}
.display-field-meta span {
  white-space: nowrap;
}
.state-override {
  color: #0878b6;
  font-weight: 700;
}
.state-inherited {
  color: #7b8d9d;
}
.preview {
  color: #183b57;
}
.display-field-actions {
  display: flex;
  gap: 7px;
  justify-content: flex-end;
}
.primary-button,
.secondary-button {
  white-space: nowrap;
}
.display-settings-skeleton {
  display: grid;
  gap: 10px;
}
.display-settings-skeleton span {
  display: block;
  height: 64px;
  border-radius: 7px;
  background: linear-gradient(90deg, #eef3f6 25%, #f8fafb 50%, #eef3f6 75%);
  background-size: 200% 100%;
  animation: skeleton 1.2s infinite;
}
@keyframes skeleton {
  to {
    background-position: -200% 0;
  }
}
@media (max-width: 900px) {
  .display-field {
    grid-template-columns: 1fr;
  }
  .display-field-actions {
    justify-content: flex-start;
  }
}
</style>
