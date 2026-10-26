<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import PushDevicesPanel from './PushDevicesPanel.vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  getNotificationPreferences,
  patchNotificationPreferences,
  type NotificationPreference,
} from '@/services/notificationSettings'

const { t } = useI18n()
const auth = useAuthStore()
const labelKeys: Record<string, string> = {
  'import.completed.in_app': 'importCompleted',
  'import.completed_with_errors.in_app': 'importCompletedWithErrors',
  'import.failed.in_app': 'importFailed',
  'workflow.action_required.in_app': 'workflowActionRequired',
  'security.permission_changed.in_app': 'securityPermissionChanged',
  'import.completed.email': 'importCompletedEmail',
  'import.completed.push': 'importCompletedPush',
}
function labelFor(key: string): string { return labelKeys[key] ? t(`notificationSettings.keys.${labelKeys[key]}`) : key }
const rows = ref<NotificationPreference[]>([])
const pushConsented = computed(() => rows.value.some((row) => row.key === 'import.completed.push'
  && row.user_override === true && row.effective_value === true))
const drafts = ref<Record<string, string>>({})
const loading = ref(false)
const saving = ref<string | null>(null)
const error = ref('')
const status = ref('')
function asSelection(value: boolean | null): string { return value === null ? 'inherit' : value ? 'on' : 'off' }
async function load(): Promise<void> {
  const generation = auth.getSessionGeneration()
  loading.value = true
  error.value = ''
  try {
    const result = await getNotificationPreferences()
    if (generation !== auth.getSessionGeneration()) return
    rows.value = result
    drafts.value = Object.fromEntries(result.map((row) => [row.key, asSelection(row.user_override)]))
  } catch {
    if (generation === auth.getSessionGeneration()) error.value = t('notificationSettings.loadError')
  } finally {
    loading.value = false
  }
}
async function save(row: NotificationPreference): Promise<void> {
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
    rows.value = rows.value.map((item) => result.find((changed) => changed.key === item.key) ?? item)
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
    <h1>{{ t('notificationSettings.personalTitle') }}</h1>
    <p>{{ t('notificationSettings.personalHelp') }}</p>
    <button type="button" @click="load">{{ t('notificationSettings.reload') }}</button>
    <p v-if="error" role="alert" class="inline-error">{{ error }}</p>
    <p v-if="status" role="status">{{ status }}</p>
    <p v-if="loading" aria-busy="true">{{ t('notificationSettings.loading') }}</p>
    <div v-for="row in rows" :key="row.key" class="m11-row">
      <div>
        <strong>{{ labelFor(row.key) }}</strong>
        <small>{{ row.key }}</small>
        <small v-if="row.key === 'import.completed.email'">{{ t('notificationSettings.emailOptInUser') }}</small>
        <small v-if="row.key === 'import.completed.push'">{{ t('notificationSettings.pushOptInUser') }}</small>
        <small v-if="row.capability === 'UNAVAILABLE'">{{ t('notificationSettings.unavailable') }}</small>
        <small v-else-if="row.producer_state === 'INACTIVE'">{{ t('notificationSettings.inactive') }}</small>
      </div>
      <label>
        {{ t('notificationSettings.choice') }}
        <select v-model="drafts[row.key]" :disabled="row.locked || saving !== null">
          <option value="inherit">{{ t('notificationSettings.inherit') }}</option>
          <option value="on">{{ t('notificationSettings.on') }}</option>
          <option value="off">{{ t('notificationSettings.off') }}</option>
        </select>
      </label>
      <span>{{ t('notificationSettings.effective') }}: {{ row.effective_value ? t('notificationSettings.on') : t('notificationSettings.off') }} ({{ row.effective_source }})</span>
      <button type="button" :disabled="row.locked || saving !== null" @click="save(row)">
        {{ saving === row.key ? t('notificationSettings.saving') : t('notificationSettings.save') }}
      </button>
    </div>
    <PushDevicesPanel :consented="pushConsented" />
  </section>
</template>

<style scoped>
.m11-panel { padding: 18px; display: grid; gap: 16px; }
.m11-row { display: grid; grid-template-columns: minmax(180px,2fr) 1fr 1fr auto; gap: 14px; align-items: center; border-top: 1px solid #8297a9; padding-top: 14px; }
.m11-row small { display: block; overflow-wrap: anywhere; opacity: .7; }
.m11-row label { display: flex; align-items: center; gap: 8px; }
@media(max-width:750px) {.m11-row {grid-template-columns: 1fr;}}
</style>
