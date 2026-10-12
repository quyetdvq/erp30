<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import { GitCompareOutline } from '@vicons/ionicons5'
import {
  executeBusinessWorkflowOption,
  getBusinessWorkflowOptions,
  type BusinessWorkflowOptions,
} from '@/services/businessWorkflow'
import { reportApiError } from '@/services/errorBoundary'

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
const working = ref(false)
const failed = ref(false)
const metadata = ref<BusinessWorkflowOptions | null>(null)
const selected = ref('')
const disabled = computed(() => working.value || props.busy)

async function open(): Promise<void> {
  if (disabled.value) return
  dialog.value = true
  failed.value = false
  metadata.value = null
  selected.value = ''
  working.value = true
  try {
    metadata.value = await getBusinessWorkflowOptions(props.resource, props.targetId)
  } catch (error: unknown) {
    failed.value = true
    reportApiError(error)
  } finally {
    working.value = false
  }
}

async function apply(): Promise<void> {
  if (disabled.value || !metadata.value || selected.value === '') return
  const chosen = metadata.value.options[Number(selected.value)]
  if (!chosen) return
  working.value = true
  failed.value = false
  try {
    await executeBusinessWorkflowOption(props.resource, props.targetId, metadata.value.mode, chosen)
    dialog.value = false
    emit('changed', chosen.status)
  } catch (error: unknown) {
    failed.value = true
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
  <div v-if="dialog" class="workflow-dialog-backdrop">
    <section
      class="workflow-dialog"
      role="dialog"
      aria-modal="true"
      :aria-label="t('workflow.changeState')"
    >
      <h2>{{ t('workflow.changeState') }}</h2>
      <p v-if="working && !metadata">{{ t('common.loading') }}</p>
      <template v-else-if="metadata">
        <p>
          {{ t('workflow.currentState') }}: <strong>{{ metadata.current }}</strong>
        </p>
        <label v-if="metadata.options.length" class="workflow-select-label">
          <span>{{ t('workflow.availableTransition') }}</span>
          <select
            v-model="selected"
            :disabled="disabled"
            :aria-label="t('workflow.availableTransition')"
          >
            <option value="">{{ t('workflow.selectTransition') }}</option>
            <option
              v-for="(option, index) in metadata.options"
              :key="`${option.action_code}:${option.status}`"
              :value="String(index)"
            >
              {{ option.status }} ({{ option.action_code }})
            </option>
          </select>
        </label>
        <p v-else role="status">{{ t('workflow.noAvailableTransition') }}</p>
      </template>
      <p v-if="failed" class="workflow-error" role="alert">
        {{ t('workflow.transitionRequestFailed') }}
      </p>
      <footer>
        <button type="button" class="secondary-button" :disabled="disabled" @click="dialog = false">
          {{ t('common.close') }}
        </button>
        <button
          type="button"
          class="primary-button"
          :disabled="disabled || !selected"
          @click="void apply()"
        >
          {{ t('common.save') }}
        </button>
      </footer>
    </section>
  </div>
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
.workflow-action-icon:focus-visible {
  border-color: #b45309;
}
.workflow-action-icon:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.workflow-dialog-backdrop {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(10, 25, 38, 0.52);
}
.workflow-dialog {
  width: min(440px, calc(100vw - 32px));
  padding: 22px;
  border-radius: 8px;
  background: #fff;
  color: #1d2934;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
}
.workflow-dialog h2 {
  margin: 0 0 14px;
  font-size: 20px;
}
.workflow-select-label {
  display: grid;
  gap: 8px;
  margin: 18px 0;
}
.workflow-select-label select {
  padding: 8px;
  width: 100%;
  border: 1px solid #a9b9c7;
  border-radius: 4px;
}
.workflow-dialog footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 18px;
}
.workflow-error {
  color: #a12727;
}
</style>
