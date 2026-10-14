import { computed, onBeforeUnmount, ref, type Ref } from 'vue'

import { useWorkspaceStore } from '@/stores/workspace'

// Pointer drag paints CSS only. Persist the width on release, never during each move.
export function useShellSidebarResize(shellElement: Ref<HTMLElement | null>) {
  const workspace = useWorkspaceStore()

  const isResizingSidebar = ref(false)
  let resizeFrame: number | null = null
  let resizePointerId: number | null = null
  let resizeStartX = 0
  let resizeStartWidth = 0
  let pendingSidebarWidth = 0

  const sidebarWidth = computed(() => (workspace.sidebarCollapsed ? 72 : workspace.sidebarWidth))

  function toggleSidebar(): void {
    workspace.setSidebarCollapsed(!workspace.sidebarCollapsed)
  }

  function clampSidebarWidth(width: number): number {
    return Math.min(workspace.MAX_SIDEBAR_WIDTH, Math.max(workspace.MIN_SIDEBAR_WIDTH, width))
  }

  // Keep drag updates in the DOM; persist the final width only on pointer release.
  function paintSidebarResize(): void {
    resizeFrame = null
    shellElement.value?.style.setProperty('--sidebar-width', `${pendingSidebarWidth}px`)
  }

  function handleResizeMove(event: PointerEvent): void {
    if (event.pointerId !== resizePointerId) return
    pendingSidebarWidth = clampSidebarWidth(resizeStartWidth + event.clientX - resizeStartX)
    if (resizeFrame === null) resizeFrame = requestAnimationFrame(paintSidebarResize)
  }

  function stopResize(commit: boolean): void {
    if (resizePointerId === null) return
    window.removeEventListener('pointermove', handleResizeMove)
    window.removeEventListener('pointerup', handleResizeUp)
    window.removeEventListener('pointercancel', handleResizeCancel)
    window.removeEventListener('blur', handleResizeCancel)
    if (resizeFrame !== null) cancelAnimationFrame(resizeFrame)
    resizeFrame = null
    resizePointerId = null
    if (commit) workspace.setSidebarWidth(pendingSidebarWidth)
    else shellElement.value?.style.setProperty('--sidebar-width', `${sidebarWidth.value}px`)
    isResizingSidebar.value = false
  }

  function handleResizeUp(event: PointerEvent): void {
    if (event.pointerId !== resizePointerId) return
    handleResizeMove(event)
    stopResize(true)
  }

  function handleResizeCancel(): void {
    stopResize(false)
  }

  function startResize(event: PointerEvent): void {
    if (workspace.sidebarCollapsed || resizePointerId !== null || event.button !== 0) return
    event.preventDefault()
    resizePointerId = event.pointerId
    resizeStartX = event.clientX
    resizeStartWidth = workspace.sidebarWidth
    pendingSidebarWidth = resizeStartWidth
    isResizingSidebar.value = true
    window.addEventListener('pointermove', handleResizeMove)
    window.addEventListener('pointerup', handleResizeUp)
    window.addEventListener('pointercancel', handleResizeCancel)
    window.addEventListener('blur', handleResizeCancel)
  }

  function handleResizeKey(event: KeyboardEvent): void {
    if (event.key === 'Home') workspace.setSidebarWidth(workspace.MIN_SIDEBAR_WIDTH)
    if (event.key === 'End') workspace.setSidebarWidth(workspace.MAX_SIDEBAR_WIDTH)
    if (event.key === 'ArrowLeft') workspace.setSidebarWidth(workspace.sidebarWidth - 16)
    if (event.key === 'ArrowRight') workspace.setSidebarWidth(workspace.sidebarWidth + 16)
  }

  onBeforeUnmount(handleResizeCancel)

  return {
    sidebarWidth,
    isResizingSidebar,
    toggleSidebar,
    startResize,
    handleResizeKey,
  }
}
