<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import { useBasePermission } from '@/composables/useBasePermission'
import { useAuthStore } from '@/stores/auth'
import {
  getNotificationPolicies,
  patchNotificationPolicies,
  type NotificationPolicy,
} from '@/services/notificationSettings'

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const auth = useAuthStore()
const labelKeys: Record<string, string> = {
  'import.completed.in_app': 'importCompleted',
  'import.completed_with_errors.in_app': 'importCompletedWithErrors',
  'import.failed.in_app': 'importFailed',
  'workflow.action_required.in_app': 'workflowActionRequired',
  'security.permission_changed.in_app': 'securityPermissionChanged',
  'import.completed.email': 'importCompletedEmail',
}
function labelFor(key: string): string { return labelKeys[key] ? t(`notificationSettings.keys.${labelKeys[key]}`) : key }
const rows = ref<NotificationPolicy[]>([])
const drafts = ref<Record<string, { default_value: boolean; allow_user_override: boolean }>>({})
// Pair each policy with its existing reactive draft. Avoid indexing a possibly
// missing record key in the template (vue-tsc noUncheckedIndexedAccess).
const policyRows = computed(() => rows.value.flatMap((row) => {
  const draft = drafts.value[row.key]
  return draft ? [{ row, draft }] : []
}))
const loading = ref(false)
const saving = ref<string | null>(null)
const error = ref('')
const status = ref('')
async function load(): Promise<void> {
  const generation = auth.getSessionGeneration()
  loading.value = true
  error.value = ''
  try {
    const result = await getNotificationPolicies()
    if (generation !== auth.getSessionGeneration()) return
    rows.value = result
    drafts.value = Object.fromEntries(result.map((item) => [item.key, {
      default_value: item.default_value, allow_user_override: item.allow_user_override,
    }]))
  } catch {
    if (generation === auth.getSessionGeneration()) error.value = t('notificationSettings.loadError')
  } finally {
    loading.value = false
  }
}
async function save(row: NotificationPolicy): Promise<void> {
  const draft = drafts.value[row.key]
  if (!draft) return
  const generation = auth.getSessionGeneration()
  saving.value = row.key
  error.value = ''
  status.value = ''
  try {
    const result = await patchNotificationPolicies([{ key: row.key, ...draft, expected_revision: row.revision }])
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
    <h1>{{ t('notificationSettings.systemTitle') }}</h1>
    <p>{{ t('notificationSettings.systemHelp') }}</p>
    <button type="button" @click="load">{{ t('notificationSettings.reload') }}</button>
    <p v-if="error" role="alert" class="inline-error">{{ error }}</p>
    <p v-if="status" role="status">{{ status }}</p>
    <p v-if="loading" aria-busy="true">{{ t('notificationSettings.loading') }}</p>
    <div v-for="{ row, draft } in policyRows" :key="row.key" class="m11-row">
      <div>
        <strong>{{ labelFor(row.key) }}</strong>
        <small>{{ row.key }}</small>
        <small v-if="row.capability === 'UNAVAILABLE'">{{ t('notificationSettings.unavailable') }}</small>
        <small v-else-if="row.producer_state === 'INACTIVE'">{{ t('notificationSettings.inactive') }}</small>
      </div>
      <label>
        <input v-model="draft.default_value" type="checkbox" :disabled="row.capability !== 'AVAILABLE' || !hasBasePermission('notification_setting', 'edit')" />
        {{ t('notificationSettings.defaultValue') }}
      </label>
      <label>
        <input v-model="draft.allow_user_override" type="checkbox" :disabled="row.capability !== 'AVAILABLE' || row.mandatory || !hasBasePermission('notification_setting', 'edit')" />
        {{ t('notificationSettings.allowOverride') }}
      </label>
      <button type="button" :disabled="saving !== null || row.capability !== 'AVAILABLE' || !hasBasePermission('notification_setting', 'edit')" @click="save(row)">
        {{ saving === row.key ? t('notificationSettings.saving') : t('notificationSettings.save') }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.m11-panel { padding: 18px; display: grid; gap: 16px; }
.m11-row { display: grid; grid-template-columns: minmax(180px,2fr) 1fr 1fr auto; gap: 14px; align-items: center; border-top: 1px solid #8297a9; padding-top: 14px; }
.m11-row small { display: block; overflow-wrap: anywhere; opacity: .7; }
.m11-row label { display: flex; align-items: center; gap: 8px; }
@media(max-width:750px) {.m11-row {grid-template-columns: 1fr;}}
</style>
