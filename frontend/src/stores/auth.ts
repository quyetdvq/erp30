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

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const hydrated = ref(false)
  const busy = ref(false)
  const error = ref('')

  const isAuthenticated = computed(() => user.value !== null)
  const isAdmin = computed(
    () => user.value?.groups.some((group) => ['orgadmin', 'systemadmin'].includes(group)) ?? false,
  )

  async function hydrate(): Promise<void> {
    if (hydrated.value) return
    hydrated.value = true
    try {
      user.value = await getCurrentUser()
    } catch (requestError: unknown) {
      if (!axios.isAxiosError(requestError) || requestError.response?.status !== 401)
        error.value = 'Không thể tải phiên đăng nhập.'
      user.value = null
    }
  }

  async function login(email: string, password: string, remember: boolean): Promise<void> {
    busy.value = true
    error.value = ''
    try {
      user.value = await loginRequest({ email, password, remember })
      hydrated.value = true
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
    await logoutRequest()
    user.value = null
  }

  async function saveProfile(payload: {
    username: string
    email: string
    password?: string
  }): Promise<void> {
    user.value = await updateProfile(payload)
  }

  function clearError(): void {
    error.value = ''
  }

  return {
    user,
    hydrated,
    busy,
    error,
    isAuthenticated,
    isAdmin,
    hydrate,
    login,
    logout,
    saveProfile,
    clearError,
  }
})
