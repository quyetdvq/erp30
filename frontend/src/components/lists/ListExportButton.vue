<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = withDefaults(
  defineProps<{
    exportAction?: () => Promise<unknown>
    canExport?: boolean
    disabled?: boolean
  }>(),
  { canExport: true, disabled: false },
)

const emit = defineEmits<{
  requested: []
  error: [error: unknown]
  completed: [result: unknown]
}>()

const { t } = useI18n()
const busy = ref(false)

async function requestExport(): Promise<void> {
  if (!props.exportAction || busy.value) return
  emit('requested')
  busy.value = true
  try {
    const result = await props.exportAction()
    emit('completed', result)
  } catch (error: unknown) {
    emit('error', error)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <button
    v-if="props.canExport && props.exportAction"
    type="button"
    class="list-export-button"
    :disabled="props.disabled || busy"
    @click="requestExport"
  >
    {{ busy ? t('list.exporting') : t('list.export') }}
  </button>
</template>

<style scoped>
.list-export-button {
  min-height: 34px;
  border: 1px solid #1876a8;
  border-radius: 4px;
  padding: 6px 12px;
  background: #fff;
  color: #1876a8;
  cursor: pointer;
}
.list-export-button:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}
</style>
