<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { useI18n } from 'vue-i18n'
import { useBasePermission } from '@/composables/useBasePermission'
import { useAuthStore } from '@/stores/auth'
import {
  getNotificationPolicies,
  patchNotificationPolicies,
  type NotificationPolicy,
} from '@/services/notificationSettings'
import {
  groupedNotificationRows, isExplicitConsentChannel, notificationLabelKey, policyCanSubmit, policyChanged,
  type NotificationPolicyDraft,
} from '../notificationSettingsPresentation'

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const auth = useAuthStore()
function labelFor(key: string): string {
  const name = notificationLabelKey(key)
  return name ? t(`notificationSettings.keys.${name}`) : key
}
const rows = ref<NotificationPolicy[]>([])
const drafts = ref<Record<string, NotificationPolicyDraft>>({})
const policyRows = computed(() => rows.value.flatMap((row) => {
  const draft = drafts.value[row.key]
  return draft ? [{ key: row.key, row, draft }] : []
}))
const groups = computed(() => groupedNotificationRows(policyRows.value))
const loading = ref(false)
const saving = ref<string | null>(null)
const error = ref('')
const status = ref('')
const pendingCount = computed(() => policyRows.value.filter(({ row, draft }) => policyChanged(row, draft)).length)
// Integrate with ERP's existing workspace/router/beforeunload guard.
// A dirty draft, including one left after a 409, must not silently disappear on navigation.
// Read-only UI and unchanged rows remain clean.
useUnsavedChanges(computed(() => pendingCount.value > 0))
const editable = computed(() => hasBasePermission('notification_setting', 'edit'))
const canEditRow = (row: NotificationPolicy): boolean => editable.value && row.capability === 'AVAILABLE'
  && !loading.value && saving.value === null
const canSave = (row: NotificationPolicy, draft: NotificationPolicyDraft): boolean => canEditRow(row)
  && policyCanSubmit(row, draft)

async function load(): Promise<void> {
  if (loading.value || saving.value !== null) return
  const generation = auth.getSessionGeneration()
  loading.value = true
  error.value = ''
  status.value = ''
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
  if (!draft || !canSave(row, draft)) return
  const generation = auth.getSessionGeneration()
  saving.value = row.key
  error.value = ''
  status.value = ''
  try {
    const result = await patchNotificationPolicies([{
      key: row.key, default_value: draft.default_value,
      allow_user_override: draft.allow_user_override, expected_revision: row.revision,
    }])
    if (generation !== auth.getSessionGeneration()) return
    const updated = result.find((item) => item.key === row.key)
    if (!updated) throw new Error('MISSING_NOTIFICATION_POLICY_ACK')
    rows.value = rows.value.map((item) => item.key === updated.key ? updated : item)
    drafts.value[row.key] = {
      default_value: updated.default_value, allow_user_override: updated.allow_user_override,
    }
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
        <h1>{{ t('notificationSettings.systemTitle') }}</h1>
        <p>{{ t('notificationSettings.systemHelp') }}</p>
      </div>
      <button type="button" class="ds-button ds-button--secondary" data-ds-icon="refresh" :disabled="loading || saving !== null" @click="load">{{ t('notificationSettings.reload') }}</button>
    </header>
    <p v-if="pendingCount > 0" role="status" class="m11-notice">{{ t('notificationSettings.pendingCount', { count: pendingCount }) }}</p>
    <p v-if="!editable && !loading" class="m11-notice">{{ t('notificationSettings.readOnly') }}</p>
    <p v-if="error" role="alert" class="inline-error">{{ error }}</p>
    <p v-if="status" role="status">{{ status }}</p>
    <p v-if="loading" aria-busy="true">{{ t('notificationSettings.loading') }}</p>
    <p v-if="!loading && policyRows.length === 0 && !error">{{ t('notificationSettings.empty') }}</p>

    <section v-for="group in groups" :key="group.channel" class="m11-group" :aria-label="t(`notificationSettings.channels.${group.channel}`)">
      <header>
        <h2>{{ t(`notificationSettings.channels.${group.channel}`) }}</h2>
        <p>{{ t(`notificationSettings.channelHelpSystem.${group.channel}`) }}</p>
      </header>
      <div v-for="{ row, draft } in group.items" :key="row.key" class="m11-row">
        <div class="m11-detail">
          <strong>{{ labelFor(row.key) }}</strong>
          <small class="m11-key">{{ row.key }}</small>
          <small v-if="policyChanged(row, draft)" class="m11-unsaved">{{ t('notificationSettings.unsaved') }}</small>
          <small v-if="row.capability === 'UNAVAILABLE'">{{ t('notificationSettings.unavailable') }}</small>
          <small v-else-if="row.producer_state === 'INACTIVE'">{{ t('notificationSettings.inactive') }}</small>
          <small v-if="row.mandatory">{{ t('notificationSettings.mandatory') }}</small>
          <small v-if="row.allow_user_override && !draft.allow_user_override && policyChanged(row, draft)" class="m11-impact">{{ t('notificationSettings.overrideDisableWarning') }}</small>
        </div>
        <label class="m11-field">
          <input v-model="draft.default_value" type="checkbox"
            :disabled="!canEditRow(row) || isExplicitConsentChannel(row.key) || row.mandatory"
            @change="status = ''" />
          <span>{{ t('notificationSettings.defaultValue') }}</span>
        </label>
        <label class="m11-field">
          <input v-model="draft.allow_user_override" type="checkbox"
            :disabled="!canEditRow(row) || row.mandatory"
            @change="status = ''" />
          <span>{{ t('notificationSettings.allowOverride') }}</span>
        </label>
        <button type="button" class="ds-button ds-button--primary" data-ds-icon="save" :disabled="!canSave(row, draft)" @click="save(row)">
          {{ saving === row.key ? t('notificationSettings.saving') : t('notificationSettings.save') }}
        </button>
      </div>
    </section>
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
.m11-row { display: grid; grid-template-columns: minmax(170px,2fr) minmax(140px,1fr) minmax(140px,1fr) auto; gap: 14px; align-items: center; border-top: 1px solid var(--ds-border-subtle); padding-top: 12px; min-width: 0; }
.m11-detail { display: grid; gap: 3px; min-width: 0; }
.m11-row small { overflow-wrap: anywhere; opacity: .76; }
.m11-row .m11-unsaved, .m11-row .m11-impact { color: var(--ds-warning); font-weight: 600; opacity: 1; }
.m11-field { display: flex; gap: 8px; align-items: center; }
.m11-row button { min-height: 34px; padding: 6px 12px; }
.m11-notice { margin: 0; font-weight: 600; }
@media(max-width:850px) { .m11-row { grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media(max-width:540px) { .m11-row { grid-template-columns: 1fr; } .m11-row button { justify-self: start; } }
</style>
