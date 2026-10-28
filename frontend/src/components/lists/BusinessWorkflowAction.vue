<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import { GitCompareOutline } from '@vicons/ionicons5'
import RemoteMultiSearchableSelect, { type RemoteMultiOption } from '@/components/forms/RemoteMultiSearchableSelect.vue'
import {
  executeBusinessWorkflowOption,
  getWorkflowAssigneeCandidates,
  getBusinessWorkflowOptions,
  getWorkflowNotificationAssignees,
  replaceWorkflowNotificationAssignees,
  type WorkflowNotificationAssignees,
  type BusinessWorkflowOptions,
} from '@/services/businessWorkflow'
import { normalizeApiError, reportApiError } from '@/services/errorBoundary'
import { inboxUiEnabled } from '@/features/notifications/featureFlags'

const props = withDefaults(
  defineProps<{
    resource: string
    targetId: string
    busy?: boolean
    variant?: 'icon' | 'button'
  }>(),
  { busy: false, variant: 'icon' },
)
const emit = defineEmits<{ changed: [status: string] }>()
const { t } = useI18n()
const dialog = ref(false)
const dialogElement = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
let previousFocus: HTMLElement | null = null
let loadSequence = 0
const working = ref(false)
const failed = ref(false)
const failureCode = ref<string | null>(null)
const metadata = ref<BusinessWorkflowOptions | null>(null)
const selected = ref('')
const assignees = ref<WorkflowNotificationAssignees | null>(null)
const assigneeDraft = ref<string[]>([])
const selectedUserOptions = ref<RemoteMultiOption[]>([])
const assigneeLoading = ref(false)
const assigneeFailed = ref(false)
const assigneeSaved = ref(false)
const showAssigneeEditor = ref(false)
const disabled = computed(() => working.value || props.busy)
const assigneeChanged = computed(() => {
  if (assignees.value === null) return false
  const order = (ids: readonly string[]): string[] => [...ids].sort((a, b) =>
    a.length - b.length || a.localeCompare(b),
  )
  return JSON.stringify(order(assigneeDraft.value)) !== JSON.stringify(order(assignees.value.user_ids))
})
const canApply = computed(() => !disabled.value && metadata.value !== null &&
  selected.value !== '' && !assigneeChanged.value)

watch(assigneeDraft, () => {
  if (assigneeChanged.value) assigneeSaved.value = false
})

function candidateLabel(user: { id: string; username: string; name: string | null }): string {
  return user.name?.trim() ? `${user.name.trim()} (@${user.username})` : `@${user.username}`
}
async function searchAssignees(q: string, page: number): Promise<{ options: RemoteMultiOption[]; total: number }> {
  const response = await getWorkflowAssigneeCandidates(props.resource, props.targetId, {
    q, page, page_size: 25,
  })
  return {
    options: response.data.map(user => ({ value: user.id, label: candidateLabel(user) })),
    total: response.meta.total,
  }
}

async function loadAssignees(): Promise<void> {
  const stamp = ++loadSequence
  assigneeLoading.value = true
  assigneeFailed.value = false
  assigneeSaved.value = false
  try {
    const loaded = await getWorkflowNotificationAssignees(props.resource, props.targetId)
    if (!dialog.value || stamp !== loadSequence) return
    assignees.value = loaded
    assigneeDraft.value = [...loaded.user_ids]
    selectedUserOptions.value = loaded.user_ids.map(id => ({ value: id, label: `#${id}` }))
    if (loaded.user_ids.length > 0) {
      try {
        const labels = await getWorkflowAssigneeCandidates(props.resource, props.targetId, {
          ids: loaded.user_ids, page_size: 25,
        })
        if (!dialog.value || stamp !== loadSequence) return
        const labelById = new Map(labels.data.map(user => [user.id, candidateLabel(user)]))
        selectedUserOptions.value = loaded.user_ids.map(id => ({
          value: id, label: labelById.get(id) ?? `#${id}`,
        }))
      } catch (error: unknown) {
        // A label lookup failure must not drop an assignment or block a transition.
        reportApiError(error)
      }
    }
  } catch (error: unknown) {
    if (dialog.value && stamp === loadSequence) {
      assigneeFailed.value = true
      reportApiError(error)
    }
  } finally {
    if (stamp === loadSequence) assigneeLoading.value = false
  }
}

