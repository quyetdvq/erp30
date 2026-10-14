<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  error: unknown | null
  validationError: string | null
  isParentRole: boolean
  imagePartialError: boolean
}>()
const { t } = useI18n()
</script>

<template>
  <p v-if="error || validationError" class="inline-error" role="alert">
    {{ validationError ?? t('product.requestError') }}
  </p>
  <p v-if="isParentRole" class="restriction-note" role="status">
    {{ t('product.parentRestriction') }}
  </p>
  <p v-if="imagePartialError" class="inline-error" role="alert">
    {{ t('product.imagePartialSave') }}
  </p>
</template>

<style scoped>
.inline-error {
  margin: 0;
  color: #b64444;
  font-size: 12px;
}
.restriction-note {
  color: #536b7b;
  font-size: 12px;
}
</style>
