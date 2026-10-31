<script setup lang="ts">
import AppDialogHost from '@/components/ui/AppDialogHost.vue'
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import { GitCompareOutline } from '@vicons/ionicons5'
import RemoteMultiSearchableSelect, { type RemoteMultiOption } from '@/components/forms/RemoteMultiSearchableSelect.vue'
import {
  getWorkflowAssigneeCandidates, getWorkflowNotificationAssignees,
  replaceWorkflowNotificationAssignees, type WorkflowNotificationAssignees,
} from '@/services/businessWorkflow'
import { inboxUiEnabled } from '@/features/notifications/featureFlags'

import { useBasePermission } from '@/composables/useBasePermission'
import { reportApiError } from '@/services/errorBoundary'
import { supplierById, supplierSetState, type Supplier } from '@/services/supplier'
import { supplierStateBody } from '../supplierPayload'
import { canSubmitSupplierTransition, supplierTransitionTargets } from '../supplierStatePolicy'

/**
 * Same dialog style as CUS, different execution owner.
 * Supplier transitions are server-derived, PBAC-gated and CAS-protected. Never
 * call generic Workflow execution: it does not own Supplier expected_version.
 */
const props = withDefaults(defineProps<{
  supplier: Supplier
  variant?: 'icon' | 'button'
  busy?: boolean
  beforeOpen?: () => boolean
}>(), { variant: 'icon', busy: false, beforeOpen: undefined })
const emit = defineEmits<{ changed: [status: string]; conflict: [] }>()
const { t, te } = useI18n()
const { hasBasePermission } = useBasePermission()
const dialogOpen = ref(false)
const working = ref(false)
const metadataLoading = ref(false)
const currentSupplier = ref<Supplier | null>(null)
let requestSequence = 0
const selected = ref('')
const error = ref('')
const conflict = ref(false)
const assignees = ref<WorkflowNotificationAssignees | null>(null)
const assigneeDraft = ref<string[]>([])
const selectedUserOptions = ref<RemoteMultiOption[]>([])
const assigneeLoading = ref(false)
const assigneeFailed = ref(false)
const assigneeSaved = ref(false)
const showAssigneeEditor = ref(false)
let assigneeSequence = 0
const dialog = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
let returnFocusTo: HTMLElement | null = null

const authorized = computed(() => hasBasePermission('supplier', 'setstate') &&
  props.supplier.allowed_actions?.setstate === true)
const disabled = computed(() => props.busy || working.value)
const assigneeChanged = computed(() => {
  if (assignees.value === null) return false
  const ordered = (ids: readonly string[]): string[] => [...ids].sort((a, b) =>
    a.length - b.length || a.localeCompare(b),
  )
  return JSON.stringify(ordered(assigneeDraft.value)) !== JSON.stringify(ordered(assignees.value.user_ids))
})
// No ACTIVE/INACTIVE/DRAFT fallback: these targets are the current row's
// effective, Lifecycle-backed transitions computed by SupplierReadService.
const targets = computed(() => supplierTransitionTargets(currentSupplier.value))
const canApply = computed(() => currentSupplier.value !== null && authorized.value &&
  !metadataLoading.value && !disabled.value &&
  canSubmitSupplierTransition(currentSupplier.value, selected.value) && !assigneeChanged.value)
function stateLabel(code: string): string {
  const key = `status.${code.toLowerCase()}`
  return te(key) ? t(key) : code
}