async function open(): Promise<void> {
  if (disabled.value || dialog.value) return
  previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null
  dialog.value = true
  failed.value = false
  failureCode.value = null
  metadata.value = null
  selected.value = ''
  assignees.value = null
  assigneeDraft.value = []
  selectedUserOptions.value = []
  assigneeLoading.value = false
  assigneeFailed.value = false
  assigneeSaved.value = false
  showAssigneeEditor.value = false
  working.value = true
  void nextTick(() => closeButton.value?.focus())
  try {
    metadata.value = await getBusinessWorkflowOptions(props.resource, props.targetId)
  } catch (error: unknown) {
    failed.value = true
    failureCode.value = normalizeApiError(error)?.code ?? null
    reportApiError(error)
  } finally {
    working.value = false
  }
  if (dialog.value && inboxUiEnabled && metadata.value?.mode === 'LIFECYCLE') {
    // Notification configuration loads independently of the transition form.
    void loadAssignees()
  }
}

function closeDialog(): void {
  if (disabled.value) return
  ++loadSequence
  dialog.value = false
  void nextTick(() => {
    if (previousFocus?.isConnected) previousFocus.focus()
    previousFocus = null
  })
}
onBeforeUnmount(() => {
  ++loadSequence
  if (previousFocus?.isConnected) previousFocus.focus()
})
function onDialogKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    closeDialog()
    return
  }
  if (event.key !== 'Tab' || !dialogElement.value) return
  const elements = [...dialogElement.value.querySelectorAll<HTMLElement>(
    'button:not(:disabled), select:not(:disabled), input:not(:disabled), [tabindex]:not([tabindex="-1"])',
  )]
  const first = elements[0]
  const last = elements[elements.length - 1]
  if (!first || !last) return
  if (event.shiftKey && (document.activeElement === first || !dialogElement.value.contains(document.activeElement))) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && (document.activeElement === last || !dialogElement.value.contains(document.activeElement))) {
    event.preventDefault()
    first.focus()
  }
}

async function saveAssignees(): Promise<void> {
  if (disabled.value || !assignees.value || !assigneeChanged.value) return
  working.value = true
  assigneeFailed.value = false
  assigneeSaved.value = false
  try {
    const result = await replaceWorkflowNotificationAssignees(
      props.resource, props.targetId, assignees.value.revision, [...assigneeDraft.value],
    )
    assignees.value = result
    assigneeDraft.value = [...result.user_ids]
    assigneeSaved.value = true
  } catch (error: unknown) {
    assigneeFailed.value = true
    reportApiError(error)
  } finally {
    working.value = false
  }
}

async function apply(): Promise<void> {
  if (!canApply.value || !metadata.value) return
  const chosen = metadata.value.options[Number(selected.value)]
  if (!chosen) return
  working.value = true
  failed.value = false
  failureCode.value = null
  try {
    await executeBusinessWorkflowOption(props.resource, props.targetId, metadata.value.mode, chosen)
    ++loadSequence
    dialog.value = false
    emit('changed', chosen.status)
    void nextTick(() => {
      if (previousFocus?.isConnected) previousFocus.focus()
      previousFocus = null
    })
  } catch (error: unknown) {
    failed.value = true
    failureCode.value = normalizeApiError(error)?.code ?? null
    reportApiError(error)
  } finally {
    working.value = false
  }
}
</script>

