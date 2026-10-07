import { computed } from 'vue'

import { i18n, type AppLocale } from '@/i18n'
import {
  formatDate,
  formatDateTime,
  formatMoney,
  formatPercentage,
  formatQuantity,
  formatTime,
} from '@/presentation/formatters'
import { usePresentationSettings } from '@/composables/usePresentationSettings'

export function useFormatters() {
  const settings = usePresentationSettings()
  const locale = computed(() => i18n.global.locale.value as AppLocale)

  return {
    locale,
    formatDate: (value: string) => formatDate(value, settings.runtime.value),
    formatDateTime: (value: string) => formatDateTime(value, settings.runtime.value),
    formatTime: (value: string) => formatTime(value, settings.runtime.value),
    formatQuantity: (value: string) => formatQuantity(value, settings.runtime.value),
    formatPercentage: (value: string) => formatPercentage(value, settings.runtime.value),
    formatMoney: (value: string, currencySymbol?: string) =>
      formatMoney(value, settings.runtime.value, currencySymbol),
  }
}
