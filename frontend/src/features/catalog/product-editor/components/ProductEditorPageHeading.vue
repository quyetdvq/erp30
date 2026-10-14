<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{ editing: boolean }>()
const emit = defineEmits<{ back: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ editing ? t('product.editTitle') : t('product.createTitle') }}</h1>
      <p>{{ t('product.editorDescription') }}</p>
    </div>
    <button type="button" class="secondary-button" @click="emit('back')">
      {{ t('common.back') }}
    </button>
  </div>
</template>