<template>
  <button
    type="button"
    :class="variant === 'icon' ? 'workflow-action-icon' : 'secondary-button'"
    :disabled="disabled"
    :title="t('workflow.changeState')"
    :aria-label="t('workflow.changeState')"
    @click="void open()"
  >
    <NIcon v-if="variant === 'icon'" :component="GitCompareOutline" aria-hidden="true" />
    <span v-else>{{ t('workflow.changeState') }}</span>
  </button>
  <Teleport to="body">
    <div v-if="dialog" class="workflow-dialog-backdrop" @click.self="closeDialog">
    <section ref="dialogElement" class="workflow-dialog" role="dialog" aria-modal="true"
      :aria-label="t('workflow.changeState')" @keydown.stop="onDialogKeydown">
      <header class="modal-card__header workflow-dialog__header">
        <h2 class="modal-card-title workflow-dialog__title">
          <NIcon :component="GitCompareOutline" aria-hidden="true" />
          {{ t('workflow.changeState') }}
        </h2>
        <button ref="closeButton" type="button" class="modal-card-button-close"
          :aria-label="t('common.close')" :disabled="disabled" @click="closeDialog" />
      </header>
      <div class="workflow-dialog__body">
        <p v-if="working && !metadata" role="status">{{ t('common.loading') }}</p>
        <template v-else-if="metadata">
          <section class="workflow-transition-section">
            <h3>{{ t('workflow.transitionSection') }}</h3>
            <p class="workflow-current-state">{{ t('workflow.currentState') }}:
              <strong>{{ metadata.current }}</strong>
            </p>
            <label v-if="metadata.options.length" class="workflow-select-label">
              <span>{{ t('workflow.availableTransition') }}</span>
              <select v-model="selected" :disabled="disabled"
                :aria-label="t('workflow.availableTransition')">
                <option value="">{{ t('workflow.selectTransition') }}</option>
                <option v-for="(option, index) in metadata.options"
                  :key="`${option.action_code}:${option.status}`" :value="String(index)">
                  {{ metadata.current }} &rarr; {{ option.status }} ({{ option.action_code }})
                </option>
              </select>
            </label>
            <p v-else role="status">{{ t('workflow.noAvailableTransition') }}</p>
          </section>
          <section v-if="metadata.mode === 'LIFECYCLE' && inboxUiEnabled" class="workflow-notify-section">
            <div class="workflow-notify-heading">
              <div class="workflow-notify-title">
                <h3>{{ t('workflow.notificationSection') }}</h3>
                <p>{{ t('workflow.notificationAssignmentOptional') }}</p>
              </div>
              <button type="button" class="secondary-button workflow-notify-toggle"
                :aria-expanded="showAssigneeEditor" :disabled="disabled"
                @click="showAssigneeEditor = !showAssigneeEditor">
                {{ showAssigneeEditor ? t('workflow.notificationSectionHide') : t('workflow.notificationSectionShow') }}
              </button>
            </div>
            <div v-if="showAssigneeEditor" class="workflow-notify-editor">
              <p class="workflow-notify-hint">{{ t('workflow.notificationAssigneesHint') }}</p>
              <p v-if="assigneeLoading" role="status">{{ t('common.loading') }}</p>
              <template v-else-if="assignees">
                <label class="workflow-assignee-label">{{ t('workflow.notificationAssignees') }}</label>
                <RemoteMultiSearchableSelect
                  v-model="assigneeDraft"
                  :search="searchAssignees"
                  :selected-options="selectedUserOptions"
                  :popupTarget="dialogElement || false"
                  :disabled="disabled"
                  :max-selections="25"
                  :ariaLabel="t('workflow.notificationAssignees')"
                  :emptyLabel="t('workflow.notificationNoCandidates')"
                  :errorLabel="t('workflow.notificationSearchFailed')"
                  :retryLabel="t('workflow.notificationRetry')"
                  :placeholder="t('workflow.notificationAssigneesPlaceholder')"
                />
                <div class="workflow-notify-controls">
                  <span>{{ assigneeDraft.length }} / 25</span>
                  <button type="button" class="secondary-button"
                    :disabled="disabled || !assigneeChanged" @click="void saveAssignees()">
                    {{ t('workflow.saveNotificationAssignees') }}
                  </button>
                </div>
                <p v-if="assigneeSaved && !assigneeChanged" class="workflow-success" role="status">
                  {{ t('workflow.notificationAssigneesSaved') }}
                </p>
              </template>
              <div v-else-if="assigneeFailed" class="workflow-notify-retry" role="alert">
                <p class="workflow-error">{{ t('workflow.notificationAssigneesError') }}</p>
                <button type="button" class="secondary-button" :disabled="disabled" @click="void loadAssignees()">
                  {{ t('workflow.notificationRetry') }}
                </button>
              </div>
              <p v-if="assigneeFailed && assignees" class="workflow-error" role="alert">
                {{ t('workflow.notificationAssigneesError') }}
              </p>
            </div>
          </section>
          <p v-else-if="metadata.mode === 'LIFECYCLE'" class="workflow-notify-hint">
            {{ t('workflow.notificationFeatureDisabled') }}
          </p>
          <p v-if="assigneeChanged" role="alert" class="workflow-unsaved-notice">
            {{ t('workflow.notificationAssignmentDirty') }}
          </p>
        </template>
        <p v-if="failed" role="alert" class="workflow-error">
          {{ t('workflow.transitionRequestFailed') }}
          <code v-if="failureCode">{{ failureCode }}</code>
        </p>
      </div>
      <footer class="modal-card__footer workflow-dialog__footer">
        <button type="button" class="secondary-button" :disabled="disabled" @click="closeDialog">
          {{ t('common.close') }}
        </button>
        <button type="button" class="primary-button" :disabled="!canApply" @click="void apply()">
          {{ t('workflow.transitionSubmit') }}
        </button>
      </footer>
    </section>
    </div>
  </Teleport>
