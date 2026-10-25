<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import {
  BanOutline,
  CreateOutline,
  EyeOutline,
  PowerOutline,
  TrashOutline,
} from '@vicons/ionicons5'
import { useBasePermission } from '@/composables/useBasePermission'
import BusinessWorkflowAction from './BusinessWorkflowAction.vue'
import { canMutateRow, canViewRow, type AllowedRowActions, type RowActionCode, type RowAuthorizationMode, type RowMutationIntent } from './rowActionPolicy'

const props = withDefaults(
  defineProps<{
    /** Effective mode always fails closed if the row hint or permission is missing. */
    authorizationMode?: RowAuthorizationMode
    allowedActions?: AllowedRowActions | null
    actionCodes?: Partial<Record<RowMutationIntent, RowActionCode>>
    /** For special VIEW intents that require a canonical mutation permission. */
    viewActionCode?: RowActionCode
    /** Set only for rows already returned by a canonical VIEW-scoped list/detail. */
    viewScopeVerified?: boolean
    active?: boolean
    busy?: boolean
    showView?: boolean
    showEdit?: boolean
    showToggle?: boolean
    stateTargetId?: string
    /** Explicit resource for server-provided row action hints (no base-permission recheck). */
    stateResource?: string
    showDelete?: boolean
    viewPermission?: string
    editPermission?: string
    togglePermission?: string
    deletePermission?: string
    viewLabel?: string
    editLabel?: string
    activateLabel?: string
    deactivateLabel?: string
    deleteLabel?: string
  }>(),
  {
    authorizationMode: 'effective',
    viewScopeVerified: false,
    active: undefined,
    busy: false,
    showView: false,
    showEdit: false,
    showToggle: false,
    showDelete: false,
  },
)

const emit = defineEmits<{
  view: []
  edit: []
  toggle: []
  delete: []
  stateChanged: [status: string]
}>()

const { t } = useI18n()
const { hasBasePermissionKey, snapshotState } = useBasePermission()
const snapshotReady = computed(() => snapshotState.value === 'ready')
const canView = computed(() => canViewRow({
  show: props.showView,
  viewScopeVerified: props.viewScopeVerified,
  authorizationMode: props.authorizationMode,
  viewActionCode: props.viewActionCode,
  allowedActions: props.allowedActions,
  snapshotReady: snapshotReady.value,
  permission: props.viewPermission,
  hasBasePermission: hasBasePermissionKey,
}))
function canMutation(intent: RowMutationIntent): boolean {
  const show = intent === 'edit' ? props.showEdit : intent === 'toggle' ? props.showToggle : props.showDelete
  const permission = intent === 'edit' ? props.editPermission :
    intent === 'toggle' ? props.togglePermission : props.deletePermission
  return canMutateRow({
    show,
    permission,
    intent,
    snapshotReady: snapshotReady.value,
    hasBasePermission: hasBasePermissionKey,
    authorizationMode: props.authorizationMode,
    allowedActions: props.allowedActions,
    actionCodes: props.actionCodes,
  })
}
function emitIfReady(action: 'view' | 'edit' | 'toggle' | 'delete'): void {
  if (props.busy || (action === 'view' ? !canView.value : !canMutation(action))) return
  switch (action) {
    case 'view': emit('view'); break
    case 'edit': emit('edit'); break
    case 'toggle': emit('toggle'); break
    case 'delete': emit('delete'); break
  }
}
const toggleLabel = computed(() =>
  props.active === false
    ? (props.activateLabel ?? t('common.activate'))
    : (props.deactivateLabel ?? t('common.deactivate')),
)
const toggleIcon = computed(() => (props.active === false ? PowerOutline : BanOutline))
</script>

<template>
  <span class="table-row-actions" :aria-busy="props.busy">
    <button
      v-if="canView"
      type="button"
      class="table-row-action"
      :title="props.viewLabel ?? t('common.view')"
      :aria-label="props.viewLabel ?? t('common.view')"
      :disabled="props.busy"
      @click="emitIfReady('view')"
    >
      <NIcon :component="EyeOutline" aria-hidden="true" />
    </button>
    <button
      v-if="canMutation('edit')"
      type="button"
      class="table-row-action"
      :title="props.editLabel ?? t('common.edit')"
      :aria-label="props.editLabel ?? t('common.edit')"
      :disabled="props.busy"
      @click="emitIfReady('edit')"
    >
      <NIcon :component="CreateOutline" aria-hidden="true" />
    </button>
    <BusinessWorkflowAction
      v-if="
        props.stateTargetId &&
        (props.stateResource || props.togglePermission) &&
        canMutation('toggle')
      "
      :resource="props.stateResource || props.togglePermission?.split('.')[0] || ''"
      :target-id="props.stateTargetId"
      :busy="props.busy"
      @changed="(status) => emit('stateChanged', status)"
    />
    <button
      v-if="
        !props.stateTargetId &&
        props.active !== undefined &&
        canMutation('toggle')
      "
      type="button"
      class="table-row-action table-row-action--state"
      :disabled="props.busy"
      :title="toggleLabel"
      :aria-label="toggleLabel"
      @click="emitIfReady('toggle')"
    >
      <NIcon :component="toggleIcon" aria-hidden="true" />
    </button>
    <button
      v-if="canMutation('delete')"
      type="button"
      class="table-row-action table-row-action--delete"
      :disabled="props.busy"
      :title="props.deleteLabel ?? t('common.delete')"
      :aria-label="props.deleteLabel ?? t('common.delete')"
      @click="emitIfReady('delete')"
    >
      <NIcon :component="TrashOutline" aria-hidden="true" />
    </button>
  </span>
</template>

<style scoped>
.table-row-actions {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.table-row-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  padding: 0;
  border: 1px solid #dbe5ea;
  border-radius: 4px;
  background: #fff;
  color: #536b7b;
  cursor: pointer;
  font-size: 17px;
}
.table-row-action:hover:not(:disabled),
.table-row-action:focus-visible {
  border-color: #1876a8;
  color: #1876a8;
}
.table-row-action--state {
  color: #b45309;
}
.table-row-action--state:hover:not(:disabled),
.table-row-action--state:focus-visible {
  border-color: #b45309;
  color: #b45309;
}
.table-row-action--delete {
  color: #a23a32;
}
.table-row-action--delete:hover:not(:disabled),
.table-row-action--delete:focus-visible {
  border-color: #a23a32;
  color: #a23a32;
}
.table-row-action:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
