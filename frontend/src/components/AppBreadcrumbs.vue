<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import { HomeOutline } from '@vicons/ionicons5'
import { RouterLink, useRoute } from 'vue-router'

import { useBasePermission } from '@/composables/useBasePermission'
import { breadcrumbsForRoute } from '@/router/breadcrumbs'

const route = useRoute()
const { t } = useI18n()
const { hasBasePermissionKey } = useBasePermission()
const items = computed(() => breadcrumbsForRoute(route.meta, hasBasePermissionKey))
</script>

<template>
  <nav class="app-breadcrumbs" :aria-label="t('breadcrumbs.navigation')">
    <ol>
      <li v-for="(item, index) in items" :key="`${index}:${item.labelKey}`">
        <span v-if="index > 0" class="breadcrumb-separator" aria-hidden="true">›</span>
        <RouterLink v-if="item.to" :to="item.to" class="breadcrumb-link">
          <NIcon v-if="index === 0" :size="14" aria-hidden="true"><HomeOutline /></NIcon>
          {{ t(item.labelKey) }}
        </RouterLink>
        <span
          v-else
          :class="index === items.length - 1 ? 'breadcrumb-current' : 'breadcrumb-group'"
          :aria-current="index === items.length - 1 ? 'page' : undefined"
        >
          <NIcon v-if="index === 0" :size="14" aria-hidden="true"><HomeOutline /></NIcon>
          {{ t(item.labelKey) }}
        </span>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.app-breadcrumbs {
  width: min(100%, 1680px);
  margin: 0 auto 14px;
  color: var(--ds-text-muted);
  font-size: 12px;
  line-height: 1.5;
}

.app-breadcrumbs ol {
  display: flex;
  align-items: center;
  gap: 0;
  min-width: 0;
  padding: 0;
  margin: 0;
  overflow-x: auto;
  list-style: none;
  white-space: nowrap;
  scrollbar-width: thin;
}

.app-breadcrumbs li,
.breadcrumb-link,
.breadcrumb-current,
.breadcrumb-group {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-width: 0;
}

.app-breadcrumbs li {
  flex: 0 0 auto;
}

.breadcrumb-separator {
  padding: 0 9px;
  color: var(--ds-text-muted);
  font-size: 15px;
}

.breadcrumb-link {
  border-radius: 3px;
  color: var(--ds-text-secondary);
  text-decoration: none;
}

.breadcrumb-link:hover {
  color: var(--ds-primary);
  text-decoration: underline;
}

.breadcrumb-link:focus-visible {
  outline: 2px solid var(--ds-focus-ring);
  outline-offset: 3px;
}

.breadcrumb-current {
  color: var(--ds-text-primary);
  font-weight: 600;
}

@media (max-width: 600px) {
  .breadcrumb-separator {
    padding-inline: 6px;
  }
}
</style>