function recipientLabel(user: { id: string; username: string; name: string | null }): string {
  return user.name?.trim() ? `${user.name.trim()} (@${user.username})` : `@${user.username}`
}
async function searchAssignees(q: string, page: number): Promise<{ options: RemoteMultiOption[]; total: number }> {
  const supplier = currentSupplier.value
  if (!dialogOpen.value || !supplier) return { options: [], total: 0 }
  const response = await getWorkflowAssigneeCandidates('supplier', String(supplier.id), { q, page, page_size: 25 })
  return { options: response.data.map(user => ({ value: user.id, label: recipientLabel(user) })),
    total: response.meta.total }
}
async function loadAssignees(): Promise<void> {
  const supplier = currentSupplier.value
  if (!supplier || !dialogOpen.value || !inboxUiEnabled) return
  const request = ++assigneeSequence
  assigneeLoading.value = true
  assigneeFailed.value = false
  assigneeSaved.value = false
  try {
    const loaded = await getWorkflowNotificationAssignees('supplier', String(supplier.id))
    if (!dialogOpen.value || request !== assigneeSequence) return
    assignees.value = loaded
    assigneeDraft.value = [...loaded.user_ids]
    selectedUserOptions.value = loaded.user_ids.map(id => ({ value: id, label: `#${id}` }))
    if (loaded.user_ids.length) {
      try {
        const labels = await getWorkflowAssigneeCandidates('supplier', String(supplier.id), {
          ids: loaded.user_ids, page_size: 25,
        })
        if (!dialogOpen.value || request !== assigneeSequence) return
        const names = new Map(labels.data.map(user => [user.id, recipientLabel(user)]))
        selectedUserOptions.value = loaded.user_ids.map(id => ({ value: id, label: names.get(id) ?? `#${id}` }))
      } catch (caught: unknown) {
        // Failed name lookup must not clear saved assignments or block a transition.
        reportApiError(caught)
      }
    }
  } catch (caught: unknown) {
    if (dialogOpen.value && request === assigneeSequence) {
      assigneeFailed.value = true
      reportApiError(caught)
    }
  } finally {
    if (request === assigneeSequence) assigneeLoading.value = false
  }
}
async function saveAssignees(): Promise<void> {
  const supplier = currentSupplier.value
  if (disabled.value || !supplier || !assignees.value || !assigneeChanged.value) return
  working.value = true
  assigneeFailed.value = false
  assigneeSaved.value = false
  try {
    const saved = await replaceWorkflowNotificationAssignees(
      'supplier', String(supplier.id), assignees.value.revision, [...assigneeDraft.value],
    )
    assignees.value = saved
    assigneeDraft.value = [...saved.user_ids]
    assigneeSaved.value = true
  } catch (caught: unknown) {
    assigneeFailed.value = true
    reportApiError(caught)
  } finally {
    working.value = false
  }
}

async function openDialog(): Promise<void> {
  if (!authorized.value || disabled.value || dialogOpen.value || (props.beforeOpen && !props.beforeOpen())) return
  returnFocusTo = document.activeElement instanceof HTMLElement ? document.activeElement : null
  error.value = ''
  conflict.value = false
  selected.value = ''
  currentSupplier.value = null
  ++assigneeSequence
  assignees.value = null
  assigneeDraft.value = []
  selectedUserOptions.value = []
  assigneeLoading.value = false
  assigneeFailed.value = false
  assigneeSaved.value = false
  showAssigneeEditor.value = false
  dialogOpen.value = true
  metadataLoading.value = true
  const request = ++requestSequence
  void nextTick(() => closeButton.value?.focus())
  try {
    // List rows have row_version and action hints but not transition targets.
    // Fetch one authorized detail snapshot for both list and detail actions.
    // This guarantees the CAS version and Lifecycle choices come from the
    // same fresh server row; the POST independently enforces both again.
    const fresh = await supplierById(String(props.supplier.id))
    if (dialogOpen.value && request === requestSequence) {
      currentSupplier.value = fresh
      if (fresh.allowed_actions?.setstate !== true) error.value = t('supplier.stateNotAllowed')
      else if (inboxUiEnabled) void loadAssignees()
    }
  } catch (caught: unknown) {
    if (dialogOpen.value && request === requestSequence) {
      error.value = t('supplier.loadError')
      reportApiError(caught)
    }
  } finally {
    if (request === requestSequence) metadataLoading.value = false
  }
}
function dismiss(): void {
  ++requestSequence
  ++assigneeSequence
  dialogOpen.value = false
  selected.value = ''
  void nextTick(() => {
    if (returnFocusTo?.isConnected) returnFocusTo.focus()
    returnFocusTo = null
  })
}
function closeDialog(): void {
  if (disabled.value) return
  dismiss()
}

async function apply(): Promise<void> {
  if (!canApply.value) return
  const target = selected.value
  const snapshot = currentSupplier.value
  if (!snapshot) return
  working.value = true
  error.value = ''
  conflict.value = false
  try {
    await supplierSetState(String(snapshot.id), supplierStateBody(target, snapshot.row_version))
    working.value = false
    dismiss()
    emit('changed', target)
  } catch (caught: unknown) {
    const responseStatus = typeof caught === 'object' && caught !== null && 'response' in caught
      ? (caught as { response?: { status?: number } }).response?.status : undefined
    conflict.value = responseStatus === 409
    error.value = conflict.value ? t('supplier.conflict') : t('supplier.stateSaveError')
    if (conflict.value) emit('conflict')
    reportApiError(caught)
  } finally {
    working.value = false
  }
}
onBeforeUnmount(() => { ++requestSequence; ++assigneeSequence; if (returnFocusTo?.isConnected) returnFocusTo.focus() })
</script>

