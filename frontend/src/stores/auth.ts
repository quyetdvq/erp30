import axios from 'axios'
import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import {
  getCurrentUser,
  login as loginRequest,
  logout as logoutRequest,
  updateProfile,
  type AuthUser,
} from '@/services/auth'
import { useDisplaySettingsStore } from '@/stores/displaySettings'
import { useWorkspaceStore } from '@/stores/workspace'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const hydrated = ref(false)
  const busy = ref(false)
  const error = ref('')
  const permissionSnapshotState = ref<'loading' | 'ready' | 'stale' | 'unavailable'>('unavailable')

  const isAuthenticated = computed(() => user.value !== null)

  function setAuthenticatedUser(nextUser: AuthUser): void {
    user.value = nextUser
    permissionSnapshotState.value = 'ready'
  }

  async function hydrate(): Promise<void> {
    if (hydrated.value) return
    hydrated.value = true
    permissionSnapshotState.value = 'loading'
    try {
      const nextUser = await getCurrentUser()
      setAuthenticatedUser(nextUser)
      useDisplaySettingsStore().activateUser(nextUser.id)
      useWorkspaceStore().activateUser(nextUser.id)
    } catch (requestError: unknown) {
      if (!axios.isAxiosError(requestError) || requestError.response?.status !== 401)
        error.value = 'Không thể tải phiên đăng nhập.'
      user.value = null
      permissionSnapshotState.value = 'unavailable'
      useDisplaySettingsStore().clearSession()
      useWorkspaceStore().clearSession()
    }
  }

  async function login(email: string, password: string, remember: boolean): Promise<void> {
    busy.value = true
    error.value = ''
    try {
      const nextUser = await loginRequest({ email, password, remember })
      setAuthenticatedUser(nextUser)
      hydrated.value = true
      useDisplaySettingsStore().activateUser(nextUser.id)
      useWorkspaceStore().activateUser(nextUser.id)
    } catch (requestError: unknown) {
      error.value = axios.isAxiosError(requestError)
        ? (requestError.response?.data?.message ?? 'Đăng nhập không thành công.')
        : 'Đăng nhập không thành công.'
      throw requestError
    } finally {
      busy.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      await logoutRequest()
    } finally {
      user.value = null
      permissionSnapshotState.value = 'unavailable'
      useDisplaySettingsStore().clearSession()
      useWorkspaceStore().clearSession()
    }
  }

  async function saveProfile(payload: {
    username: string
    email: string
    password?: string
  }): Promise<void> {
    setAuthenticatedUser(await updateProfile(payload))
  }

  async function refreshBasePermissions(): Promise<void> {
    if (!user.value) {
      permissionSnapshotState.value = 'unavailable'
      return
    }

    permissionSnapshotState.value = 'loading'
    try {
      setAuthenticatedUser(await getCurrentUser())
    } catch (requestError: unknown) {
      permissionSnapshotState.value = 'unavailable'
      throw requestError
    }
  }

  function markPermissionSnapshotStale(): void {
    if (user.value) permissionSnapshotState.value = 'stale'
  }

  function clearError(): void {
    error.value = ''
  }

  return {
    user,
    hydrated,
    busy,
    error,
    permissionSnapshotState,
    isAuthenticated,
    hydrate,
    login,
    logout,
    saveProfile,
    refreshBasePermissions,
    markPermissionSnapshotStale,
    clearError,
  }
})
