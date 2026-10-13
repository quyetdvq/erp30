<script setup lang="ts">
import { computed, watch } from 'vue'
import { darkTheme, NConfigProvider, NGlobalStyle, NMessageProvider } from 'naive-ui'
import { RouterView } from 'vue-router'
import router from '@/router'

import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const isDark = computed(() => auth.isAuthenticated && workspace.themeMode === 'dark')
const naiveTheme = computed(() => (isDark.value ? darkTheme : null))

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
  <NConfigProvider :theme="naiveTheme">
    <NGlobalStyle />
    <NMessageProvider>
      <div class="app-root" :class="{ 'app-root--dark': isDark }">
        <RouterView />
      </div>
    </NMessageProvider>
  </NConfigProvider>
</template>