<template>
  <button v-if="authorized" type="button"
    :class="variant === 'icon' ? 'supplier-state-action-icon' : 'secondary-button'"
    :disabled="disabled" :title="t('workflow.changeState')" :aria-label="t('workflow.changeState')"
    @click="void openDialog()" data-ds-icon="changestate">

    <template v-if="variant !== 'icon'"> {{ t('workflow.changeState') }}</template>
  </button>

    <AppDialogHost :open="dialogOpen" size="lg" :mask-closable="true" @close="closeDialog">
      <section v-if="dialogOpen" ref="dialog" class="ds-dialog-surface supplier-state-dialog" role="dialog" aria-modal="true"
        :aria-label="t('workflow.changeState')">
        <header class="modal-card__header supplier-state-dialog__header">
          <h2 class="modal-card-title supplier-state-dialog__title"><NIcon :component="GitCompareOutline" aria-hidden="true" />
            {{ t('workflow.changeState') }}</h2>
          <button ref="closeButton" type="button" class="modal-card-button-close"
            :aria-label="t('common.close')" :disabled="disabled" @click="closeDialog" data-ds-icon="close" />
        </header>
        <div class="supplier-state-dialog__body">
          <section class="supplier-state-transition-section">
            <h3>{{ t('workflow.transitionSection') }}</h3>
            <p v-if="metadataLoading" role="status">{{ t('common.loading') }}</p>
            <p v-else-if="currentSupplier" class="supplier-state-current">{{ t('workflow.currentState') }}:
              <strong>{{ stateLabel(currentSupplier.status) }}</strong> <code>{{ currentSupplier.status }}</code>
            </p>
            <label v-if="!metadataLoading && currentSupplier && targets.length" class="supplier-state-label">
              <span>{{ t('workflow.availableTransition') }}</span>
              <select v-model="selected" :disabled="disabled" :aria-label="t('workflow.availableTransition')">
                <option value="">{{ t('workflow.selectTransition') }}</option>
                <option v-for="target in targets" :key="target" :value="target">
                  {{ currentSupplier.status }} → {{ target }} (setstate)
                </option>
              </select>
            </label>
            <p v-else-if="currentSupplier && !metadataLoading" role="status" class="supplier-state-hint">
              {{ t('workflow.noAvailableTransition') }}
            </p>
          </section>
          <section v-if="currentSupplier && inboxUiEnabled" class="workflow-notify-section">
            <div class="workflow-notify-heading">
              <div class="workflow-notify-title">
                <h3>{{ t('workflow.notificationSection') }}</h3>
                <p>{{ t('workflow.notificationAssignmentOptional') }}</p>
              </div>
              <button data-ds-icon="settings" type="button" class="secondary-button workflow-notify-toggle"
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
                  :popupTarget="dialog || false"
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
                  <button data-ds-icon="save" type="button" class="secondary-button" :disabled="disabled || !assigneeChanged"
                    @click="void saveAssignees()">{{ t('workflow.saveNotificationAssignees') }}</button>
                </div>
                <p v-if="assigneeSaved && !assigneeChanged" class="workflow-success" role="status">
                  {{ t('workflow.notificationAssigneesSaved') }}
                </p>
              </template>
              <div v-else-if="assigneeFailed" class="workflow-notify-retry" role="alert">
                <p class="workflow-error">{{ t('workflow.notificationAssigneesError') }}</p>
                <button data-ds-icon="retry" type="button" class="secondary-button" :disabled="disabled"
                  @click="void loadAssignees()">{{ t('workflow.notificationRetry') }}</button>
              </div>
              <p v-if="assigneeFailed && assignees" class="workflow-error" role="alert">
                {{ t('workflow.notificationAssigneesError') }}
              </p>
            </div>
          </section>
          <p v-else-if="currentSupplier && !inboxUiEnabled" class="workflow-notify-hint">
            {{ t('workflow.notificationFeatureDisabled') }}
          </p>
          <p v-if="assigneeChanged" role="alert" class="workflow-unsaved-notice">
            {{ t('workflow.notificationAssignmentDirty') }}
          </p>
          <p v-if="selected" class="supplier-state-hint">
            {{ t('supplier.confirmState', { status: stateLabel(selected) }) }}
          </p>
          <p v-if="error" role="alert" class="supplier-state-error">{{ error }}</p>
          <p v-if="conflict" class="supplier-state-hint">{{ t('supplier.preserveDraft') }}</p>
        </div>
        <footer class="modal-card__footer supplier-state-dialog__footer">
          <button data-ds-icon="cancel" type="button" class="secondary-button" :disabled="disabled" @click="closeDialog">
            {{ t('common.cancel') }}
          </button>
          <button data-ds-icon="changestate" type="button" class="primary-button" :disabled="!canApply" @click="void apply()">
            {{ working ? t('common.saving') : t('workflow.transitionSubmit') }}
          </button>
        </footer>
      </section>
    </AppDialogHost>

