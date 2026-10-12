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

const props = withDefaults(
  defineProps<{
    active?: boolean
    busy?: boolean
    showView?: boolean
    showEdit?: boolean
    showToggle?: boolean
    stateTargetId?: string
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
const { hasBasePermissionKey } = useBasePermission()
const can = (permission?: string): boolean =>
  permission === undefined || hasBasePermissionKey(permission)
function emitIfReady(action: 'view' | 'edit' | 'toggle' | 'delete'): void {
  if (props.busy) return
  switch (action) {
    case 'view':
      emit('view')
      break
    case 'edit':
      emit('edit')
      break
    case 'toggle':
      emit('toggle')
      break
    case 'delete':
      emit('delete')
      break
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
      v-if="props.showView && can(props.viewPermission)"
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
      v-if="props.showEdit && can(props.editPermission)"
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
        props.showToggle &&
        props.stateTargetId &&
        props.togglePermission &&
        can(props.togglePermission)
      "
      :resource="props.togglePermission.split('.')[0] || ''"
      :target-id="props.stateTargetId"
      :busy="props.busy"
      @changed="(status) => emit('stateChanged', status)"
    />
    <button
      v-if="
        props.showToggle &&
        !props.stateTargetId &&
        props.active !== undefined &&
        can(props.togglePermission)
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
      v-if="props.showDelete && can(props.deletePermission)"
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