</template>

<style scoped>
.workflow-action-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  padding: 0;
  border: 1px solid #dbe5ea;
  border-radius: 4px;
  background: #fff;
  color: #b45309;
  cursor: pointer;
  font-size: 17px;
}
.workflow-action-icon:hover:not(:disabled),
.workflow-action-icon:focus-visible { border-color: #b45309; }
.workflow-action-icon:disabled { opacity: 0.5; cursor: not-allowed; }
.workflow-dialog-backdrop {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
  background: rgba(10, 25, 38, 0.52);
}
.workflow-dialog {
  width: min(600px, 100%);
  max-width: calc(100vw - 24px);
  max-height: calc(100dvh - 24px);
  min-width: 0;
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: visible;
  border-radius: 8px;
  background: #fff;
  color: #1d2934;
  white-space: normal;
  text-align: start;
  box-shadow: 0 18px 48px rgba(0, 0, 0, 0.22);
}
.workflow-dialog, .workflow-dialog * { box-sizing: border-box; }
.workflow-dialog__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
  flex: 0 0 auto;
}
.workflow-dialog__title {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
  font-size: 19px;
}
.workflow-dialog__body {
  display: grid;
  align-content: start;
  gap: 18px;
  padding: 18px 24px 24px;
  min-height: 0;
  min-width: 0;
  overflow-y: auto;
  overflow-x: hidden;
}
.workflow-dialog__body > *, .workflow-notify-heading > * { min-width: 0; }
.workflow-dialog__footer { flex: 0 0 auto; display: flex; gap: 8px; justify-content: flex-end; }
.workflow-dialog h3 { margin: 0; font-size: 15px; color: #183b57; }
.workflow-transition-section { display: grid; gap: 10px; min-width: 0; }
.workflow-current-state { margin: 0; font-size: 13px; color: #536b7b; }
.workflow-select-label { display: grid; gap: 8px; min-width: 0; font-size: 13px; }
.workflow-select-label select {
  display: block;
  width: 100%;
  min-width: 0;
  min-height: 38px;
  padding: 8px;
  border: 1px solid #a9b9c7;
  border-radius: 4px;
  background: #fff;
  color: #183b57;
}
.workflow-notify-section { min-width: 0; display: grid; gap: 12px; padding-top: 16px; border-top: 1px solid #dbe5ea; }
.workflow-notify-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; min-width: 0; }
.workflow-notify-title { flex: 1 1 230px; }
.workflow-notify-title p, .workflow-notify-hint { margin: 6px 0 0; font-size: 12px; line-height: 1.6; color: #536b7b; overflow-wrap: anywhere; }
.workflow-notify-toggle { flex: 0 0 auto; }
.workflow-notify-editor { display: grid; gap: 12px; min-width: 0; padding: 12px; border: 1px solid #dbe5ea; border-radius: 6px; background: #f8fbfd; }
.workflow-assignee-label { font-size: 12px; font-weight: 700; color: #536b7b; }
.workflow-notify-controls { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; justify-content: space-between; }
.workflow-notify-controls > span { font-size: 12px; color: #536b7b; }
.workflow-error, .workflow-unsaved-notice { color: #ad3434; font-size: 12px; margin: 0; overflow-wrap: anywhere; }
.workflow-success { font-size: 12px; color: #16803a; margin: 0; }
.workflow-notify-retry { display: grid; justify-items: start; gap: 8px; }
@media (max-width: 560px) {
  .workflow-dialog__body { padding: 14px 16px 18px; }
  .workflow-dialog__footer { flex-wrap: wrap; }
}
</style>
