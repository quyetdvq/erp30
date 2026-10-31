<script setup lang="ts">
import axios from 'axios'
import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import UserAvatar from '@/components/users/UserAvatar.vue'
import { avatarSelectionIssue } from '@/services/userAvatarRules'
import { deleteUserAvatar, uploadUserAvatar } from '@/services/userAvatar'
import { reportApiError } from '@/services/errorBoundary'

const props = defineProps<{
  userId: string | number
  hasAvatar: boolean
  avatarUrl?: string | null
  canEdit: boolean
  label: string
}>()
const emit = defineEmits<{ updated: [hasAvatar: boolean] }>()
const { t } = useI18n()
const hasAvatar = ref(props.hasAvatar)
const currentAvatarUrl = ref(props.avatarUrl ?? null)
const revision = ref(0)
const busy = ref(false)
const selected = ref<File | null>(null)
const localPreview = ref<string | null>(null)
const error = ref('')
const input = ref<HTMLInputElement | null>(null)
watch(() => props.hasAvatar, (value) => { hasAvatar.value = value })
watch(() => props.avatarUrl, (value) => { currentAvatarUrl.value = value ?? null })
watch(() => props.userId, () => {
  clearSelection()
  hasAvatar.value = props.hasAvatar
  currentAvatarUrl.value = props.avatarUrl ?? null
  error.value = ''
})
function clearSelection(): void {
  if (localPreview.value) URL.revokeObjectURL(localPreview.value)
  localPreview.value = null
  selected.value = null
  if (input.value) input.value.value = ''
}
function chooseFile(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0]
  clearSelection()
  if (!file) return
  const issue = avatarSelectionIssue(file)
  if (issue) {
    error.value = issue === 'size' ? t('security.avatarInvalidSize') : t('security.avatarInvalidType')
    return
  }
  error.value = ''
  selected.value = file
  localPreview.value = URL.createObjectURL(file)
}
async function upload(): Promise<void> {
  if (!selected.value || !props.canEdit || busy.value) return
  busy.value = true
  error.value = ''
  try {
    const saved = await uploadUserAvatar(props.userId, selected.value)
    currentAvatarUrl.value = saved?.avatar_url ?? null
    hasAvatar.value = true
    revision.value++
    emit('updated', true)
    clearSelection()
  } catch (caught: unknown) {
    const notice = reportApiError(caught)
    // Keep the selected file for retry; surface HTTP status when API has no machine code.
    const status = axios.isAxiosError(caught) ? caught.response?.status : undefined
    const code = notice?.code ?? (status ? `HTTP_${status}` : null)
    error.value = code
      ? `${t('security.avatarUploadFailed')} (${code})`
      : t('security.avatarUploadFailed')
  } finally {
    busy.value = false
  }
}
async function remove(): Promise<void> {
  if (!props.canEdit || !hasAvatar.value || busy.value) return
  busy.value = true
  error.value = ''
  try {
    await deleteUserAvatar(props.userId)
    hasAvatar.value = false
    currentAvatarUrl.value = null
    revision.value++
    emit('updated', false)
    clearSelection()
  } catch (caught: unknown) {
    error.value = t('security.avatarDeleteFailed')
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
onBeforeUnmount(clearSelection)
</script>

<template>
  <section class="avatar-manager" :aria-label="t('security.avatarLabel')">
    <div class="avatar-manager__preview">
      <img v-if="localPreview" :src="localPreview" :alt="t('security.avatarPreview')" class="avatar-manager__local" />
      <UserAvatar v-else :user-id="userId" :has-avatar="hasAvatar" :avatar-url="currentAvatarUrl" :fallback="label" :revision="revision" size="large" />
    </div>
    <div v-if="canEdit" class="avatar-manager__actions">
      <label class="avatar-manager__file">
        <span>{{ t('security.avatarChoose') }}</span>
        <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" :disabled="busy" @change="chooseFile" />
      </label>
      <p class="security-note">{{ t('security.avatarLimit') }}</p>
      <div class="avatar-manager__buttons">
        <button data-ds-icon="upload" type="button" class="primary-button" :disabled="busy || !selected" @click="void upload()">{{ t('security.avatarUpload') }}</button>
        <button data-ds-icon="delete" v-if="hasAvatar" type="button" class="secondary-button ds-button--danger-outline" :disabled="busy" @click="void remove()">{{ t('security.avatarRemove') }}</button>
      </div>
    </div>
    <p v-if="error" class="inline-error" role="alert">{{ error }}</p>
  </section>
</template>

<style scoped>
.avatar-manager { display: flex; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
.avatar-manager__preview { width: 82px; height: 82px; border-radius: 50%; overflow: hidden; }
.avatar-manager__local { width: 82px; height: 82px; border-radius: 50%; object-fit: cover; }
.avatar-manager__actions { display: grid; gap: 8px; min-width: 200px; flex: 1; }
.avatar-manager__file { display: grid; gap: 5px; }
.avatar-manager__file input { width: 100%; max-width: 300px; }
.avatar-manager__buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.inline-error { color: var(--ds-danger); font-size: 12px; }
</style>
