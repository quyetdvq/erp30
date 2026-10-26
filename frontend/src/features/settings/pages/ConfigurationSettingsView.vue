<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import { useBasePermission } from '@/composables/useBasePermission'
import { useAuthStore } from '@/stores/auth'
import {
  getNotificationRuntimeConfig,
  updateNotificationRuntimeConfig,
  type NotificationRuntimeItem,
  type NotificationRuntimeSnapshot,
} from '@/services/notificationRuntimeConfig'

const { t } = useI18n()
const auth = useAuthStore()
const { hasBasePermission } = useBasePermission()
const canView = computed(() => hasBasePermission('notification_setting', 'view'))
const canEdit = computed(() => hasBasePermission('notification_setting', 'edit'))
const snapshot = ref<NotificationRuntimeSnapshot | null>(null)
const draft = reactive<Record<string, boolean>>({})
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const sections = ['administration', 'producers', 'delivery', 'email', 'push', 'scheduler'] as const
const grouped = computed(() => sections.map((section) => ({
  section,
  items: snapshot.value?.items.filter((item) => item.section === section) ?? [],
})))
const changed = computed(() => snapshot.value !== null && snapshot.value.items.some((item) => item.enabled !== draft[item.key]))

function applyData(data: NotificationRuntimeSnapshot) {
  snapshot.value = data
  for (const item of data.items) draft[item.key] = item.enabled
}

async function reload() {
  if (!canView.value) return
  loading.value = true
  error.value = ''
  success.value = ''
  const generation = auth.getSessionGeneration()
  try {
    const result = await getNotificationRuntimeConfig()
    if (generation === auth.getSessionGeneration()) applyData(result)
  } catch {
    if (generation === auth.getSessionGeneration()) error.value = t('runtimeConfiguration.loadError')
  } finally {
    if (generation === auth.getSessionGeneration()) loading.value = false
  }
}

function label(item: NotificationRuntimeItem): string {
  return t(`runtimeConfiguration.flags.${item.key}`)
}

async function save() {
  if (!snapshot.value || !canEdit.value || loading.value || saving.value) return
  const changes = snapshot.value.items.map((item) => ({ key: item.key, enabled: draft[item.key] ?? false }))
  const prior = snapshot.value.items.find((item) => item.key === 'DINKERP_M11_EMAIL_SEND_ENABLED')
  if (prior && !prior.enabled && draft[prior.key]
    && !window.confirm(t('runtimeConfiguration.confirmEmailSend'))) return
  const oldPush = snapshot.value.items.find((item) => item.key === 'DINKERP_M11_PUSH_SEND_ENABLED')
  if (oldPush && !oldPush.enabled && draft[oldPush.key]
    && !window.confirm(t('runtimeConfiguration.confirmPushSend'))) return
  const oldInAppTask = snapshot.value.items.find((item) => item.key === 'DINKERP_M11_TASKS_INAPP_ENABLED')
  if (oldInAppTask && !oldInAppTask.enabled && draft[oldInAppTask.key]
    && !window.confirm(t('runtimeConfiguration.confirmTasksInApp'))) return
  const oldPushTask = snapshot.value.items.find((item) => item.key === 'DINKERP_M11_TASKS_PUSH_ENABLED')
  if (oldPushTask && !oldPushTask.enabled && draft[oldPushTask.key]
    && !window.confirm(t('runtimeConfiguration.confirmTasksPush'))) return
  const oldEmailTask = snapshot.value.items.find((item) => item.key === 'DINKERP_M11_TASKS_EMAIL_ENABLED')
  if (oldEmailTask && !oldEmailTask.enabled && draft[oldEmailTask.key]
    && !window.confirm(t('runtimeConfiguration.confirmTasksEmail'))) return
  saving.value = true
  error.value = ''
  success.value = ''
  const generation = auth.getSessionGeneration()
  try {
    const result = await updateNotificationRuntimeConfig(snapshot.value.revision, changes)
    if (generation === auth.getSessionGeneration()) {
      applyData(result)
      success.value = t('runtimeConfiguration.saved')
    }
  } catch (err: unknown) {
    if (generation === auth.getSessionGeneration()) {
      error.value = axios.isAxiosError(err) && err.response?.status === 409
        ? t('runtimeConfiguration.conflict') : t('runtimeConfiguration.saveError')
    }
  } finally {
    if (generation === auth.getSessionGeneration()) saving.value = false
  }
}

