<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

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
// Match the original ERP toggle behavior: the icon describes the NEXT action.
// Inactive -> PowerOutline (Activate); Active -> BanOutline (Deactivate).
const toggleIcon = computed(() => props.active === false ? 'activate' : 'deactivate')
</script>

<template>
  <span class="table-row-actions" :aria-busy="props.busy">
    <button
      v-if="canView"
      type="button"
      class="table-row-action ds-icon-control"
      :title="props.viewLabel ?? t('common.view')"
      :aria-label="props.viewLabel ?? t('common.view')"
      :disabled="props.busy"
      @click="emitIfReady('view')"
     data-ds-icon="view">

    </button>
    <button
      v-if="canMutation('edit')"
      type="button"
      class="table-row-action ds-icon-control"
      :title="props.editLabel ?? t('common.edit')"
      :aria-label="props.editLabel ?? t('common.edit')"
      :disabled="props.busy"
      @click="emitIfReady('edit')"
     data-ds-icon="edit">

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
      class="table-row-action ds-icon-control table-row-action--state ds-state-toggle"
      :data-ds-state="props.active ? 'active' : 'inactive'"
      :disabled="props.busy"
      :title="toggleLabel"
      :aria-label="toggleLabel"
      @click="emitIfReady('toggle')"
     :data-ds-icon="toggleIcon">

    </button>
    <button
      v-if="canMutation('delete')"
      type="button"
      class="table-row-action ds-icon-control ds-icon-control--danger table-row-action--delete"
      :disabled="props.busy"
      :title="props.deleteLabel ?? t('common.delete')"
      :aria-label="props.deleteLabel ?? t('common.delete')"
      @click="emitIfReady('delete')"
     data-ds-icon="delete">

    </button>
  </span>
</template>
