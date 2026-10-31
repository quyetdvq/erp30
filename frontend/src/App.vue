<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from 'vue'
import { darkTheme, NConfigProvider, NGlobalStyle, NMessageProvider } from 'naive-ui'
import { RouterView } from 'vue-router'
import ApiErrorNoticeStack from '@/components/ui/ApiErrorNoticeStack.vue'
import router from '@/router'
import { naiveDarkOverrides, naiveLightOverrides } from '@/styles/naiveTheme'

import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const isDark = computed(() => auth.isAuthenticated && workspace.themeMode === 'dark')
const naiveTheme = computed(() => (isDark.value ? darkTheme : null))
const naiveThemeOverrides = computed(() => (isDark.value ? naiveDarkOverrides : naiveLightOverrides))

// Portals from Naive UI are mounted outside .app-root: use document-level tokens.
watch(
  isDark,
  (dark) => {
    if (typeof document !== 'undefined') document.documentElement.dataset.dsTheme = dark ? 'dark' : 'light'
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  if (typeof document !== 'undefined') delete document.documentElement.dataset.dsTheme
})

// A protected API 401 expires the session immediately; leave protected pages.
watch(
  () => auth.isAuthenticated,
  (authenticated, wasAuthenticated) => {
    if (wasAuthenticated && !authenticated && router.currentRoute.value.path !== '/login') {
      void router.replace('/login')
    }
  },
)
</script>

<template>
  <NConfigProvider :theme="naiveTheme" :theme-overrides="naiveThemeOverrides">
    <NGlobalStyle />
    <NMessageProvider>
      <div class="app-root" :class="{ 'app-root--dark': isDark }">
        <RouterView />
      </div>
      <ApiErrorNoticeStack />
    </NMessageProvider>
  </NConfigProvider>
</template>
