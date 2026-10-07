<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import { BanOutline, CreateOutline, EyeOutline, PowerOutline } from '@vicons/ionicons5'

const props = withDefaults(
  defineProps<{
    active?: boolean
    busy?: boolean
    showView?: boolean
    showEdit?: boolean
    showToggle?: boolean
    viewLabel?: string
    editLabel?: string
    activateLabel?: string
    deactivateLabel?: string
  }>(),
  { active: undefined, busy: false, showView: false, showEdit: false, showToggle: false },
)

const emit = defineEmits<{
  view: []
  edit: []
  toggle: []
}>()

const { t } = useI18n()
const toggleLabel = computed(() =>
  props.active === false
    ? (props.activateLabel ?? t('common.activate'))
    : (props.deactivateLabel ?? t('common.deactivate')),
)
const toggleIcon = computed(() => (props.active === false ? PowerOutline : BanOutline))
</script>

<template>
  <span class="table-row-actions">
    <button
      v-if="props.showView"
      type="button"
      class="table-row-action"
      :title="props.viewLabel ?? t('common.view')"
      :aria-label="props.viewLabel ?? t('common.view')"
      @click="emit('view')"
    >
      <NIcon :component="EyeOutline" aria-hidden="true" />
    </button>
    <button
      v-if="props.showEdit"
      type="button"
      class="table-row-action"
      :title="props.editLabel ?? t('common.edit')"
      :aria-label="props.editLabel ?? t('common.edit')"
      @click="emit('edit')"
    >
      <NIcon :component="CreateOutline" aria-hidden="true" />
    </button>
    <button
      v-if="props.showToggle && props.active !== undefined"
      type="button"
      class="table-row-action table-row-action--state"
      :disabled="props.busy"
      :title="toggleLabel"
      :aria-label="toggleLabel"
      @click="emit('toggle')"
    >
      <NIcon :component="toggleIcon" aria-hidden="true" />
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
.table-row-action:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
