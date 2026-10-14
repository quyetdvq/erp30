import { createI18n } from 'vue-i18n'

import { en } from '@/i18n/locales/en'
import { vi } from '@/i18n/locales/vi'

export const supportedLocales = ['vi', 'en'] as const
export type AppLocale = (typeof supportedLocales)[number]

export const messages = { vi, en } as const

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
