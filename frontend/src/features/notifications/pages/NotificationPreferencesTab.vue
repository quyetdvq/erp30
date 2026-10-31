<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import PushDevicesPanel from './PushDevicesPanel.vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  getNotificationPreferences,
  patchNotificationPreferences,
  type NotificationPreference,
} from '@/services/notificationSettings'
import {
  groupedNotificationRows, isExplicitConsentChannel, notificationLabelKey, preferenceChanged,
  preferenceSelection, preferenceSourceLabel,
  type PreferenceSelection,
} from '../notificationSettingsPresentation'

const { t } = useI18n()
const auth = useAuthStore()
function labelFor(key: string): string {
  const name = notificationLabelKey(key)
  return name ? t(`notificationSettings.keys.${name}`) : key
}
const rows = ref<NotificationPreference[]>([])
const groups = computed(() => groupedNotificationRows(rows.value))
const pushConsented = computed(() => rows.value.some((row) => row.key.endsWith('.push')
  && row.user_override === true && row.effective_value === true && !row.locked))
const drafts = ref<Record<string, PreferenceSelection>>({})
const loading = ref(false)
const saving = ref<string | null>(null)
const error = ref('')
const status = ref('')
const pendingCount = computed(() => rows.value.filter((row) => preferenceChanged(row, drafts.value[row.key])).length)
// Integrate with ERP's existing workspace/router/beforeunload guard.
// A dirty draft, including one left after a 409, must not silently disappear on navigation.
// Read-only UI and unchanged rows remain clean.
useUnsavedChanges(computed(() => pendingCount.value > 0))
const canEdit = (row: NotificationPreference): boolean => !row.locked && !loading.value && saving.value === null
const canSave = (row: NotificationPreference): boolean => canEdit(row) && preferenceChanged(row, drafts.value[row.key])

async function load(): Promise<void> {
  if (loading.value || saving.value !== null) return
  const generation = auth.getSessionGeneration()
  loading.value = true
  error.value = ''
  status.value = ''
  try {
    const result = await getNotificationPreferences()
    if (generation !== auth.getSessionGeneration()) return
    rows.value = result
    drafts.value = Object.fromEntries(result.map((row) => [row.key, preferenceSelection(row.user_override)]))
  } catch {
    if (generation === auth.getSessionGeneration()) error.value = t('notificationSettings.loadError')
  } finally {
    loading.value = false
  }
}
async function save(row: NotificationPreference): Promise<void> {
  if (!canSave(row)) return
  const draft = drafts.value[row.key]
  if (draft === undefined) return
  const generation = auth.getSessionGeneration()
  saving.value = row.key
  error.value = ''
  status.value = ''
  try {
    const result = await patchNotificationPreferences([{
      key: row.key,
      value: draft === 'inherit' ? null : draft === 'on',
      expected_override_epoch: row.override_epoch,
      expected_user_revision: row.user_revision,
    }])
    if (generation !== auth.getSessionGeneration()) return
    const updated = result.find((item) => item.key === row.key)
    if (!updated) throw new Error('MISSING_NOTIFICATION_PREFERENCE_ACK')
    rows.value = rows.value.map((item) => item.key === updated.key ? updated : item)
    // Reconcile to the server's canonical response (including new revision).
    drafts.value[row.key] = preferenceSelection(updated.user_override)
    status.value = t('notificationSettings.saved')
  } catch (e) {
    if (generation === auth.getSessionGeneration()) {
      error.value = axios.isAxiosError(e) && e.response?.status === 409
        ? t('notificationSettings.conflict') : t('notificationSettings.saveError')
    }
  } finally {
    saving.value = null
  }
}
onMounted(() => void load())
</script>

