import { computed } from 'vue'

import { useAuthStore } from '@/stores/auth'

export type PermissionSnapshotState = 'loading' | 'ready' | 'stale' | 'unavailable'

function normalizeKey(resource: string, action: string): string | null {
  const normalizedResource = resource.trim().toLowerCase()
  const normalizedAction = action.trim().toLowerCase()
  if (!/^[a-z0-9_]+$/.test(normalizedResource) || !/^[a-z0-9_]+$/.test(normalizedAction)) {
    return null
  }
  return `${normalizedResource}.${normalizedAction}`
}

export function useBasePermission() {
  const auth = useAuthStore()
  const snapshotState = computed(() => auth.permissionSnapshotState)

  function hasBasePermissionKey(key: string): boolean {
    if (auth.permissionSnapshotState !== 'ready') return false
    const normalized = key.trim().toLowerCase()
    if (!/^[a-z0-9_]+\.[a-z0-9_]+$/.test(normalized)) return false
    return auth.user && Array.isArray(auth.user.base_permissions)
      ? auth.user.base_permissions.includes(normalized)
      : false
  }

  function hasBasePermission(resource: string, action: string): boolean {
    const key = normalizeKey(resource, action)
    return key !== null && hasBasePermissionKey(key)
  }

  return {
    snapshotState,
    hasBasePermission,
    hasBasePermissionKey,
    refreshBasePermissions: auth.refreshBasePermissions,
  }
}
