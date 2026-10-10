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
  // Invalidate responses started under a previous session, including logout/login races.
  let sessionGeneration = 0
  let permissionRefresh: Promise<void> | null = null
  let permissionGeneration = 0
  function getSessionGeneration(): number {
    return sessionGeneration
  }

  function setAuthenticatedUser(nextUser: AuthUser): void {
    user.value = nextUser
    permissionSnapshotState.value = 'ready'
  }

  function clearSession(): void {
    ++sessionGeneration
    ++permissionGeneration
    permissionRefresh = null
    user.value = null
    permissionSnapshotState.value = 'unavailable'
    useDisplaySettingsStore().clearSession()
    useWorkspaceStore().clearSession()
  }

  /** Called after a protected endpoint reports an expired server session. */
  function expireSession(): void {
    hydrated.value = true
    clearSession()
  }

  async function hydrate(): Promise<void> {
    if (hydrated.value) return
    hydrated.value = true
    const generation = ++sessionGeneration
    permissionSnapshotState.value = 'loading'
    try {
      const nextUser = await getCurrentUser()
      if (generation !== sessionGeneration) return
      setAuthenticatedUser(nextUser)
      useDisplaySettingsStore().activateUser(nextUser.id)
      useWorkspaceStore().activateUser(nextUser.id)
    } catch (requestError: unknown) {
      if (generation !== sessionGeneration) return
      if (!axios.isAxiosError(requestError) || requestError.response?.status !== 401)
        error.value = 'Không thể tải phiên đăng nhập.'
      clearSession()
    }
  }

  async function login(email: string, password: string, remember: boolean): Promise<void> {
    const generation = ++sessionGeneration
    busy.value = true
    error.value = ''
    try {
      const nextUser = await loginRequest({ email, password, remember })
      if (generation !== sessionGeneration) return
      setAuthenticatedUser(nextUser)
      hydrated.value = true
      useDisplaySettingsStore().activateUser(nextUser.id)
      useWorkspaceStore().activateUser(nextUser.id)
    } catch (requestError: unknown) {
      if (generation !== sessionGeneration) return
      error.value = axios.isAxiosError(requestError)
        ? (requestError.response?.data?.message ?? 'Đăng nhập không thành công.')
        : 'Đăng nhập không thành công.'
      throw requestError
    } finally {
      if (generation === sessionGeneration) busy.value = false
    }
  }

  async function logout(): Promise<void> {
    // Invalidate pending /auth/me or profile responses before the server roundtrip.
    clearSession()
    hydrated.value = true
    busy.value = false
    await logoutRequest()
  }

  async function saveProfile(payload: {
    username: string
    email: string
    password?: string
  }): Promise<void> {
    const generation = sessionGeneration
    const currentId = user.value?.id
    const nextUser = await updateProfile(payload)
    if (generation === sessionGeneration && currentId === nextUser.id)
      setAuthenticatedUser(nextUser)
  }

  function refreshBasePermissions(force = false): Promise<void> {
    if (!user.value) {
      permissionSnapshotState.value = 'unavailable'
      return Promise.resolve()
    }
    if (permissionRefresh && !force) return permissionRefresh
    const generation = sessionGeneration
    const permissionVersion = ++permissionGeneration
    const currentId = String(user.value.id)
    permissionSnapshotState.value = 'loading'
    const pending = (async () => {
      try {
        const nextUser = await getCurrentUser()
        if (generation !== sessionGeneration || permissionVersion !== permissionGeneration) return
        if (String(nextUser.id) !== currentId) {
          expireSession() // Do not merge another user's permissions into this session.
          throw new Error('Authenticated user changed during permission refresh.')
        }
        setAuthenticatedUser(nextUser)
      } catch (requestError: unknown) {
        if (generation === sessionGeneration && permissionVersion === permissionGeneration) {
          permissionSnapshotState.value = 'unavailable'
        }
        throw requestError
      }
    })()
    permissionRefresh = pending
    void pending
      .finally(() => {
        if (permissionRefresh === pending) permissionRefresh = null
      })
      .catch(() => undefined)
    return pending
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
    expireSession,
    getSessionGeneration,
    saveProfile,
    refreshBasePermissions,
    markPermissionSnapshotStale,
    clearError,
  }
})