onMounted(() => { void reload() })
</script>

<template>
  <main class="runtime-config">
    <header>
      <h1>{{ t('runtimeConfiguration.title') }}</h1>
      <p>{{ t('runtimeConfiguration.description') }}</p>
    </header>
    <p v-if="!canView" role="alert">{{ t('runtimeConfiguration.forbidden') }}</p>
    <template v-else>
      <p class="info">{{ t('runtimeConfiguration.migrationNote') }}</p>
      <p class="info">{{ t('runtimeConfiguration.secretNote') }}</p>
      <div class="actions">
        <button type="button" :disabled="loading || saving" @click="reload">{{ t('runtimeConfiguration.reload') }}</button>
        <button type="button" :disabled="!canEdit || !snapshot || loading || saving" @click="save">
          {{ saving ? t('runtimeConfiguration.saving') : t('runtimeConfiguration.saveAll') }}
        </button>
        <small v-if="snapshot">{{ t('runtimeConfiguration.revision') }}: {{ snapshot.revision }}</small>
      </div>
      <p v-if="loading" aria-busy="true">{{ t('runtimeConfiguration.loading') }}</p>
      <p v-if="error" role="alert" class="error">{{ error }}</p>
      <p v-if="success" role="status">{{ success }}</p>
      <div v-if="snapshot" class="sections">
        <section v-for="group in grouped" :key="group.section" class="group">
          <h2>{{ t(`runtimeConfiguration.sections.${group.section}`) }}</h2>
          <p v-if="group.section === 'scheduler'" class="scheduler-note">
            {{ t('runtimeConfiguration.schedulerNote') }}
          </p>
          <label v-for="item in group.items" :key="item.key" class="flag">
            <input v-model="draft[item.key]" type="checkbox" :disabled="!canEdit || saving" />
            <span>
              <strong>{{ label(item) }}</strong>
              <small class="key">{{ item.key }}</small>
              <small>{{ item.source === 'database' ? t('runtimeConfiguration.database') : t('runtimeConfiguration.legacy') }}</small>
            </span>
          </label>
        </section>
      </div>
      <p v-if="changed" class="pending">{{ t('runtimeConfiguration.unsaved') }}</p>
    </template>
  </main>
</template>

<style scoped>
.runtime-config { max-width: 1050px; margin: 0 auto; padding: 8px 16px 28px; }
.runtime-config h1 { margin-bottom: 8px; }
.runtime-config h2 { font-size: 1.1rem; margin: 0 0 14px; }
.runtime-config p { line-height: 1.55; }
.info { color: var(--n-text-color, inherit); }
.sections { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 350px), 1fr)); gap: 16px; margin-top: 18px; }
.group { border: 1px solid #8297a966; border-radius: 10px; padding: 18px; }
.flag { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; cursor: pointer; }
.flag + .flag { border-top: 1px solid #8297a933; }
.flag input { margin-top: 4px; width: 18px; height: 18px; }
.flag span { display: flex; flex-direction: column; gap: 4px; overflow-wrap: anywhere; }
.flag small { opacity: .75; }
.key { font-family: monospace; }
.scheduler-note { font-size: .9rem; opacity: .85; }
.actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 18px; }
.actions button { padding: 8px 16px; border-radius: 6px; border: 1px solid #8297a9; background: transparent; color: inherit; cursor: pointer; }
.actions button:disabled { opacity: .5; cursor: not-allowed; }
.error { color: #c73737; }
.pending { font-weight: 600; }
</style>
