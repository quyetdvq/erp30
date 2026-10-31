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
  <button data-ds-icon="download"
    v-if="props.canExport && props.exportAction"
    type="button"
    class="list-export-button ds-button ds-button--secondary"
    :disabled="props.disabled || busy"
    :aria-busy="busy"
    @click="requestExport"
  >
    {{ busy ? t('list.exporting') : t('list.export') }}
  </button>
</template>
