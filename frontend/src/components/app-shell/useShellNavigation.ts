import { computed, h, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { NButton, NIcon, type MenuOption, type MenuGroupOption } from 'naive-ui'
import { Star, StarOutline } from '@vicons/ionicons5'
import { useRouter } from 'vue-router'

import {
  ORGANIZATION_NAV_PATHS,
  RECENT_ROUTE_HINTS,
  SYSTEM_NAV_PATHS,
  navItems,
  sidebarModes,
  type NavigationItem,
} from '@/app/navigation'
import { useBasePermission } from '@/composables/useBasePermission'
import { SIDEBAR_MODES, useWorkspaceStore, type SidebarMode } from '@/stores/workspace'

// All menus (including recent, favorites and command search) share one permission gate.
export function useShellNavigation() {
  const workspace = useWorkspaceStore()
  const router = useRouter()
  const { t } = useI18n()
  const { hasBasePermissionKey } = useBasePermission()

  const mobileMenuOpen = ref(false)
  const commandOpen = ref(false)
  const menuSearch = ref('')
  const commandSearch = ref('')

  const icon = (component: object) => () => h(NIcon, null, { default: () => h(component) })

  const activeMode = computed(() => workspace.sidebarMode)

  const activeModeLabel = computed(() => {
    const mode = sidebarModes.find((item) => item.key === activeMode.value) || sidebarModes[0]
    return t(mode.labelKey)
  })

  function visibleNavItems(items: NavigationItem[]): NavigationItem[] {
    return items.flatMap((item) => {
      const children = item.children ? visibleNavItems(item.children) : undefined
      if (item.children && children?.length) return [{ ...item, children }]
      if (item.children) return []
      return !item.permission || hasBasePermissionKey(item.permission) ? [item] : []
    })
  }

  const allowedNavItems = computed(() => visibleNavItems(navItems))

  function flattenNavItems(items: NavigationItem[]): NavigationItem[] {
    return items.flatMap((item) => (item.children ? flattenNavItems(item.children) : [item]))
  }

  // Favorites, Recent and Command Palette share the same permission-filtered leaves.
  const allowedNavLeaves = computed(() => flattenNavItems(allowedNavItems.value))
  const permittedMenuByPath = computed(
    () => new Map(allowedNavLeaves.value.map((item) => [item.to, item])),
  )

  const favoriteItems = computed(() =>
    workspace.favorites.flatMap((path) => {
      const item = permittedMenuByPath.value.get(path)
      return item ? [item] : []
    }),
  )

  const recentItems = computed<NavigationItem[]>(() =>
    workspace.recent.flatMap((path) => {
      const destination = router.resolve(path)
      if (!destination.name || destination.name === 'not-found' || destination.meta.public)
        return []
      const menuItem = permittedMenuByPath.value.get(path)
      if (menuItem) return [menuItem]

      const hint = RECENT_ROUTE_HINTS.get(String(destination.name))
      if (!hint || (hint.permission && !hasBasePermissionKey(hint.permission))) return []
      const labelKey = destination.meta.labelKey
      return typeof labelKey === 'string' ? [{ labelKey, to: path, icon: hint.icon }] : []
    }),
  )

  function filterNavItems(items: NavigationItem[], search: string): NavigationItem[] {
    if (!search) return items
    return items.flatMap((item) => {
      if (
        t(item.labelKey).toLocaleLowerCase().includes(search) ||
        item.to.toLocaleLowerCase().includes(search)
      )
        return [item]
      const children = item.children ? filterNavItems(item.children, search) : []
      return children.length ? [{ ...item, children }] : []
    })
  }

  const modeItems = computed(() => {
    let items: NavigationItem[]
    switch (activeMode.value) {
      case 'favorites':
        items = favoriteItems.value
        break
      case 'recent':
        items = recentItems.value
        break
      case 'system':
        items = allowedNavItems.value.filter((item) => SYSTEM_NAV_PATHS.has(item.to))
        break
      case 'organization':
        items = allowedNavItems.value.filter((item) => ORGANIZATION_NAV_PATHS.has(item.to))
        break
      default:
        items = allowedNavItems.value.filter(
          (item) => !SYSTEM_NAV_PATHS.has(item.to) && !ORGANIZATION_NAV_PATHS.has(item.to),
        )
    }
    return filterNavItems(items, menuSearch.value.trim().toLocaleLowerCase())
  })

  const activeModeCount = computed(() => {
    if (activeMode.value === 'favorites') return favoriteItems.value.length
    if (activeMode.value === 'recent') return recentItems.value.length
    return null
  })

  const sidebarEmptyMessage = computed(() => {
    if (menuSearch.value.trim()) return t('shell.noMatchingMenus')
    if (activeMode.value === 'favorites') return t('shell.emptyFavorites')
    if (activeMode.value === 'recent') return t('shell.emptyRecent')
    return t('shell.noAvailableMenus')
  })

  function renderFavoriteExtra(option: MenuOption | MenuGroupOption) {
    if (option.children?.length) return null
    const path = String(option.key)
    const item = permittedMenuByPath.value.get(path)
    if (!item) return null
    const favorite = workspace.isFavorite(path)
    const actionLabel = t(favorite ? 'shell.removeFavorite' : 'shell.addFavorite', {
      name: t(item.labelKey),
    })
    return h(
      NButton,
      {
        class: ['sidebar-favorite-toggle', favorite && 'sidebar-favorite-toggle--active'],
        quaternary: true,
        circle: true,
        size: 'tiny',
        title: actionLabel,
        'aria-label': actionLabel,
        'aria-pressed': favorite,
        onClick: (event: MouseEvent) => {
          event.stopPropagation()
          workspace.toggleFavorite(path)
        },
        onKeydown: (event: KeyboardEvent) => event.stopPropagation(),
      },
      { icon: () => h(NIcon, null, { default: () => h(favorite ? Star : StarOutline) }) },
    )
  }

  function menuNodeProps(option: MenuOption | MenuGroupOption) {
    return { 'aria-label': typeof option.label === 'string' ? option.label : '' }
  }

  function toMenuOption(item: NavigationItem): MenuOption {
    return {
      key: item.to,
      label: t(item.labelKey),
      icon: icon(item.icon),
      disabled: item.disabled,
      children: item.children?.map(toMenuOption),
    }
  }

  const menuOptions = computed<MenuOption[]>(() => modeItems.value.map(toMenuOption))

  const commandOptions = computed(() => {
    const search = commandSearch.value.toLowerCase()
    return allowedNavLeaves.value
      .filter((item) => t(item.labelKey).toLowerCase().includes(search))
      .map((item) => ({ key: item.to, label: t(item.labelKey), icon: icon(item.icon) }))
  })

  function selectMode(mode: string): void {
    if (SIDEBAR_MODES.includes(mode as SidebarMode)) {
      if (activeMode.value !== mode) menuSearch.value = ''
      workspace.setSidebarMode(mode as SidebarMode)
    }
    if (workspace.sidebarCollapsed) workspace.setSidebarCollapsed(false)
  }

  function handleMenuSelect(key: string): void {
    menuSearch.value = ''
    mobileMenuOpen.value = false
    void router.push(key)
  }

  function openCommandPalette(): void {
    commandSearch.value = ''
    commandOpen.value = true
  }

  function openCommandRoute(key: string | number): void {
    handleMenuSelect(String(key))
    commandOpen.value = false
  }

  function handleCommandKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Enter') return
    const firstCommand = commandOptions.value[0]
    if (!firstCommand) return
    event.preventDefault()
    openCommandRoute(firstCommand.key)
  }

  return {
    mobileMenuOpen,
    commandOpen,
    menuSearch,
    commandSearch,
    activeMode,
    activeModeLabel,
    activeModeCount,
    sidebarEmptyMessage,
    menuOptions,
    commandOptions,
    renderFavoriteExtra,
    menuNodeProps,
    selectMode,
    handleMenuSelect,
    openCommandPalette,
    openCommandRoute,
    handleCommandKeydown,
  }
}
