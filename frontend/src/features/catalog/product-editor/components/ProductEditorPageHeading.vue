<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{ editing: boolean; saveFormId?: string; saveDisabled?: boolean; saveBusy?: boolean }>()
const emit = defineEmits<{ back: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ editing ? t('product.editTitle') : t('product.createTitle') }}</h1>
      <p>{{ editing ? t('product.editorEditDescription') : t('product.editorCreateDescription') }}</p>
    </div>
    <div class="product-editor-heading-actions">
      <button data-ds-icon="back" type="button" class="secondary-button" @click="emit('back')">
        {{ t('common.back') }}
      </button>
      <button
        v-if="saveFormId"
        :form="saveFormId"
        data-ds-icon="save"
        type="submit"
        class="primary-button"
        :disabled="saveDisabled || saveBusy"
      >
        {{ saveBusy ? t('common.saving') : editing ? t('product.editorSaveChanges') : t('product.editorSaveProduct') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.product-editor-heading-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--ds-space-2);
}
@media (max-width: 700px) {
  .product-editor-heading-actions { margin-top: var(--ds-space-3); }
}
</style>
