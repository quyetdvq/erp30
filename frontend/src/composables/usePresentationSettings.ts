import { computed } from 'vue'

import { useDisplaySettingsStore } from '@/stores/displaySettings'

export function usePresentationSettings() {
  const store = useDisplaySettingsStore()

  return {
    runtime: computed(() => store.effectiveRuntime),
    values: computed(() => store.effectiveValues),
    serverRuntime: computed(() => store.serverRuntime),
    localPreferences: computed(() => store.localPreferences),
    activeUserId: computed(() => store.activeUserId),
    loading: computed(() => store.loading),
    loaded: computed(() => store.loaded),
    error: computed(() => store.error),
    load: store.load,
    activateUser: store.activateUser,
    clearSession: store.clearSession,
    setLocalPreference: store.setLocalPreference,
    clearLocalPreference: store.clearLocalPreference,
  }
}
