<script setup lang="ts">
import { NButton, NResult } from 'naive-ui'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const router = useRouter()
const { t } = useI18n()
</script>

<template>
  <NResult
    status="404"
    :title="t('errors.status.404')"
    :description="t('errors.notFoundDescription')"
  >
    <template #footer>
      <NButton type="primary" @click="router.push('/')">{{ t('errors.backToWorkspace') }}</NButton>
    </template>
  </NResult>
</template>
