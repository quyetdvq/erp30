<script setup lang="ts">
import { NAlert, NIcon } from 'naive-ui'
import { AlertCircleOutline } from '@vicons/ionicons5'
import { useI18n } from 'vue-i18n'

import { dismissApiError, errorNotices } from '@/services/errorBoundary'

const { t } = useI18n()
</script>

<template>
  <!-- One application-wide owner, regardless of route or open dialogs.
       Teleport avoids the AppShell stacking context and clipped workspaces. -->
  <Teleport to="body">
    <div
      v-if="errorNotices.length"
      class="api-error-notice-stack"
      data-test="global-api-notices"
      aria-live="assertive"
      aria-relevant="additions text"
    >
      <NAlert
        v-for="notice in errorNotices"
        :key="notice.id"
        :type="notice.status === 401 || notice.status === 403 ? 'warning' : 'error'"
        :class="notice.status === 401 || notice.status === 403
          ? 'api-error-notice--warning' : 'api-error-notice--error'"
        closable
        @close="dismissApiError(notice.id)"
      >
        <template #icon>
          <NIcon :color="notice.status === 401 || notice.status === 403
            ? 'var(--ds-warning)' : 'var(--ds-danger)'"><AlertCircleOutline /></NIcon>
        </template>
        <strong>{{ notice.code === 'SUPPLIER_API_NOT_ENABLED'
          ? t('supplier.apiNotEnabledTitle') : t(`errors.status.${notice.status}`) }}</strong>
        <span class="api-error-notice-copy">{{ notice.code === 'SUPPLIER_API_NOT_ENABLED'
          ? t('supplier.apiNotEnabledMessage') : (notice.message || t('errors.defaultMessage')) }}</span>
        <small v-if="notice.code || notice.requestId" class="api-error-notice-meta">
          {{ notice.code || t('errors.unknownCode')
          }}<span v-if="notice.requestId"> · {{ notice.requestId }}</span>
        </small>
      </NAlert>
    </div>
  </Teleport>
</template>

<style scoped>
.api-error-notice-stack {
  position: fixed;
  z-index: 999;
  top: max(16px, env(safe-area-inset-top));
  right: max(16px, env(safe-area-inset-right));
  display: grid;
  align-content: start;
  gap: 8px;
  width: min(440px, calc(100vw - 32px));
  max-height: calc(100dvh - 32px);
  padding: 2px;
  overflow-y: auto;
  overscroll-behavior: contain;
}
/* Severity classes belong to DinkERP: NAlert does not expose --error/--warning classes. */
.api-error-notice-stack :deep(.n-alert) {
  align-items: flex-start;
  border: 1px solid var(--ds-danger);
  border-inline-start: 4px solid var(--ds-danger);
  border-radius: var(--ds-radius-md);
  background: var(--ds-toast-error-bg);
  color: var(--ds-text-primary);
  box-shadow: var(--ds-shadow-toast);
}
.api-error-notice-stack :deep(.n-alert.api-error-notice--warning) {
  border-color: var(--ds-warning);
  border-inline-start-color: var(--ds-warning);
  background: var(--ds-toast-warning-bg);
}
.api-error-notice-stack :deep(.n-alert strong) {
  display: block;
  color: var(--ds-text-primary);
  font-size: var(--ds-font-lg);
  font-weight: 750;
  line-height: 1.4;
}
.api-error-notice-copy { display: block; margin-top: 4px; color: var(--ds-text-primary); font-size: var(--ds-font-md); overflow-wrap: anywhere; }
.api-error-notice-meta { display: block; margin-top: 6px; color: var(--ds-text-secondary); font-size: var(--ds-font-xs); overflow-wrap: anywhere; }
@media (max-width: 600px) {
  .api-error-notice-stack {
    top: max(12px, env(safe-area-inset-top));
    right: max(12px, env(safe-area-inset-right));
    width: calc(100vw - 24px);
    max-height: calc(100dvh - 24px);
  }
}
</style>
