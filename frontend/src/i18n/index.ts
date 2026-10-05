import { createI18n } from 'vue-i18n'

export const supportedLocales = ['vi', 'en'] as const
export type AppLocale = (typeof supportedLocales)[number]

export const messages = {
  vi: {
    shell: {
      openMenu: 'Mở menu',
      searchWorkspace: 'Tìm trong workspace',
      searchMenu: 'Tìm menu',
      notifications: 'Thông báo',
      theme: 'Đổi giao diện',
      language: 'Ngôn ngữ',
      profile: 'Hồ sơ cá nhân',
      signOut: 'Đăng xuất',
      filteredByPermission: 'Đã lọc theo quyền',
      openNewTab: 'Mở tab mới',
      close: 'Đóng',
      commandPalette: 'Command palette',
      commandDescription: 'Đi tới màn hình hoặc lệnh trong workspace',
      searchCommand: 'Tìm lệnh',
      commandClose: 'Đóng',
      commandMove: 'Di chuyển',
      commandOpen: 'Mở',
      modes: {
        menu: 'Menu',
        favorites: 'Yêu thích',
        recent: 'Mở gần đây',
        system: 'Hệ thống',
        organization: 'Cơ cấu tổ chức',
      },
    },
    nav: {
      dashboard: 'Tổng quan',
      approvals: 'Phê duyệt của tôi',
      organizations: 'Tổ chức và dữ liệu nền',
      groups: 'Nhóm và permission',
      products: 'Sản phẩm và vòng đời',
      sales: 'Bán hàng và khách hàng',
      users: 'Người dùng',
    },
    auth: {
      title: 'Đăng nhập workspace',
      lead: 'Đăng nhập để làm việc với dữ liệu doanh nghiệp của bạn.',
      identity: 'Email hoặc tên tài khoản',
      password: 'Mật khẩu',
      passwordPlaceholder: 'Nhập mật khẩu',
      remember: 'Ghi nhớ phiên đăng nhập',
      submit: 'Đăng nhập',
      submitting: 'Đang đăng nhập...',
      error: 'Đăng nhập không thành công.',
      sessionError: 'Không thể tải phiên đăng nhập.',
    },
    dashboard: {
      breadcrumb: 'Workspace',
      title: 'Tổng quan',
      greeting: 'Không gian làm việc của {name}.',
      quickCreate: 'Tạo nhanh',
      groups: 'Nhóm quyền của bạn',
      organizationScope: 'Phạm vi tổ chức',
      pendingRequests: 'Yêu cầu chờ xử lý',
      sessionStatus: 'Trạng thái phiên',
      ready: 'Workspace của bạn đã sẵn sàng',
      readyDescription: 'Mở màn hình từ menu bên trái hoặc tìm kiếm trong workspace để bắt đầu.',
    },
    commands: {
      dashboard: 'Về trang tổng quan',
      profile: 'Chỉnh sửa profile',
      users: 'Quản lý người dùng và phân quyền',
      organizations: 'Quản trị tổ chức',
      products: 'Sản phẩm và vòng đời',
    },
    common: {
      brandSubtitle: 'ERP Workspace',
    },
  },
  en: {
    shell: {
      openMenu: 'Open menu',
      searchWorkspace: 'Search workspace',
      searchMenu: 'Search menu',
      notifications: 'Notifications',
      theme: 'Change theme',
      language: 'Language',
      profile: 'Personal profile',
      signOut: 'Sign out',
      filteredByPermission: 'Filtered by permission',
      openNewTab: 'Open new tab',
      close: 'Close',
      commandPalette: 'Command palette',
      commandDescription: 'Go to a screen or command in the workspace',
      searchCommand: 'Search commands',
      commandClose: 'Close',
      commandMove: 'Move',
      commandOpen: 'Open',
      modes: {
        menu: 'Menu',
        favorites: 'Favorites',
        recent: 'Recent',
        system: 'System',
        organization: 'Organization',
      },
    },
    nav: {
      dashboard: 'Overview',
      approvals: 'My approvals',
      organizations: 'Organizations and foundation data',
      groups: 'Groups and permissions',
      products: 'Products and lifecycle',
      sales: 'Sales and customers',
      users: 'Users',
    },
    auth: {
      title: 'Sign in to workspace',
      lead: 'Sign in to work with your business data.',
      identity: 'Email or username',
      password: 'Password',
      passwordPlaceholder: 'Enter password',
      remember: 'Remember this session',
      submit: 'Sign in',
      submitting: 'Signing in...',
      error: 'Sign in failed.',
      sessionError: 'Unable to load the current session.',
    },
    dashboard: {
      breadcrumb: 'Workspace',
      title: 'Overview',
      greeting: '{name} workspace.',
      quickCreate: 'Quick create',
      groups: 'Your permission groups',
      organizationScope: 'Organization scope',
      pendingRequests: 'Pending requests',
      sessionStatus: 'Session status',
      ready: 'Your workspace is ready',
      readyDescription: 'Open a screen from the left menu or search the workspace to get started.',
    },
    commands: {
      dashboard: 'Go to overview',
      profile: 'Edit profile',
      users: 'Manage users and permissions',
      organizations: 'Manage organizations',
      products: 'Products and lifecycle',
    },
    common: {
      brandSubtitle: 'ERP Workspace',
    },
  },
} as const

function resolveInitialLocale(): AppLocale {
  if (typeof window !== 'undefined') {
    const stored = window.localStorage.getItem('ui.locale')
    if (stored && supportedLocales.includes(stored as AppLocale)) return stored as AppLocale
  }
  return 'vi'
}

export const i18n = createI18n({
  legacy: false,
  locale: resolveInitialLocale(),
  fallbackLocale: 'vi',
  messages,
})

export function setLocale(nextLocale: AppLocale): void {
  i18n.global.locale.value = nextLocale
  if (typeof window !== 'undefined') window.localStorage.setItem('ui.locale', nextLocale)
  if (typeof document !== 'undefined') document.documentElement.lang = nextLocale
}

setLocale(i18n.global.locale.value as AppLocale)