<template>
  <section class="content-panel m11-panel">
    <header class="m11-heading">
      <div>
        <h1>{{ t('notificationSettings.personalTitle') }}</h1>
        <p>{{ t('notificationSettings.personalHelp') }}</p>
      </div>
      <button type="button" class="ds-button ds-button--secondary" data-ds-icon="refresh" :disabled="loading || saving !== null" @click="load">{{ t('notificationSettings.reload') }}</button>
    </header>
    <p v-if="pendingCount > 0" role="status" class="m11-notice">{{ t('notificationSettings.pendingCount', { count: pendingCount }) }}</p>
    <p v-if="error" role="alert" class="inline-error">{{ error }}</p>
    <p v-if="status" role="status">{{ status }}</p>
    <p v-if="loading" aria-busy="true">{{ t('notificationSettings.loading') }}</p>
    <p v-if="!loading && rows.length === 0 && !error">{{ t('notificationSettings.empty') }}</p>

    <section v-for="group in groups" :key="group.channel" class="m11-group" :aria-label="t(`notificationSettings.channels.${group.channel}`)">
      <header>
        <h2>{{ t(`notificationSettings.channels.${group.channel}`) }}</h2>
        <p>{{ t(`notificationSettings.channelHelp.${group.channel}`) }}</p>
      </header>
      <div v-for="row in group.items" :key="row.key" class="m11-row">
        <div class="m11-detail">
          <strong>{{ labelFor(row.key) }}</strong>
          <small v-if="preferenceChanged(row, drafts[row.key])" class="m11-unsaved">{{ t('notificationSettings.unsaved') }}</small>
          <small v-if="row.capability === 'UNAVAILABLE'">{{ t('notificationSettings.unavailable') }}</small>
          <small v-else-if="row.producer_state === 'INACTIVE'">{{ t('notificationSettings.inactive') }}</small>
          <small v-if="row.locked && row.capability === 'AVAILABLE'">{{ t('notificationSettings.locked') }}</small>
        </div>
        <label class="m11-field">
          <span>{{ t('notificationSettings.choice') }}</span>
          <select v-model="drafts[row.key]" :aria-label="`${labelFor(row.key)}: ${t('notificationSettings.choice')}`" :disabled="!canEdit(row)" @change="status = ''">
            <option value="inherit">{{ isExplicitConsentChannel(row.key) ? t('notificationSettings.noConsentInherit') : t('notificationSettings.inherit') }}</option>
            <option value="on">{{ t('notificationSettings.on') }}</option>
            <option value="off">{{ t('notificationSettings.off') }}</option>
          </select>
        </label>
        <div class="m11-effective">
          <span>{{ t('notificationSettings.effective') }}: <strong>{{ row.effective_value ? t('notificationSettings.on') : t('notificationSettings.off') }}</strong></span>
          <small>{{ t(`notificationSettings.${preferenceSourceLabel(row.effective_source)}`) }}</small>
        </div>
        <button type="button" class="ds-button ds-button--primary" data-ds-icon="save" :disabled="!canSave(row)" @click="save(row)">
          {{ saving === row.key ? t('notificationSettings.saving') : t('notificationSettings.save') }}
        </button>
      </div>
    </section>
    <PushDevicesPanel :consented="pushConsented" />
  </section>
</template>

<style scoped>
.m11-panel { padding: 18px; display: grid; gap: 16px; }
.m11-heading { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; flex-wrap: wrap; }
.m11-heading h1, .m11-group h2 { margin: 0; }
.m11-heading p, .m11-group p { margin: 6px 0 0; opacity: .78; }
.m11-heading button { white-space: nowrap; }
.m11-group { border: 1px solid var(--ds-border-subtle); border-radius: 10px; padding: 16px; display: grid; gap: 12px; min-width: 0; }
.m11-group h2 { font-size: 1.1rem; }
.m11-row { display: grid; grid-template-columns: minmax(170px,2fr) minmax(125px,1fr) minmax(140px,1fr) auto; gap: 14px; align-items: center; border-top: 1px solid var(--ds-border-subtle); padding-top: 12px; min-width: 0; }
.m11-detail, .m11-effective { display: grid; gap: 3px; min-width: 0; }
.m11-row small { overflow-wrap: anywhere; opacity: .76; }
.m11-row .m11-unsaved { color: var(--ds-warning); font-weight: 600; opacity: 1; }
.m11-field { display: grid; gap: 5px; }
.m11-field select { width: 100%; max-width: 250px; min-height: 34px; }
.m11-row button { min-height: 34px; padding: 6px 12px; }
.m11-notice { margin: 0; font-weight: 600; }
@media(max-width:850px) { .m11-row { grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media(max-width:540px) { .m11-row { grid-template-columns: 1fr; } .m11-row button { justify-self: start; } }
</style>
