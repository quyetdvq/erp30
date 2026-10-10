import { computed, onScopeDispose, ref, watch, type ComputedRef, type Ref } from 'vue'
import { useRoute } from 'vue-router'
import { i18n } from '@/i18n'
import { useWorkspaceStore } from '@/stores/workspace'

type Readable<T> = Readonly<Ref<T>> | ComputedRef<T>

/**
 * Track an editing form against its last acknowledged (loaded/saved) snapshot.
 * The workspace tab guard handles navigation; confirmDiscard handles modal close.
 * Call markClean after a successful save or after installing API-loaded defaults.
 */
export function useUnsavedChanges<T>(model: Readable<T>, enabled?: Readable<boolean>) {
  const route = useRoute()
  const workspace = useWorkspaceStore()
  const snapshot = ref(JSON.stringify(model.value))
  const dirty = computed(
    () => (enabled ? enabled.value : true) && JSON.stringify(model.value) !== snapshot.value,
  )
  const tabKey = computed(() => String(route.name || route.path))

  const stop = watch(
    [dirty, tabKey],
    ([value, key], previous) => {
      const previousKey = previous?.[1]
      if (previousKey !== undefined && previousKey !== key) {
        workspace.markDirty(previousKey, false)
      }
      workspace.markDirty(key, value)
    },
    { immediate: true, flush: 'sync' },
  )

  function beforeUnload(event: BeforeUnloadEvent): void {
    if (!dirty.value) return
    event.preventDefault()
    event.returnValue = ''
  }
  if (typeof window !== 'undefined') window.addEventListener('beforeunload', beforeUnload)

  onScopeDispose(() => {
    if (typeof window !== 'undefined') window.removeEventListener('beforeunload', beforeUnload)
    stop()
    workspace.markDirty(tabKey.value, false)
  })

  function markClean(): void {
    snapshot.value = JSON.stringify(model.value)
    workspace.markDirty(tabKey.value, false)
  }

  function confirmDiscard(): boolean {
    if (!dirty.value) return true
    return typeof window === 'undefined' || typeof window.confirm !== 'function'
      ? false
      : window.confirm(i18n.global.t('shell.dirtyCloseConfirm'))
  }

  return { dirty, markClean, confirmDiscard }
}