</template>

<style scoped>
.workflow-notify-section { border-top: 1px solid var(--ds-border-subtle); padding-top: 18px; display: grid; gap: 12px; }
.workflow-notify-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.workflow-notify-title { min-width: 0; }
.workflow-notify-title p { margin: 10px 0 0; font-size: 13px; line-height: 1.6; color: var(--ds-text-secondary); }
.workflow-notify-toggle { flex: 0 0 auto; }
.workflow-notify-editor { padding: 15px; border: 1px solid var(--ds-border-subtle); border-radius: 6px; background: var(--ds-bg-subtle); display: grid; gap: 12px; min-width: 0; }
.workflow-notify-hint { margin: 0; color: var(--ds-text-secondary); font-size: 13px; line-height: 1.6; }
.workflow-assignee-label { color: var(--ds-text-secondary); font-size: 13px; font-weight: 600; }
.workflow-notify-controls { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 12px; color: var(--ds-text-secondary); }
.workflow-notify-retry { display: grid; gap: 8px; justify-items: start; }
.workflow-error, .workflow-unsaved-notice { margin: 0; color: var(--ds-danger); font-size: 12px; }
.workflow-success { margin: 0; color: var(--ds-success); font-size: 12px; }
.supplier-state-action-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; padding: 0; border: 1px solid var(--ds-border-subtle);
  border-radius: 4px; background: var(--ds-bg-surface); color: var(--ds-warning); cursor: pointer; font-size: 17px;
}
.supplier-state-action-icon:hover:not(:disabled),
.supplier-state-action-icon:focus-visible { border-color: var(--ds-warning); }
.supplier-state-action-icon:disabled { opacity: 0.5; cursor: not-allowed; }
.supplier-state-dialog__header { flex: 0 0 auto; min-width: 0; }
.supplier-state-dialog__title { display: inline-flex; align-items: center; gap: 9px; min-width: 0; font-size: 19px; }
.supplier-state-dialog__body {
  display: grid; align-content: start; gap: 18px; padding: 18px 24px 24px;
  min-height: 0; min-width: 0; overflow-y: auto; overflow-x: hidden;
}
.supplier-state-dialog__body > * { min-width: 0; }
.supplier-state-transition-section { display: grid; gap: 10px; min-width: 0; }
.supplier-state-dialog h3 { margin: 0; font-size: 15px; color: var(--ds-text-primary); }
.supplier-state-current, .supplier-state-hint { margin: 0; color: var(--ds-text-secondary); font-size: 13px; }
.supplier-state-current code { margin-inline-start: 4px; font-size: 11px; }
.supplier-state-label { display: grid; gap: 8px; color: var(--ds-text-secondary); font-size: 13px; }
.supplier-state-label select {
  width: 100%; min-height: 38px; padding: 8px; min-width: 0;
  border: 1px solid var(--ds-border-subtle); border-radius: 4px; background: var(--ds-bg-surface); color: var(--ds-text-primary);
}
.supplier-state-error { margin: 0; color: var(--ds-danger); font-size: 12px; }
.supplier-state-dialog__footer { flex: 0 0 auto; display: flex; gap: 8px; justify-content: flex-end; }
@media (max-width: 560px) {
  .workflow-notify-heading { flex-direction: column; align-items: stretch; }
  .workflow-notify-toggle { align-self: flex-start; }
  .workflow-notify-controls { flex-wrap: wrap; }
  .supplier-state-dialog__body { padding: 14px 16px 18px; }
  .supplier-state-dialog__footer { flex-wrap: wrap; }
}
</style>
