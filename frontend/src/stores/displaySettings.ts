import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import {
  DEFAULT_RUNTIME,
  DISPLAY_SETTING_KEYS,
  fetchDisplaySettingsRuntime,
  isValidDisplaySettingValue,
  readLocalPreferences,
  runtimeToValues,
  valuesToRuntime,
  writeLocalPreferences,
  type DisplaySettingKey,
  type DisplaySettingValue,
  type DisplaySettingValues,
  type LocalDisplayPreferences,
} from '@/services/displaySettings'

export const useDisplaySettingsStore = defineStore('displaySettings', () => {
  const serverRuntime = ref(DEFAULT_RUNTIME)
  const localPreferences = ref<LocalDisplayPreferences>({})
  const activeUserId = ref<string | null>(null)
  const loading = ref(false)
  const loaded = ref(false)
  const error = ref<unknown>(null)

  const serverValues = computed(() => runtimeToValues(serverRuntime.value))
  const effectiveValues = computed<DisplaySettingValues>(() => ({
    ...serverValues.value,
    ...localPreferences.value,
  }))
  const effectiveRuntime = computed(() => valuesToRuntime(effectiveValues.value))

  function activateUser(userId: number | string): void {
    const normalized = String(userId).trim()
    if (activeUserId.value === normalized) return
    activeUserId.value = normalized
    localPreferences.value = readLocalPreferences(normalized)
    serverRuntime.value = DEFAULT_RUNTIME
    loaded.value = false
    error.value = null
  }

  function clearSession(): void {
    activeUserId.value = null
    localPreferences.value = {}
    serverRuntime.value = DEFAULT_RUNTIME
    loaded.value = false
    loading.value = false
    error.value = null
  }

  async function load(userId: number | string, organizationId?: number | string): Promise<void> {
    activateUser(userId)
    loading.value = true
    error.value = null
    try {
      serverRuntime.value = await fetchDisplaySettingsRuntime(organizationId)
      loaded.value = true
    } catch (requestError: unknown) {
      error.value = requestError
      loaded.value = false
      throw requestError
    } finally {
      loading.value = false
    }
  }

  function setLocalPreference(key: DisplaySettingKey, value: DisplaySettingValue): void {
    if (!DISPLAY_SETTING_KEYS.includes(key) || !isValidDisplaySettingValue(key, value)) {
      throw new Error(`Invalid local Display Setting preference: ${key}`)
    }
    if (!activeUserId.value) throw new Error('An authenticated user is required.')
    localPreferences.value = { ...localPreferences.value, [key]: value }
    writeLocalPreferences(activeUserId.value, localPreferences.value)
  }

  function clearLocalPreference(key: DisplaySettingKey): void {
    if (!activeUserId.value) return
    const next = { ...localPreferences.value }
    delete next[key]
    localPreferences.value = next
    writeLocalPreferences(activeUserId.value, next)
  }

  return {
    serverRuntime,
    localPreferences,
    activeUserId,
    loading,
    loaded,
    error,
    serverValues,
    effectiveValues,
    effectiveRuntime,
    activateUser,
    clearSession,
    load,
    setLocalPreference,
    clearLocalPreference,
  }
})
