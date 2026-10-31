<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { setLocale, supportedLocales, type AppLocale } from '@/i18n'
import { useFormatters } from '@/composables/useFormatters'
import { useAuthStore } from '@/stores/auth'
import {
  DISPLAY_SETTING_KEYS,
  type DisplaySettingKey,
  type DisplaySettingValue,
} from '@/services/displaySettings'
import { useDisplaySettingsStore } from '@/stores/displaySettings'

const { t, locale } = useI18n()
const auth = useAuthStore()
const settings = useDisplaySettingsStore()
const formatters = useFormatters()

function activateAuthenticatedUser(): void {
  const userId = auth.user?.id
  if (userId !== undefined && userId !== null) settings.activateUser(userId)
}

onMounted(activateAuthenticatedUser)
watch(() => auth.user?.id, activateAuthenticatedUser)

const fieldLabels: Record<DisplaySettingKey, string> = {
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

const groups = computed(() => [
  { category: 'DATE_TIME', keys: DISPLAY_SETTING_KEYS.slice(0, 5) },
  { category: 'NUMBER', keys: DISPLAY_SETTING_KEYS.slice(5, 10) },
  { category: 'MONEY', keys: DISPLAY_SETTING_KEYS.slice(10) },
])

const options: Partial<Record<DisplaySettingKey, Array<{ value: string; label: string }>>> = {
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

function valueFor(key: DisplaySettingKey): DisplaySettingValue {
  return settings.effectiveValues[key]
}

function hasOverride(key: DisplaySettingKey): boolean {
  return Object.prototype.hasOwnProperty.call(settings.localPreferences, key)
}

function onValueChange(key: DisplaySettingKey, event: Event): void {
  const target = event.target as HTMLInputElement | HTMLSelectElement
  let value: DisplaySettingValue = target.value
  if (key === 'Display.quantityDecimals' || key === 'Display.percentageDecimals')
    value = Number(target.value)
  if (key === 'Display.trailingZeros') value = (target as HTMLInputElement).checked
  settings.setLocalPreference(key, value)
}

function clearOverride(key: DisplaySettingKey): void {
  settings.clearLocalPreference(key)
}

function changeLocale(value: string): void {
  if (supportedLocales.includes(value as AppLocale)) setLocale(value as AppLocale)
}

function onLocaleChange(event: Event): void {
  changeLocale((event.target as HTMLSelectElement).value)
}
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('displaySettings.preferencesTitle') }}</h1>
      <p>{{ t('displaySettings.preferencesDescription') }}</p>
    </div>
  </div>

  <section class="content-panel preference-panel">
    <div class="preference-language">
      <label for="preference-language">{{ t('displaySettings.language') }}</label>
      <select id="preference-language" :value="locale" class="field-input" @change="onLocaleChange">
        <option value="vi">Tiếng Việt</option>
        <option value="en">English</option>
      </select>
    </div>
    <p class="preference-note">{{ t('displaySettings.localOnly') }}</p>
    <section v-for="group in groups" :key="group.category" class="display-group">
      <h2>{{ t(`displaySettings.categories.${group.category}`) }}</h2>
      <div class="preference-fields">
        <div v-for="key in group.keys" :key="key" class="preference-field">
          <label :for="`preference-${key}`">{{ t(fieldLabels[key]) }}</label>
          <select
            v-if="options[key]"
            :id="`preference-${key}`"
            class="field-input"
            :value="String(valueFor(key))"
            @change="onValueChange(key, $event)"
          >
            <option v-for="option in options[key]" :key="option.value" :value="option.value">
              {{ option.label.startsWith('displaySettings.') ? t(option.label) : option.label }}
            </option>
          </select>
          <input
            v-else-if="key === 'Display.trailingZeros'"
            :id="`preference-${key}`"
            class="check-input"
            type="checkbox"
            :checked="Boolean(valueFor(key))"
            @change="onValueChange(key, $event)"
          />
          <input
            v-else
            :id="`preference-${key}`"
            class="field-input"
            :type="key.includes('Decimals') ? 'number' : 'text'"
            :value="String(valueFor(key))"
            @change="onValueChange(key, $event)"
          />
          <button
            v-if="hasOverride(key)"
            class="link-button"
            type="button"
            @click="clearOverride(key)"
          >
            {{ t('displaySettings.useDefault') }}
          </button>
          <span v-else class="inherited-hint">{{ t('displaySettings.usingDefault') }}</span>
        </div>
      </div>
    </section>
    <section class="preview-box" aria-labelledby="display-preferences-preview">
      <h2 id="display-preferences-preview">{{ t('displaySettings.preview') }}</h2>
      <div class="preview-values">
        <span
          >{{ t('displaySettings.previewDate') }}: {{ formatters.formatDate('2026-01-05') }}</span
        >
        <span
          >{{ t('displaySettings.previewDateTime') }}:
          {{ formatters.formatDateTime('2026-01-05T14:30:00Z') }}</span
        >
        <span
          >{{ t('displaySettings.previewQuantity') }}:
          {{ formatters.formatQuantity('1234.56789') }}</span
        >
        <span
          >{{ t('displaySettings.previewPercentage') }}:
          {{ formatters.formatPercentage('12.34567') }}</span
        >
        <span
          >{{ t('displaySettings.previewMoney') }}:
          {{ formatters.formatMoney('1234.5', '₫') }}</span
        >
      </div>
    </section>
  </section>
</template>

<style scoped>
.preference-panel {
  display: grid;
  gap: 22px;
  padding: 18px 20px 20px;
}
.preference-language {
  display: grid;
  grid-template-columns: minmax(150px, 240px) minmax(180px, 280px);
  align-items: center;
  gap: 12px;
}
.preference-language label,
.preference-field label {
  color: var(--ds-text-secondary);
  font-size: 12px;
  font-weight: 700;
}
.preference-note {
  margin: -10px 0 0;
  color: var(--ds-text-muted);
  font-size: 12px;
}
.display-group {
  border-top: 1px solid var(--ds-border-subtle);
  padding-top: 16px;
}
.display-group h2,
.preview-box h2 {
  margin: 0 0 12px;
  color: var(--ds-text-primary);
  font-size: 16px;
}
.preference-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 18px;
}
.preference-field {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 8px;
}
.preference-field .field-input {
  grid-column: 1 / -1;
}
.check-input {
  width: 18px;
  height: 18px;
  justify-self: start;
}
.link-button {
  grid-column: 1;
  justify-self: start;
  border: 0;
  padding: 0;
  background: none;
  color: var(--ds-primary);
  font-size: 11px;
  cursor: pointer;
}
.inherited-hint {
  grid-column: 1;
  color: var(--ds-text-muted);
  font-size: 11px;
}
.preview-box {
  border-top: 1px solid var(--ds-border-subtle);
  padding-top: 16px;
}
.preview-values {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 18px;
  color: var(--ds-text-secondary);
  font-size: 12px;
}
@media (max-width: 700px) {
  .preference-panel {
    padding: 16px;
  }
  .preference-language,
  .preference-fields {
    grid-template-columns: 1fr;
  }
}
</style>
